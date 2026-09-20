<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fields = [
            'student_nim', 'student_name', 'student_email', 'course_code', 'course_name', 'credits',
            'academic_year', 'semester', 'status', 'students.nim', 'students.name', 'students.email',
            'courses.code', 'courses.name', 'courses.credits', 'enrollments.academic_year',
            'enrollments.semester', 'enrollments.status',
        ];

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quick_status' => ['sometimes', 'nullable', Rule::in(['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'])],
            'quick_semester' => ['sometimes', 'nullable', Rule::in(['GANJIL', 'GENAP'])],
            'logic' => ['sometimes', Rule::in(['AND', 'OR', 'and', 'or'])],
            'sorts' => ['sometimes', 'array', 'max:9'],
            'sorts.*.field' => ['required', 'string', Rule::in($fields)],
            'sorts.*.dir' => ['required', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
            'filters' => ['sometimes', 'array', 'max:20'],
            'filters.*.field' => ['required', 'string', Rule::in($fields)],
            'filters.*.operator' => ['required', Rule::in(['equal', 'contains', 'startsWith', 'in', 'between'])],
            'filters.*.value' => ['present'],
        ];
    }

    public function messages(): array
    {
        return [
            'sorts.*.field.in' => 'Kolom pengurutan tidak didukung.',
            'sorts.*.dir.in' => 'Arah pengurutan harus naik atau turun.',
            'filters.*.field.in' => 'Kolom filter tidak didukung.',
            'filters.*.operator.in' => 'Operator filter tidak didukung.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ($this->input('filters', []) as $index => $filter) {
                    $operator = $filter['operator'] ?? null;
                    $value = $filter['value'] ?? null;

                    if ($operator === 'in' && (! is_array($value) || $value === [])) {
                        $validator->errors()->add("filters.{$index}.value", 'Operator ini memerlukan daftar nilai yang tidak kosong.');
                    }

                    if ($operator === 'between' && (! is_array($value) || count($value) !== 2)) {
                        $validator->errors()->add("filters.{$index}.value", 'Operator di antara memerlukan tepat dua nilai.');
                    }

                    if (in_array($operator, ['equal', 'contains', 'startsWith'], true) && ! is_scalar($value)) {
                        $validator->errors()->add("filters.{$index}.value", 'Operator ini memerlukan satu nilai.');
                    }
                }
            },
        ];
    }
}
