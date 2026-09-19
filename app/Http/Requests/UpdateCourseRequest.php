<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $courseId = $this->route('course');

        return [
            'code' => ['required', 'regex:/^[A-Z]{2,4}[0-9]{3}$/', Rule::unique('courses', 'code')->ignore($courseId)],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'credits' => ['required', 'integer', 'min:1', 'max:6'],
        ];
    }
}
