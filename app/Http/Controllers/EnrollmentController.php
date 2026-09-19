<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexEnrollmentRequest;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class EnrollmentController extends Controller
{
    private const FIELD_MAP = [
        'student_nim' => 'students.nim',
        'student_name' => 'students.name',
        'student_email' => 'students.email',
        'course_code' => 'courses.code',
        'course_name' => 'courses.name',
        'credits' => 'courses.credits',
        'academic_year' => 'enrollments.academic_year',
        'semester' => 'enrollments.semester',
        'status' => 'enrollments.status',
        'students.nim' => 'students.nim',
        'students.name' => 'students.name',
        'students.email' => 'students.email',
        'courses.code' => 'courses.code',
        'courses.name' => 'courses.name',
        'courses.credits' => 'courses.credits',
        'enrollments.academic_year' => 'enrollments.academic_year',
        'enrollments.semester' => 'enrollments.semester',
        'enrollments.status' => 'enrollments.status',
    ];

    public function index(IndexEnrollmentRequest $request)
    {
        $validated = $request->validated();
        $countQuery = $this->countNeedsJoins($validated) ? $this->baseQuery() : Enrollment::query();
        $this->applyQueryConstraints($countQuery, $validated);

        $pageNeedsJoins = $this->pageNeedsJoins($validated);
        $pageQuery = $pageNeedsJoins ? $this->baseQuery() : Enrollment::query()->select('enrollments.id');
        $this->applyQueryConstraints($pageQuery, $validated);

        if (! empty($validated['sorts'])) {
            foreach ($validated['sorts'] as $sort) {
                $pageQuery->orderBy(self::FIELD_MAP[$sort['field']], strtolower($sort['dir']));
            }
        } else {
            $pageQuery->orderBy('enrollments.id');
        }

        $page = $pageQuery->paginate((int) ($validated['page_size'] ?? 25), ['*'], 'page', null, $countQuery->count('enrollments.id'));

        if (! $pageNeedsJoins && $page->getCollection()->isNotEmpty()) {
            $rows = $this->baseQuery()->whereIn('enrollments.id', $page->getCollection()->pluck('id'))->get()->keyBy('id');
            $page->setCollection($page->getCollection()->map(fn (Enrollment $enrollment) => $rows->get($enrollment->id)));
        }

        return $page;
    }

    public function store(StoreEnrollmentRequest $request)
    {
        $validated = $request->validated();

        try {
            $enrollment = DB::transaction(function () use ($validated) {
                $studentId = $validated['student_id'] ?? null;
                if (! $studentId) {
                    $studentId = Student::create([
                        'nim' => $validated['nim'],
                        'name' => $validated['student_name'],
                        'email' => $validated['email'],
                    ])->id;
                }

                $courseId = $validated['course_id'] ?? null;
                if (! $courseId) {
                    $courseId = Course::create([
                        'code' => $validated['course_code'],
                        'name' => $validated['course_name'],
                        'credits' => $validated['credits'],
                    ])->id;
                }

                return Enrollment::create([
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'academic_year' => $validated['academic_year'],
                    'semester' => $validated['semester'],
                    'status' => $validated['status'],
                ])->load(['student', 'course']);
            });

            return response()->json([
                'message' => 'Data KRS berhasil dibuat.',
                'data' => $enrollment,
            ], 201);
        } catch (QueryException $exception) {
            report($exception);

            if ($this->isIntegrityViolation($exception)) {
                return response()->json([
                    'message' => 'Data KRS, mahasiswa, atau mata kuliah tersebut sudah ada.',
                ], 422);
            }

            return response()->json(['message' => 'Data KRS gagal dibuat. Silakan coba lagi.'], 500);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Data KRS gagal dibuat. Silakan coba lagi.'], 500);
        }
    }

    public function update(UpdateEnrollmentRequest $request, $id)
    {
        $validated = $request->validated();
        $enrollment = Enrollment::with(['student', 'course'])->findOrFail($id);

        try {
            $result = DB::transaction(function () use ($validated, $enrollment) {
                $enrollment->update([
                    'academic_year' => $validated['academic_year'],
                    'semester' => $validated['semester'],
                    'status' => $validated['status'],
                ]);

                if (isset($validated['student_name']) || isset($validated['email'])) {
                    $enrollment->student->update([
                        'name' => $validated['student_name'] ?? $enrollment->student->name,
                        'email' => $validated['email'] ?? $enrollment->student->email,
                    ]);
                }

                if (isset($validated['course_name']) || isset($validated['credits'])) {
                    $enrollment->course->update([
                        'name' => $validated['course_name'] ?? $enrollment->course->name,
                        'credits' => $validated['credits'] ?? $enrollment->course->credits,
                    ]);
                }

                return $enrollment->refresh()->load(['student', 'course']);
            });

            return response()->json([
                'message' => 'Data KRS berhasil diperbarui.',
                'data' => $result,
            ]);
        } catch (QueryException $exception) {
            report($exception);

            if ($this->isIntegrityViolation($exception)) {
                return response()->json(['message' => 'Perubahan tersebut bertentangan dengan data yang sudah ada.'], 422);
            }

            return response()->json(['message' => 'Data KRS gagal diperbarui. Silakan coba lagi.'], 500);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Data KRS gagal diperbarui. Silakan coba lagi.'], 500);
        }
    }

    public function destroy($id)
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->delete();

        return response()->json(['message' => 'Data KRS berhasil dihapus.']);
    }

    public function export(IndexEnrollmentRequest $request)
    {
        $validated = $request->validated();

        return response()->stream(function () use ($validated) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['NIM', 'Nama Mahasiswa', 'Kode MK', 'Nama MK', 'Tahun Ajaran', 'Semester', 'Status']);
            DB::disableQueryLog();

            $query = $this->baseQuery();
            $this->applyQueryConstraints($query, $validated);

            $processed = 0;
            foreach ($query->lazyById(5000, 'enrollments.id', 'id') as $row) {
                fputcsv($handle, [
                    $this->csvValue($row->nim),
                    $this->csvValue($row->student_name),
                    $this->csvValue($row->course_code),
                    $this->csvValue($row->course_name),
                    $this->csvValue($row->academic_year),
                    $this->csvValue($row->semester),
                    $this->csvValue($row->status),
                ]);

                if (++$processed % 5000 === 0) {
                    flush();
                }
            }

            fclose($handle);
        }, 200, [
            'Cache-Control' => 'no-store, no-cache',
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=enrollments.csv',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function stats()
    {
        DB::disableQueryLog();
        $statuses = Enrollment::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'total' => $statuses->sum(),
            'approved' => (int) ($statuses['APPROVED'] ?? 0),
            'draft' => (int) ($statuses['DRAFT'] ?? 0),
            'rejected' => (int) ($statuses['REJECTED'] ?? 0),
            'submitted' => (int) ($statuses['SUBMITTED'] ?? 0),
        ]);
    }

    private function baseQuery(): Builder
    {
        return Enrollment::query()
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
    }

    private function applyFilters(Builder $query, array $filters, string $logic): void
    {
        $query->where(function (Builder $group) use ($filters, $logic) {
            foreach ($filters as $filter) {
                $column = self::FIELD_MAP[$filter['field']];
                $or = $logic === 'OR';

                match ($filter['operator']) {
                    'equal' => $or ? $group->orWhere($column, $filter['value']) : $group->where($column, $filter['value']),
                    'contains' => $or ? $group->orWhereLike($column, '%'.$filter['value'].'%') : $group->whereLike($column, '%'.$filter['value'].'%'),
                    'startsWith' => $or ? $group->orWhereLike($column, $filter['value'].'%') : $group->whereLike($column, $filter['value'].'%'),
                    'in' => $or ? $group->orWhereIn($column, (array) $filter['value']) : $group->whereIn($column, (array) $filter['value']),
                    'between' => $or ? $group->orWhereBetween($column, (array) $filter['value']) : $group->whereBetween($column, (array) $filter['value']),
                };
            }
        });
    }

    private function applyQueryConstraints(Builder $query, array $validated): void
    {
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($group) use ($search) {
                $group->whereLike('students.nim', "%{$search}%")
                    ->orWhereLike('students.name', "%{$search}%")
                    ->orWhereLike('courses.code', "%{$search}%");
            });
        }

        if (! empty($validated['filters'])) {
            $this->applyFilters($query, $validated['filters'], strtoupper($validated['logic'] ?? 'AND'));
        }
    }

    private function countNeedsJoins(array $validated): bool
    {
        if (! empty($validated['search'])) {
            return true;
        }

        foreach ($validated['filters'] ?? [] as $filter) {
            if (! str_starts_with(self::FIELD_MAP[$filter['field']], 'enrollments.')) {
                return true;
            }
        }

        return false;
    }

    private function pageNeedsJoins(array $validated): bool
    {
        if ($this->countNeedsJoins($validated)) {
            return true;
        }

        foreach ($validated['sorts'] ?? [] as $sort) {
            if (! str_starts_with(self::FIELD_MAP[$sort['field']], 'enrollments.')) {
                return true;
            }
        }

        return false;
    }

    private function isIntegrityViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }

    private function csvValue(mixed $value): string
    {
        $string = (string) $value;

        return preg_match('/^[=+\-@]/', $string) === 1 ? "'{$string}" : $string;
    }
}
