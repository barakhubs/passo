<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    // public function authorize(): bool
    // {
    //     return false;
    // }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'business_id' => 'sometimes|exists:businesses,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        // if ($this->method() == 'PUT' || $this->method() == 'PATCH') {
        //     $rules['slug'] = 'required|string|max:255|unique:categories,slug,' . $this->route('id');
        // }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'parent_id' => 'parent category',
            'business_id' => 'business',
        ];
    }
}
