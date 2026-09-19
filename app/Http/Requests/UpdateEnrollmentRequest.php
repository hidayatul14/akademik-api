<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'email' => ['sometimes', 'email:rfc', 'max:255'],
            'course_name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'credits' => ['sometimes', 'integer', 'between:1,6'],
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:GANJIL,GENAP'],
            'status' => ['required', 'in:DRAFT,SUBMITTED,APPROVED,REJECTED'],
        ];
    }
}
