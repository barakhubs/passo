<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    private function successResponse($data = [], $message = '', $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    private function errorResponse($message, $code = 400)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], $code);
    }

    private function sanitizeUser($user)
    {
        return new UserResource($user);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users',
            'password' => [
                'required',
                'min:8',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&£#])[A-Za-z\d@$!%*?&£#]{8,}$/', $value)) {
                        $fail('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.');
                    }
                },
            ],
        ]);

        $user = User::create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $this->sanitizeUser($user),
            'token' => $token
        ], 'Registration successful', 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $this->sanitizeUser($user),
            'token' => $token
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return $this->successResponse([], 'Logout successful');
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return $this->errorResponse('Logout failed', 500);
        }
    }

    public function allUsers()
    {
        try {
            $users = User::all();
            if ($users->isEmpty()) {
                return $this->errorResponse('No users found', 404);
            }

            return $this->successResponse([
                'users' => $users->map(fn($user) => $this->sanitizeUser($user))
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return $this->errorResponse('Error fetching users', 500);
        }
    }

    public function updateProfile(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $user->update($request->all());

            return $this->successResponse([
                'user' => $this->sanitizeUser($user)
            ], 'Profile updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 404);
        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return $this->errorResponse('Error updating profile', 500);
        }
    }

    public function updatePassword(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validatedData = $request->validate([
                'old_password' => 'required',
                'new_password' => [
                    'required',
                    'min:8',
                    'different:old_password',
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&£#])[A-Za-z\d@$!%*?&£#]{8,}$/', $value)) {
                            $fail('New password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.');
                        }
                    },
                ],
            ]);

            if (!Hash::check($validatedData['old_password'], $user->password)) {
                return $this->errorResponse('Current password is incorrect', 401);
            }

            $user->password = Hash::make($validatedData['new_password']);
            $user->save();

            return $this->successResponse([], 'Password updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 404);
        } catch (\Exception $e) {
            Log::error('Password update error: ' . $e->getMessage());
            return $this->errorResponse('Error updating password', 500);
        }
    }

    public function deleteAccount(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'password' => 'required',
            ]);

            if (!Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            $user->tokens()->delete(); // Delete all tokens
            $user->delete();

            return $this->successResponse([], 'Account deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 404);
        } catch (\Exception $e) {
            Log::error('Account deletion error: ' . $e->getMessage());
            return $this->errorResponse('Error deleting account', 500);
        }
    }
}
