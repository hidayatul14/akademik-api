<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        DB::disableQueryLog();

        $total     = 5000000;
        $chunkSize = 5000;
        $statuses  = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'];

        $inserted = 0;

        while ($inserted < $total) {

            $data = [];

            for ($i = 0; $i < $chunkSize && $inserted < $total; $i++) {

                $studentId = ($inserted % 10000) + 1;
                $courseId  = (int) (($inserted / 10000) % 500) + 1;
                $semester  = ($inserted % 2) ? 'GANJIL' : 'GENAP';

                $data[] = [
                    'student_id'    => $studentId,
                    'course_id'     => $courseId,
                    'academic_year' => '2025/2026',
                    'semester'      => $semester,
                    'status'        => $statuses[array_rand($statuses)],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];

                $inserted++;
            }

            DB::table('enrollments')->insert($data);

            echo "Inserted: {$inserted}\n";
        }
    }
}
