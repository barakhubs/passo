# Forgot Password Flow Documentation

This document describes the secure forgot password implementation with temporary tokens and rate limiting.

## Enhanced Security Features

✅ **Temporary Reset Tokens** - 15-minute expiry  
✅ **Rate Limiting** - Max 3 attempts per phone number  
✅ **Token Validation** - Multiple security checks  
✅ **Session Invalidation** - All tokens revoked after reset  
✅ **Anti-Enumeration** - Same response for invalid users

## Complete Flow

### **Step 1: Initiate Password Reset**

**Endpoint:** `POST /forgot-password`

**Request:**

```json
{
    "phone": "123456789",
    "country_code": "+255"
}
```

**Response (Success):**

```json
{
    "status": "success",
    "message": "OTP sent successfully for password reset",
    "data": []
}
```

**Response (Rate Limited):**

```json
{
    "status": "error",
    "message": "Too many password reset attempts. Please try again in 12 minutes."
}
```

**Security Features:**

-   Rate limiting: 3 attempts per 15 minutes per phone number
-   Same response time for existing/non-existing users
-   Validates complete registration status

### **Step 2: Verify OTP & Get Reset Token**

**Endpoint:** `POST /verify-reset-otp`

**Request:**

```json
{
    "code": "1234",
    "phone": "123456789",
    "country_code": "+255"
}
```

**Response (Success):**

```json
{
    "status": "success",
    "message": "OTP verified successfully. You can now reset your password.",
    "data": {
        "reset_token": "a1b2c3d4e5f6...64_character_token",
        "expires_in": 900
    }
}
```

**Security Features:**

-   Generates cryptographically secure 64-character token
-   Token expires in 15 minutes (900 seconds)
-   Token stored in cache with phone/country code binding

### **Step 3: Reset Password**

**Endpoint:** `POST /reset-password`

**Request:**

```json
{
    "phone": "123456789",
    "country_code": "+255",
    "reset_token": "a1b2c3d4e5f6...64_character_token",
    "password": "NewSecureP@ss123"
}
```

**Response (Success):**

```json
{
    "status": "success",
    "message": "Password reset successfully. Please login with your new password.",
    "data": []
}
```

**Response (Invalid/Expired Token):**

```json
{
    "status": "error",
    "message": "Invalid or expired reset token. Please start the password reset process again."
}
```

**Security Features:**

-   Validates token exists and matches
-   Checks token expiration
-   Verifies phone/country code match
-   Password strength validation
-   Cleans up reset token after use
-   Invalidates all existing user sessions

## Security Validations

### **Token Validation Process:**

1. ✅ Token exists in cache
2. ✅ Token matches provided value
3. ✅ Token hasn't expired (15 minutes)
4. ✅ Phone and country code match original request
5. ✅ User still exists in database

### **Rate Limiting:**

-   **Limit:** 3 attempts per phone number
-   **Window:** 15 minutes
-   **Scope:** Per phone number (not IP-based)
-   **Reset:** Automatic after 15 minutes

### **Token Security:**

-   **Length:** 64 characters (32 bytes hex-encoded)
-   **Entropy:** Cryptographically secure random
-   **Storage:** Redis/Cache with TTL
-   **Expiry:** 15 minutes
-   **Single Use:** Automatically deleted after password reset

## Mobile Implementation Guide

### **React Native Example:**

```javascript
class ForgotPasswordFlow {
    constructor() {
        this.resetToken = null;
    }

    // Step 1: Request OTP
    async requestPasswordReset(phone, countryCode) {
        try {
            const response = await fetch(`${API_BASE}/forgot-password`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    phone: phone,
                    country_code: countryCode,
                }),
            });

            const data = await response.json();

            if (data.status === "success") {
                // Navigate to OTP verification screen
                this.navigateToOTPScreen(phone, countryCode);
            } else {
                // Handle error (rate limiting, user not found, etc.)
                this.showError(data.message);
            }
        } catch (error) {
            this.showError("Network error occurred");
        }
    }

    // Step 2: Verify OTP and get reset token
    async verifyOTPAndGetToken(phone, countryCode, otpCode) {
        try {
            const response = await fetch(`${API_BASE}/verify-reset-otp`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    phone: phone,
                    country_code: countryCode,
                    code: otpCode,
                }),
            });

            const data = await response.json();

            if (data.status === "success") {
                // Store reset token securely
                this.resetToken = data.data.reset_token;

                // Show countdown timer (15 minutes)
                this.startTokenExpiryTimer(data.data.expires_in);

                // Navigate to password reset screen
                this.navigateToPasswordResetScreen(phone, countryCode);
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            this.showError("Network error occurred");
        }
    }

    // Step 3: Reset password
    async resetPassword(phone, countryCode, newPassword) {
        if (!this.resetToken) {
            this.showError("Reset session expired. Please start over.");
            return;
        }

        try {
            const response = await fetch(`${API_BASE}/reset-password`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    phone: phone,
                    country_code: countryCode,
                    reset_token: this.resetToken,
                    password: newPassword,
                }),
            });

            const data = await response.json();

            if (data.status === "success") {
                // Clear reset token
                this.resetToken = null;

                // Show success message
                this.showSuccess("Password reset successfully!");

                // Navigate to login screen
                this.navigateToLogin();
            } else {
                // Handle token expiry or other errors
                if (
                    data.message.includes("expired") ||
                    data.message.includes("Invalid")
                ) {
                    this.resetToken = null;
                    this.showError("Reset session expired. Please start over.");
                    this.navigateToForgotPassword();
                } else {
                    this.showError(data.message);
                }
            }
        } catch (error) {
            this.showError("Network error occurred");
        }
    }

    startTokenExpiryTimer(expiresIn) {
        let timeLeft = expiresIn;

        const timer = setInterval(() => {
            timeLeft--;

            if (timeLeft <= 0) {
                clearInterval(timer);
                this.resetToken = null;
                this.showError("Reset session expired. Please start over.");
                this.navigateToForgotPassword();
            } else {
                // Update UI with remaining time
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                this.updateTimerUI(
                    `${minutes}:${seconds.toString().padStart(2, "0")}`
                );
            }
        }, 1000);
    }
}
```

