<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'address' => 'nullable|string',
            'country' => 'nullable|string',
            'website' => 'nullable|string',
            'tagline' => 'nullable|string',
        ];

        // if ($this->method() == 'PUT' || $this->method() == 'PATCH') {
        //     $rules['slug'] = 'required|string|max:255|unique:businesss,slug,' . $this->route('id');
        // }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'user',
        ];
    }
}
