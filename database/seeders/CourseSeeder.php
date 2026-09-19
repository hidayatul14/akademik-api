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
        $timestamp = now();

        for ($i = 1; $i <= 500; $i++) {
            $data[] = [
                'code' => 'IF'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'name' => 'Course '.$i,
                'credits' => (($i - 1) % 6) + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('courses')->insertOrIgnore($data);
    }
}
