<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Sale Header
            'business_id' => 'required|exists:businesses,id',
            'customer_id' => 'required|exists:customers,id',
            'payment_status' => [Rule::in(['paid', 'unpain'])],
            'total_amount' => 'nullable|string',

            // Sale Items (Array of items)
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0.01',
            'items.*.total' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required for the sale',
            'items.*.product_id.required' => 'Product is required for each item',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'items.*.unit_price.min' => 'Unit price must be greater than 0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit price',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if products are in stock
            foreach ($this->items as $index => $item) {
                $product = \App\Models\Product::find($item['product_id']);
                if ($product && $product->stock_quantity < $item['quantity']) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        "Insufficient stock for product {$product->name}. Available: {$product->stock}"
                    );
                }
            }

            // Add any other custom validation logic
        });
    }
}
