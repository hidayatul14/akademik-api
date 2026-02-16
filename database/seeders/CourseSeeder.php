<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::disableQueryLog();

        $data = [];

        for ($i = 1; $i <= 500; $i++) {
            $data[] = [
                'code'       => 'IF' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'name'       => 'Course ' . $i,
                'credits'    => rand(1, 6),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('courses')->insert($data);
    }
}
