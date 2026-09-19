<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::disableQueryLog();

        if (DB::table('enrollments')->exists()) {
            throw new RuntimeException('The enrollments table is not empty. Use a fresh database before generating the benchmark dataset.');
        }

        $studentIds = DB::table('students')->orderBy('id')->pluck('id')->all();
        $courseIds = DB::table('courses')->orderBy('id')->pluck('id')->all();
        if ($studentIds === [] || $courseIds === []) {
            throw new RuntimeException('Students and courses must be seeded before enrollments.');
        }

        $total = max(1, (int) config('academic.enrollment_seed_count', 5_000_000));
        $chunkSize = min(10_000, max(100, (int) config('academic.enrollment_seed_chunk', 5_000)));
        $academicYear = (string) config('academic.seed_academic_year', '2026/2027');
        $statuses = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'];
        $studentCount = count($studentIds);
        $courseCount = count($courseIds);
        $combinationsPerSemester = $studentCount * $courseCount;
        $maximumForYear = $combinationsPerSemester * 2;

        if ($total > $maximumForYear) {
            throw new RuntimeException("Requested {$total} rows, but only {$maximumForYear} unique combinations are available for {$academicYear}.");
        }

        $timestamp = now();
        for ($offset = 0; $offset < $total; $offset += $chunkSize) {
            $batchEnd = min($offset + $chunkSize, $total);
            $rows = [];

            for ($index = $offset; $index < $batchEnd; $index++) {
                $studentIndex = $index % $studentCount;
                $courseIndex = intdiv($index, $studentCount) % $courseCount;
                $semester = intdiv($index, $combinationsPerSemester) % 2 === 0 ? 'GANJIL' : 'GENAP';

                $rows[] = [
                    'student_id' => $studentIds[$studentIndex],
                    'course_id' => $courseIds[$courseIndex],
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'status' => $statuses[$index % count($statuses)],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            DB::table('enrollments')->insert($rows);
            $inserted = $batchEnd;
            if ($inserted === $total || $inserted % 100_000 === 0) {
                $this->command?->info("Inserted {$inserted} / {$total} enrollments");
            }
        }
    }
}
