<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('course_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('academic_year');
            $table->enum('semester', ['GANJIL', 'GENAP']);
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED']);

            $table->timestamps();

            $table->unique(['student_id', 'course_id', 'academic_year', 'semester']);

            // INDEX untuk performa 5 juta data
            $table->index(['status', 'semester']);
            $table->index(['academic_year', 'semester']);
            $table->index(['student_id']);
            $table->index(['course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