### **Android (Kotlin) Example:**

```kotlin
class ForgotPasswordManager {
    private var resetToken: String? = null
    private var tokenExpiryTimer: CountDownTimer? = null

    fun requestPasswordReset(phone: String, countryCode: String) {
        val request = ForgotPasswordRequest(phone, countryCode)

        apiService.forgotPassword(request)
            .enqueue(object : Callback<ApiResponse> {
                override fun onResponse(call: Call<ApiResponse>, response: Response<ApiResponse>) {
                    if (response.isSuccessful && response.body()?.status == "success") {
                        navigateToOTPScreen(phone, countryCode)
                    } else {
                        showError(response.body()?.message ?: "Error occurred")
                    }
                }

                override fun onFailure(call: Call<ApiResponse>, t: Throwable) {
                    showError("Network error occurred")
                }
            })
    }

    fun verifyOTPAndGetToken(phone: String, countryCode: String, otpCode: String) {
        val request = VerifyOTPRequest(phone, countryCode, otpCode)

        apiService.verifyResetOTP(request)
            .enqueue(object : Callback<VerifyOTPResponse> {
                override fun onResponse(call: Call<VerifyOTPResponse>, response: Response<VerifyOTPResponse>) {
                    if (response.isSuccessful && response.body()?.status == "success") {
                        val data = response.body()!!.data
                        resetToken = data.resetToken

                        startTokenExpiryTimer(data.expiresIn.toLong())
                        navigateToPasswordResetScreen(phone, countryCode)
                    } else {
                        showError(response.body()?.message ?: "OTP verification failed")
                    }
                }

                override fun onFailure(call: Call<VerifyOTPResponse>, t: Throwable) {
                    showError("Network error occurred")
                }
            })
    }

    fun resetPassword(phone: String, countryCode: String, newPassword: String) {
        val token = resetToken
        if (token == null) {
            showError("Reset session expired. Please start over.")
            return
        }

        val request = ResetPasswordRequest(phone, countryCode, token, newPassword)

        apiService.resetPassword(request)
            .enqueue(object : Callback<ApiResponse> {
                override fun onResponse(call: Call<ApiResponse>, response: Response<ApiResponse>) {
                    if (response.isSuccessful && response.body()?.status == "success") {
                        cleanupResetFlow()
                        showSuccess("Password reset successfully!")
                        navigateToLogin()
                    } else {
                        val message = response.body()?.message ?: "Password reset failed"
                        if (message.contains("expired") || message.contains("Invalid")) {
                            cleanupResetFlow()
                            showError("Reset session expired. Please start over.")
                            navigateToForgotPassword()
                        } else {
                            showError(message)
                        }
                    }
                }

                override fun onFailure(call: Call<ApiResponse>, t: Throwable) {
                    showError("Network error occurred")
                }
            })
    }

    private fun startTokenExpiryTimer(expiresInSeconds: Long) {
        tokenExpiryTimer?.cancel()

        tokenExpiryTimer = object : CountDownTimer(expiresInSeconds * 1000, 1000) {
            override fun onTick(millisUntilFinished: Long) {
                val minutes = (millisUntilFinished / 1000) / 60
                val seconds = (millisUntilFinished / 1000) % 60
                updateTimerUI("$minutes:${seconds.toString().padStart(2, '0')}")
            }

            override fun onFinish() {
                cleanupResetFlow()
                showError("Reset session expired. Please start over.")
                navigateToForgotPassword()
            }
        }.start()
    }

    private fun cleanupResetFlow() {
        resetToken = null
        tokenExpiryTimer?.cancel()
        tokenExpiryTimer = null
    }
}
```

## Error Handling

### **Common Error Responses:**

| Error                   | Status Code | Message                               | Action                  |
| ----------------------- | ----------- | ------------------------------------- | ----------------------- |
| Rate Limited            | 429         | "Too many password reset attempts..." | Wait and retry          |
| User Not Found          | 404         | "No account found..."                 | Register or check phone |
| Incomplete Registration | 400         | "Your registration is incomplete..."  | Complete registration   |
| Invalid OTP             | 401         | "Incorrect OTP entered"               | Retry OTP               |
| Expired Token           | 401         | "Invalid or expired reset token..."   | Restart flow            |
| Invalid Token           | 401         | "Invalid reset token..."              | Restart flow            |
| Weak Password           | 422         | "Password must contain..."            | Update password         |

### **Best Practices:**

1. **Clear Error Messages:** Always show user-friendly error messages
2. **Automatic Cleanup:** Clear stored tokens on errors
3. **Timer Display:** Show remaining time for token expiry
4. **Graceful Degradation:** Handle network errors gracefully
5. **Secure Storage:** Never log or store reset tokens permanently
6. **User Guidance:** Provide clear next steps for each error

## Testing

### **Manual Testing Scenarios:**

1. ✅ Normal flow (happy path)
2. ✅ Rate limiting (4+ attempts)
3. ✅ Invalid OTP codes
4. ✅ Expired reset tokens
5. ✅ Invalid reset tokens
6. ✅ Non-existent users
7. ✅ Incomplete registrations
8. ✅ Weak passwords
9. ✅ Network interruptions
10. ✅ Concurrent reset attempts

This enhanced flow provides enterprise-level security while maintaining a smooth user experience! 🔐
