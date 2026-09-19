<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_validation_rejects_invalid_and_duplicate_data(): void
    {
        $this->postJson('/api/students', ['nim' => 'ABC', 'name' => 'A', 'email' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nim', 'name', 'email']);

        Student::create(['nim' => '12345678', 'name' => 'Existing Student', 'email' => 'existing@example.test']);
        $this->postJson('/api/students', ['nim' => '12345678', 'name' => 'Another Student', 'email' => 'existing@example.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nim', 'email']);
    }

    public function test_course_validation_enforces_code_name_and_credit_rules(): void
    {
        $this->postJson('/api/courses', ['code' => 'if1', 'name' => 'A', 'credits' => 9])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'credits']);

        $this->postJson('/api/courses', ['code' => 'IF101', 'name' => 'Algorithm Design', 'credits' => 3])
            ->assertCreated();
        $this->assertDatabaseHas('courses', ['code' => 'IF101']);
    }

    public function test_student_update_ignores_its_own_unique_values_but_rejects_another_student_values(): void
    {
        $student = Student::create(['nim' => '12345678', 'name' => 'First Student', 'email' => 'first@example.test']);
        Student::create(['nim' => '87654321', 'name' => 'Second Student', 'email' => 'second@example.test']);

        $this->putJson("/api/students/{$student->id}", [
            'nim' => '12345678',
            'name' => 'Updated Student',
            'email' => 'first@example.test',
        ])->assertOk();

        $this->putJson("/api/students/{$student->id}", [
            'nim' => '87654321',
            'name' => 'Updated Student',
            'email' => 'second@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['nim', 'email']);
    }

    public function test_course_update_ignores_its_own_code_but_rejects_another_course_code(): void
    {
        $course = Course::create(['code' => 'IF101', 'name' => 'First Course', 'credits' => 3]);
        Course::create(['code' => 'TI202', 'name' => 'Second Course', 'credits' => 2]);

        $this->putJson("/api/courses/{$course->id}", [
            'code' => 'IF101',
            'name' => 'Updated Course',
            'credits' => 4,
        ])->assertOk();

        $this->putJson("/api/courses/{$course->id}", [
            'code' => 'TI202',
            'name' => 'Updated Course',
            'credits' => 4,
        ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_student_and_course_search_are_server_side(): void
    {
        Student::create(['nim' => '87654321', 'name' => 'Searchable Student', 'email' => 'searchable@example.test']);
        Course::create(['code' => 'TI202', 'name' => 'Searchable Course', 'credits' => 3]);

        $this->getJson('/api/students?search=Searchable')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/courses?search=TI202')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_referenced_master_data_cannot_be_deleted(): void
    {
        $student = Student::create(['nim' => '11223344', 'name' => 'Referenced Student', 'email' => 'referenced@example.test']);
        $course = Course::create(['code' => 'IF505', 'name' => 'Referenced Course', 'credits' => 3]);
        Enrollment::create(['student_id' => $student->id, 'course_id' => $course->id, 'academic_year' => '2026/2027', 'semester' => 'GANJIL', 'status' => 'DRAFT']);

        $this->deleteJson("/api/students/{$student->id}")->assertConflict();
        $this->deleteJson("/api/courses/{$course->id}")->assertConflict();
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
        $this->assertDatabaseCount('enrollments', 1);
    }
}
