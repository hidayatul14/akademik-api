<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['deleted_at', 'status', 'semester'], 'enrollments_active_status_semester_index');
            $table->index(['deleted_at', 'semester'], 'enrollments_active_semester_index');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('deleted_at', 'enrollments_deleted_at_index');
            $table->dropIndex('enrollments_active_status_semester_index');
            $table->dropIndex('enrollments_active_semester_index');
        });
    }
};
