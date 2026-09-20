<?php

namespace App\Console\Commands;

use Database\Seeders\CourseSeeder;
use Database\Seeders\EnrollmentSeeder;
use Database\Seeders\StudentSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedAcademicData extends Command
{
    protected $signature = 'academic:seed
        {count=5000000 : Number of enrollment records to generate}
        {--chunk=5000 : Rows inserted per database statement}
        {--if-empty : Skip seeding when enrollment data already exists}';

    protected $description = 'Seed deterministic student, course, and enrollment data in scalable batches';

    public function handle(): int
    {
        $count = filter_var($this->argument('count'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 100, 'max_range' => 10_000]]);

        if ($count === false || $chunk === false) {
            $this->error('Count must be positive and chunk must be between 100 and 10,000.');

            return self::FAILURE;
        }

        if ($this->option('if-empty') && DB::table('enrollments')->exists()) {
            $this->components->info('Enrollment data already exists; skipping demo seed.');

            return self::SUCCESS;
        }

        config([
            'academic.enrollment_seed_count' => $count,
            'academic.enrollment_seed_chunk' => $chunk,
        ]);

        $this->components->info('Preparing academic master data...');
        $this->call('db:seed', ['--class' => StudentSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => CourseSeeder::class, '--force' => true]);
        $this->components->info("Generating {$count} enrollment records in batches of {$chunk}...");
        $exitCode = $this->call('db:seed', ['--class' => EnrollmentSeeder::class, '--force' => true]);

        if ($exitCode === self::SUCCESS) {
            $this->components->info('Academic dataset generated successfully.');
        }

        return $exitCode;
    }
}
