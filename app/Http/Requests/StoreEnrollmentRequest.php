<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'nim' => ['required_without:student_id', 'nullable', 'regex:/^\d{8,12}$/', 'unique:students,nim'],
            'student_name' => ['required_without:student_id', 'nullable', 'string', 'min:3', 'max:100'],
            'email' => ['required_without:student_id', 'nullable', 'email:rfc', 'max:255', 'unique:students,email'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'course_code' => ['required_without:course_id', 'nullable', 'regex:/^[A-Z]{2,4}[0-9]{3}$/', 'unique:courses,code'],
            'course_name' => ['required_without:course_id', 'nullable', 'string', 'min:3', 'max:120'],
            'credits' => ['required_without:course_id', 'nullable', 'integer', 'between:1,6'],
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:GANJIL,GENAP'],
            'status' => ['required', 'in:DRAFT,SUBMITTED,APPROVED,REJECTED'],
        ];
    }
}
