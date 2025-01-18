<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
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
            'category_id' => 'required|exists:categories,id',
            'business_id' => 'required|exists:businesses,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'buying_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'description' => 'required|string',
            'stock_quantity' => 'required|integer',
            'published' => 'required|in:true,false',
        ];

        // if ($this->method() == 'PUT' || $this->method() == 'PATCH') {
        //     $rules['slug'] = 'required|string|max:255|unique:products,slug,' . $this->route('id');
        // }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'business_id' => 'business',
        ];
    }
}
