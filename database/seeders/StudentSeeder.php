<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::disableQueryLog();

        $data = [];

        for ($i = 1; $i <= 10000; $i++) {
            $data[] = [
                'nim'   => str_pad($i, 8, '0', STR_PAD_LEFT),
                'name'  => 'Student ' . $i,
                'email' => "student{$i}@test.com",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('students')->insert($data);
    }
}
