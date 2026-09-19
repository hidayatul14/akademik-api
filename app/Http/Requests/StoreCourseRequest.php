<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'regex:/^[A-Z]{2,4}[0-9]{3}$/', 'unique:courses,code'],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'credits' => ['required', 'integer', 'min:1', 'max:6'],
        ];
    }
}
