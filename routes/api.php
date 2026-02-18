<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::post('/enrollments', [EnrollmentController::class, 'store']);
Route::get('/enrollments', [EnrollmentController::class, 'index']);
Route::get('/enrollments/export', [EnrollmentController::class, 'export']);
Route::put('/enrollments/{id}', [EnrollmentController::class, 'update']);
Route::delete('/enrollments/{id}', [EnrollmentController::class, 'destroy']);
Route::get('/enrollments/stats', [EnrollmentController::class, 'stats']);

Route::get('/students/search', [StudentController::class, 'search']);
Route::apiResource('students', StudentController::class);

Route::get('/courses/search', [CourseController::class, 'search']);
Route::apiResource('courses', CourseController::class);

Route::get('/test-db', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            "status" => "success",
            "message" => "Database connected!"
        ]);
    } catch (\Exception $e) {
        return response()->json([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
});
