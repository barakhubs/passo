<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\NLQueryService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const PASSWORD_REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s])/u';

    private $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    // ========== Helper Methods ==========
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

    /**
     * Clean up incomplete registrations older than 24 hours
     * Call this periodically or before new registrations
     */
    private function cleanupIncompleteRegistrations()
    {
        try {
            User::where('password', null)
                ->where('status', 'inactive')
                ->where('created_at', '<', now()->subHours(24))
                ->delete();
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup incomplete registrations: ' . $e->getMessage());
        }
    }

    // ========== Authentication Methods ==========
    public function registerStepOne(Request $request)
    {
        // Clean up old incomplete registrations
        $this->cleanupIncompleteRegistrations();

        $data = $request->validate([
            'country_code' => 'required',
            'phone' => 'required|min:9|max:10',
        ]);

        // Check if user already exists
        $existingUser = User::where([
            'country_code' => $data['country_code'],
            'phone' => $data['phone']
        ])->first();

        if ($existingUser) {
            // If user has a password set, they should use password reset instead
            if (!is_null($existingUser->password)) {
                return $this->errorResponse(
                    'This phone number is already registered. Please use "Forgot Password" to reset your password instead of creating a new account.',
                    409
                );
            }

            // If user exists but has no password, allow them to continue registration
            // This handles incomplete registrations
            $phone = $data['country_code'] . $data['phone'];
            $this->otpService->generateOtp($phone);

            return $this->successResponse([], 'OTP sent to ' . $phone . ' successfully. Continuing previous registration.', 201);
        }

        // Create new user for first-time registration
        User::create([
            'phone' => $data['phone'],
            'country_code' => $data['country_code'],
        ]);

        $phone = $data['country_code'] . $data['phone'];
        $this->otpService->generateOtp($phone);

        return $this->successResponse([], 'OTP sent to ' . $phone . ' successfully', 201);
    }

    // resendOtp
    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'country_code' => 'required',
            'phone' => 'required|min:9|max:9',
        ]);

        $phone = $data['country_code'] . $data['phone'];
        $this->otpService->resendOtp(phone: $phone);
        return $this->successResponse([], 'OTP sent to ' . $phone . ' successfully', 201);
    }

    public function verifyOtp(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|digits:4',
            'phone' => 'required|min:9|max:9',
            'country_code' => 'required|min:3|max:3'
        ]);

        $isOtpVerified = $this->otpService->isOtpVerified(
            $validatedData['code'],
            $validatedData['country_code'],
            $validatedData['phone']
        );

        if (!$isOtpVerified) {
            return $this->errorResponse('Incorrect OTP entered', 401);
        }

        return $this->successResponse(['verified' => true], 'OTP verified successfully');
    }

    public function registerStepTwo(Request $request)
    {
        $validatedData = $request->validate([
            'phone' => 'required|min:9|max:10',
            'country_code' => 'required',
            'password' => [
                'required',
                'min:8',
                function ($attribute, $value, $fail) {
                    if (!preg_match(self::PASSWORD_REGEX, $value)) {
                        $fail('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.');
                    }
                },
            ],
        ]);

        $user = User::where([
            'phone' => $validatedData['phone'],
            'country_code' => $validatedData['country_code']
        ])->first();

        if (!$user) {
            return $this->errorResponse('User not found. Please start registration from step one.', 404);
        }

        // Check if user already has a password set (complete registration)
        if (!is_null($user->password)) {
            return $this->errorResponse(
                'This phone number is already registered with a password. Please use "Forgot Password" to reset your password or login directly.',
                409
            );
        }

        if ($user->status === 'active' || $user->status === 'suspended') {
            return $this->errorResponse('An error occurred. Please try again', 401);
        }

        $user->password = Hash::make($validatedData['password']);
        $user->status = 'active';
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $this->sanitizeUser($user),
            'token' => $token
        ], 'Registration successful', 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => 'required|min:9|max:9',
            'country_code' => 'required|min:3|max:3',
            'password' => 'required|min:8',
        ]);

        $user = User::where('country_code', $credentials['country_code'])
            ->where('phone', $credentials['phone'])
            ->first();

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
            $request->user()->tokens()->delete();
            return $this->successResponse([], 'Logout successful');
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return $this->errorResponse('Logout failed', 500);
        }
    }

    // ========== User Profile Methods ==========
    public function updateProfile(UpdateProfileRequest $request)
    {
        try {
            $user = Auth::user();
            $data = $request->validated();
            $user->update($data);

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

    public function updatePassword(Request $request)
    {
        try {
            $user = $request->user();

            $validatedData = $request->validate([
                'old_password' => 'required',
                'new_password' => [
                    'required',
                    'min:8',
                    'different:old_password',
                    function ($attribute, $value, $fail) {
                        if (!preg_match(self::PASSWORD_REGEX, $value)) {
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
        } catch (\Exception $e) {
            Log::error('Password update error: ' . $e->getMessage());
            return $this->errorResponse('Error updating password', 500);
        }
    }

    // ========== User Management Methods ==========
    public function allUsers(Request $request)
    {
        try {
            // Check if user explicitly wants all users (non-paginated)
            if ($request->has('all') && $request->get('all') === 'true') {
                return $this->getAllUsers();
            }
            
            // Default to pagination
            [$perPage, $page] = $this->getPaginationParams($request);
            
            $paginatedUsers = User::orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            if ($paginatedUsers->total() === 0) {
                return response()->json([
                    'message' => 'No users found',
                    'data' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'has_more_pages' => false,
                        'from' => null,
                        'to' => null
                    ]
                ], 200);
            }

            return response()->json([
                'message' => 'Users retrieved successfully',
                'data' => collect($paginatedUsers->items())->map(fn($user) => $this->sanitizeUser($user)),
                'pagination' => [
                    'current_page' => $paginatedUsers->currentPage(),
                    'per_page' => $paginatedUsers->perPage(),
                    'total' => $paginatedUsers->total(),
                    'last_page' => $paginatedUsers->lastPage(),
                    'has_more_pages' => $paginatedUsers->hasMorePages(),
                    'from' => $paginatedUsers->firstItem(),
                    'to' => $paginatedUsers->lastItem(),
                    'next_page_url' => $paginatedUsers->nextPageUrl(),
                    'prev_page_url' => $paginatedUsers->previousPageUrl()
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return $this->errorResponse('Error fetching users', 500);
        }
    }

    public function getAllUsers()
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

    public function deleteAccount(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'password' => 'required',
            ]);

            if (!Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            $user->tokens()->delete();
            $user->delete();

            return $this->successResponse([], 'Account deleted successfully');
        } catch (\Exception $e) {
            Log::error('Account deletion error: ' . $e->getMessage());
            return $this->errorResponse('Error deleting account', 500);
        }
    }

    // ========== Password Reset Methods ==========
    public function forgotPassword(Request $request)
    {
        $validatedData = $request->validate([
            'phone' => 'required|min:9|max:9',
            'country_code' => 'required|min:3|max:3'
        ]);

        $user = User::where([
            'phone' => $validatedData['phone'],
            'country_code' => $validatedData['country_code']
        ])->first();

        if (!$user) {
            return $this->errorResponse('No account found with this phone number. Please register first.', 404);
        }

        // Check if user has completed registration (has password)
        if (is_null($user->password)) {
            return $this->errorResponse(
                'Your registration is incomplete. Please complete your registration instead of resetting password.',
                400
            );
        }

        // Generate and send OTP
        $phone = $validatedData['country_code'] . $validatedData['phone'];
        $this->otpService->generateOtp($phone);

        return $this->successResponse([], 'OTP sent successfully for password reset');
    }

    public function verifyPasswordResetOtp(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|digits:4',
            'phone' => 'required|min:9|max:9',
            'country_code' => 'required|min:3|max:3'
        ]);

        $isOtpVerified = $this->otpService->isOtpVerified(
            $validatedData['code'],
            $validatedData['country_code'],
            $validatedData['phone']
        );

        if (!$isOtpVerified) {
            return $this->errorResponse('Incorrect OTP entered', 401);
        }

        // You might want to return a temporary token here if you want to secure the reset process further
        return $this->successResponse([], 'OTP verified successfully');
    }

    public function resetPassword(Request $request)
    {
        $validatedData = $request->validate([
            'phone' => 'required|min:9|max:9',
            'country_code' => 'required|min:3|max:3',
            'password' => [
                'required',
                'min:8',
                function ($attribute, $value, $fail) {
                    if (!preg_match(self::PASSWORD_REGEX, $value)) {
                        $fail('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.');
                    }
                },
            ],
        ]);

        $user = User::where([
            'phone' => $validatedData['phone'],
            'country_code' => $validatedData['country_code']
        ])->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $user->password = Hash::make($validatedData['password']);
        $user->save();

        // Optional: Invalidate all existing tokens
        $user->tokens()->delete();

        return $this->successResponse([], 'Password reset successfully');
    }

    public function testAp(Request $request)
    {
        try {
            $apiService = new NLQueryService();
        } catch (\Exception $e) {
            Log::error('Error fetching user profile: ' . $e->getMessage());
            return $this->errorResponse('Error fetching user profile', 500);
        }
    }
}
