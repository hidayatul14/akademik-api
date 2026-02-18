<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->select(
                'enrollments.id',
                'students.nim',
                'students.name as student_name',
                'students.email',
                'courses.code as course_code',
                'courses.name as course_name',
                'courses.credits',
                'enrollments.academic_year',
                'enrollments.semester',
                'enrollments.status'
            );

        // 🔍 SEARCH
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('students.nim', 'ilike', "%{$request->search}%")
                    ->orWhere('students.name', 'ilike', "%{$request->search}%")
                    ->orWhere('courses.code', 'ilike', "%{$request->search}%");
            });
        }

        // ⚡ ADVANCED FILTER
        if ($request->filters) {

            $logic = strtoupper($request->logic ?? 'AND');

            $query->where(function ($q) use ($request, $logic) {

                foreach ($request->filters as $filter) {

                    $method = $logic === 'OR' ? 'orWhere' : 'where';

                    switch ($filter['operator']) {

                        case 'equal':
                            $q->$method($filter['field'], '=', $filter['value']);
                            break;

                        case 'contains':
                            $q->$method($filter['field'], 'ilike', "%{$filter['value']}%");
                            break;

                        case 'startsWith':
                            $q->$method($filter['field'], 'ilike', "{$filter['value']}%");
                            break;

                        case 'in':
                            $q->$method(function ($sub) use ($filter) {
                                $sub->whereIn($filter['field'], $filter['value']);
                            });
                            break;

                        case 'between':
                            $q->$method(function ($sub) use ($filter) {
                                $sub->whereBetween($filter['field'], $filter['value']);
                            });
                            break;
                    }
                }
            });
        }

        // 🔄 SORT
        if ($request->sorts) {
            foreach ($request->sorts as $sort) {
                $query->orderBy($sort['field'], $sort['dir']);
            }
        } else {
            $query->orderBy('enrollments.id', 'asc');
        }

        return $query->simplePaginate($request->page_size ?? 10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'    => 'nullable|exists:students,id',
            'nim'           => 'nullable|digits_between:8,12',
            'student_name'  => 'required_without:student_id',
            'email'         => 'required_without:student_id|email',

            'course_id'     => 'nullable|exists:courses,id',
            'course_code'   => 'required_without:course_id',
            'course_name'   => 'required_without:course_id',
            'credits'       => 'required_without:course_id|integer|min:1|max:6',

            'academic_year' => 'required',
            'semester'      => 'required|in:GANJIL,GENAP',
            'status'        => 'required|in:DRAFT,SUBMITTED,APPROVED,REJECTED',
        ]);

        try {
            $result = DB::transaction(function () use ($request) {

                if ($request->student_id) {
                    $studentId = $request->student_id;
                } else {
                    $student = Student::updateOrCreate(
                        ['nim' => $request->nim],
                        [
                            'name'  => $request->student_name,
                            'email' => $request->email,
                        ]
                    );
                    $studentId = $student->id;
                }

                if ($request->course_id) {
                    $courseId = $request->course_id;
                } else {
                    $course = Course::updateOrCreate(
                        ['code' => $request->course_code],
                        [
                            'name'    => $request->course_name,
                            'credits' => $request->credits,
                        ]
                    );
                    $courseId = $course->id;
                }

                Enrollment::create([
                    'student_id'    => $studentId,
                    'course_id'     => $courseId,
                    'academic_year' => $request->academic_year,
                    'semester'      => $request->semester,
                    'status'        => $request->status,
                ]);
            });

            return response()->json([
                'message' => 'Enrollment created successfully',
                'data'    => $result,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Transaction failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([

            // Student (optional update)
            'student_name'  => 'sometimes|min:3|max:100',
            'email'         => 'sometimes|email',

            // Course (optional update)
            'course_name'   => 'sometimes|min:3|max:120',
            'credits'       => 'sometimes|integer|min:1|max:6',

            // Enrollment
            'academic_year' => 'required|regex:/^\d{4}\/\d{4}$/',
            'semester'      => 'required|in:GANJIL,GENAP',
            'status'        => 'required|in:DRAFT,SUBMITTED,APPROVED,REJECTED',
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $id) {

                $enrollment = Enrollment::with(['student', 'course'])->findOrFail($id);

                // Update Enrollment
                $enrollment->update([
                    'academic_year' => $validated['academic_year'],
                    'semester'      => $validated['semester'],
                    'status'        => $validated['status'],
                ]);

                // Update student jika ada
                if (isset($validated['student_name']) || isset($validated['email'])) {
                    $enrollment->student->update([
                        'name'  => $validated['student_name'] ?? $enrollment->student->name,
                        'email' => $validated['email'] ?? $enrollment->student->email,
                    ]);
                }

                // Update course jika ada
                if (isset($validated['course_name']) || isset($validated['credits'])) {
                    $enrollment->course->update([
                        'name'    => $validated['course_name'] ?? $enrollment->course->name,
                        'credits' => $validated['credits'] ?? $enrollment->course->credits,
                    ]);
                }

                return $enrollment;
            });

            return response()->json([
                'message' => 'Enrollment updated successfully',
                'data'    => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Update failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {

            $enrollment = Enrollment::findOrFail($id);

            $enrollment->delete(); // soft delete

            return response()->json([
                'message' => 'Enrollment deleted successfully',
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Delete failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $fileName = 'enrollments.csv';

        return response()->stream(function () use ($request) {

            $handle = fopen('php://output', 'w');

            // Header CSV
            fputcsv($handle, [
                'NIM',
                'Nama Mahasiswa',
                'Kode MK',
                'Nama MK',
                'Tahun Ajaran',
                'Semester',
                'Status',
            ]);

            DB::disableQueryLog();

            Enrollment::query()
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->join('courses', 'courses.id', '=', 'enrollments.course_id')
                ->select(
                    'students.nim',
                    'students.name',
                    'courses.code',
                    'courses.name as course_name',
                    'enrollments.academic_year',
                    'enrollments.semester',
                    'enrollments.status'
                )
                ->orderBy('enrollments.id')
                ->chunk(5000, function ($rows) use ($handle) {

                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->nim,
                            $row->name,
                            $row->code,
                            $row->course_name,
                            $row->academic_year,
                            $row->semester,
                            $row->status,
                        ]);
                    }

                    flush();
                });

            fclose($handle);

        }, 200, [
            "Content-Type"        => "text/csv",
            "Content-Disposition" => "attachment; filename={$fileName}",
        ]);
    }

    public function stats()
    {
        DB::disableQueryLog();

        $total = Enrollment::count();

        $statuses = Enrollment::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'total'     => $total,
            'approved'  => $statuses['APPROVED'] ?? 0,
            'draft'     => $statuses['DRAFT'] ?? 0,
            'rejected'  => $statuses['REJECTED'] ?? 0,
            'submitted' => $statuses['SUBMITTED'] ?? 0,
        ]);
    }

}
