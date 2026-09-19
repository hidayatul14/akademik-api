<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Database\Seeders\EnrollmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_writes_student_course_and_enrollment_atomically(): void
    {
        $response = $this->postJson('/api/enrollments', [
            'nim' => '12345678',
            'student_name' => 'Siti Rahma',
            'email' => 'siti@example.test',
            'course_code' => 'IF101',
            'course_name' => 'Algoritma Dasar',
            'credits' => 3,
            'academic_year' => '2026/2027',
            'semester' => 'GANJIL',
            'status' => 'DRAFT',
        ]);

        $response->assertCreated()->assertJsonPath('data.student.nim', '12345678');
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_invalid_create_does_not_write_partial_data(): void
    {
        $this->postJson('/api/enrollments', [
            'nim' => 'ABC',
            'student_name' => 'A',
            'email' => 'invalid',
            'course_code' => 'invalid',
            'course_name' => 'X',
            'credits' => 9,
            'academic_year' => '2026',
            'semester' => 'INVALID',
            'status' => 'INVALID',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_list_rejects_unsupported_sort_fields(): void
    {
        $response = $this->call('GET', '/api/enrollments', [
            'sorts' => [['field' => 'users.password', 'dir' => 'asc']],
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertUnprocessable()->assertJsonValidationErrors('sorts.0.field');
    }

    public function test_search_and_filters_work_with_safe_field_aliases(): void
    {
        $student = Student::create(['nim' => '87654321', 'name' => 'Budi Santoso', 'email' => 'budi@example.test']);
        $course = Course::create(['code' => 'TI202', 'name' => 'Basis Data', 'credits' => 3]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_year' => '2026/2027',
            'semester' => 'GENAP',
            'status' => 'APPROVED',
        ]);

        $response = $this->call('GET', '/api/enrollments', [
            'search' => 'Budi',
            'filters' => [['field' => 'status', 'operator' => 'equal', 'value' => 'APPROVED']],
            'sorts' => [['field' => 'student_nim', 'dir' => 'asc']],
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nim', '87654321');
    }

    public function test_between_filter_requires_exactly_two_values(): void
    {
        $response = $this->call('GET', '/api/enrollments', [
            'filters' => [['field' => 'academic_year', 'operator' => 'between', 'value' => ['2026/2027']]],
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertUnprocessable()->assertJsonValidationErrors('filters.0.value');
    }

    public function test_export_applies_the_same_search_and_filters_as_the_list(): void
    {
        $approvedStudent = Student::create(['nim' => '11111111', 'name' => 'Approved Student', 'email' => 'approved@example.test']);
        $draftStudent = Student::create(['nim' => '22222222', 'name' => 'Draft Student', 'email' => 'draft@example.test']);
        $course = Course::create(['code' => 'IF303', 'name' => 'Web Engineering', 'credits' => 3]);

        Enrollment::create(['student_id' => $approvedStudent->id, 'course_id' => $course->id, 'academic_year' => '2026/2027', 'semester' => 'GANJIL', 'status' => 'APPROVED']);
        Enrollment::create(['student_id' => $draftStudent->id, 'course_id' => $course->id, 'academic_year' => '2026/2027', 'semester' => 'GENAP', 'status' => 'DRAFT']);

        $response = $this->call('GET', '/api/enrollments/export', [
            'search' => 'Approved',
            'filters' => [['field' => 'status', 'operator' => 'equal', 'value' => 'APPROVED']],
        ], [], [], ['HTTP_ACCEPT' => 'text/csv']);

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('11111111', $content);
        $this->assertStringNotContainsString('22222222', $content);
    }

    public function test_duplicate_enrollment_is_rejected_without_creating_another_row(): void
    {
        $student = Student::create(['nim' => '33333333', 'name' => 'Duplicate Student', 'email' => 'duplicate@example.test']);
        $course = Course::create(['code' => 'IF404', 'name' => 'Distributed Systems', 'credits' => 4]);
        $payload = ['student_id' => $student->id, 'course_id' => $course->id, 'academic_year' => '2026/2027', 'semester' => 'GANJIL', 'status' => 'DRAFT'];

        $this->postJson('/api/enrollments', $payload)->assertCreated();
        $this->postJson('/api/enrollments', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_update_and_soft_delete_preserve_related_master_data(): void
    {
        $student = Student::create(['nim' => '44444444', 'name' => 'Update Student', 'email' => 'update@example.test']);
        $course = Course::create(['code' => 'IF405', 'name' => 'Software Quality', 'credits' => 3]);
        $enrollment = Enrollment::create(['student_id' => $student->id, 'course_id' => $course->id, 'academic_year' => '2026/2027', 'semester' => 'GANJIL', 'status' => 'DRAFT']);

        $this->putJson("/api/enrollments/{$enrollment->id}", ['academic_year' => '2026/2027', 'semester' => 'GENAP', 'status' => 'APPROVED'])
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->deleteJson("/api/enrollments/{$enrollment->id}")->assertOk();
        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_enrollment_seeder_uses_configurable_batches_and_real_ids(): void
    {
        $students = collect(range(1, 4))->map(fn (int $number) => Student::create([
            'nim' => str_pad((string) (5_000_000 + $number), 8, '0', STR_PAD_LEFT),
            'name' => "Seeder Student {$number}",
            'email' => "seeder{$number}@example.test",
        ]));
        $courses = collect(range(1, 3))->map(fn (int $number) => Course::create([
            'code' => 'TS'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'name' => "Seeder Course {$number}",
            'credits' => 3,
        ]));

        config(['academic.enrollment_seed_count' => 20, 'academic.enrollment_seed_chunk' => 100]);
        $this->seed(EnrollmentSeeder::class);

        $this->assertDatabaseCount('enrollments', 20);
        $this->assertContains(Enrollment::firstOrFail()->student_id, $students->pluck('id'));
        $this->assertContains(Enrollment::firstOrFail()->course_id, $courses->pluck('id'));
        $this->assertSame(20, Enrollment::query()->select('student_id', 'course_id', 'academic_year', 'semester')->distinct()->count());
    }
}
