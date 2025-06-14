<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = Auth::user()->id;

        return [
            'email' => "sometimes|email|unique:users,email,{$userId}",
            'password' => 'sometimes|min:6',
            'first_name' => 'sometimes|string',
            'last_name' => 'sometimes|string',
            'phone' => "sometimes|unique:users,phone,{$userId}",
        ];
    }
}
