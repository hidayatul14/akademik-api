<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EnrollmentController;

Route::post('/enrollments', [EnrollmentController::class, 'store']);
Route::get('/enrollments', [EnrollmentController::class, 'index']);
Route::get('/enrollments/export', [EnrollmentController::class, 'export']);
Route::put('/enrollments/{id}', [EnrollmentController::class, 'update']);
Route::delete('/enrollments/{id}', [EnrollmentController::class, 'destroy']);
