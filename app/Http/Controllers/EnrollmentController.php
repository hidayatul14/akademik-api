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
                'courses.code as course_code',
                'courses.name as course_name',
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
        $sortBy  = $request->sort_by ?? 'enrollments.id';
        $sortDir = $request->sort_dir ?? 'asc';

        $query->orderBy($sortBy, $sortDir);

        return $query->simplePaginate($request->page_size ?? 10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([

            // STUDENT
            'nim'           => 'required|digits_between:8,12|unique:students,nim',
            'student_name'  => 'required|min:3|max:100',
            'email'         => 'required|email|unique:students,email',

            // COURSE
            'course_code'   => ['required', 'regex:/^[A-Z]{2,4}[0-9]{3}$/', 'unique:courses,code'],
            'course_name'   => 'required|min:3|max:120',
            'credits'       => 'required|integer|min:1|max:6',

            // ENROLLMENT
            'academic_year' => 'required|regex:/^\d{4}\/\d{4}$/',
            'semester'      => 'required|in:GANJIL,GENAP',
            'status'        => 'required|in:DRAFT,SUBMITTED,APPROVED,REJECTED',
        ]);

        try {

            $result = DB::transaction(function () use ($validated) {

                $student = Student::create([
                    'nim'   => $validated['nim'],
                    'name'  => $validated['student_name'],
                    'email' => $validated['email'],
                ]);

                $course = Course::create([
                    'code'    => $validated['course_code'],
                    'name'    => $validated['course_name'],
                    'credits' => $validated['credits'],
                ]);

                $enrollment = Enrollment::create([
                    'student_id'    => $student->id,
                    'course_id'     => $course->id,
                    'academic_year' => $validated['academic_year'],
                    'semester'      => $validated['semester'],
                    'status'        => $validated['status'],
                ]);

                return $enrollment;
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
}
