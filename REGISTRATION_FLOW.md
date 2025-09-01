# User Registration Flow

## Overview

This document describes the enhanced user registration flow that handles incomplete registrations gracefully.

## Registration Scenarios

### 1. New User Registration

-   User provides phone number and country code
-   System creates new user record with `status: 'inactive'` and `password: null`
-   OTP is sent to the user's phone
-   User verifies OTP and sets password
-   User status changes to `active` and registration is complete

### 2. Incomplete Registration Recovery

When a user starts registration but doesn't complete it (leaves app, terminates app, etc.), they can restart the registration process:

-   If user has no password set (`password: null`):
    -   System allows re-registration
    -   Sends new OTP
    -   User can complete registration by setting password
    -   Message: "OTP sent to {phone} successfully. Continuing previous registration."

### 3. Complete Registration Attempt

When a user tries to register with a phone number that already has a complete registration:

-   If user has password set (`password: not null`):
    -   System blocks registration
    -   Suggests using "Forgot Password" instead
    -   Message: "This phone number is already registered. Please use 'Forgot Password' to reset your password instead of creating a new account."

### 4. Password Reset vs Registration

The system intelligently directs users to the correct flow:

#### Forgot Password

-   If user has no password: "Your registration is incomplete. Please complete your registration instead of resetting password."
-   If user has password: Proceeds with password reset flow

#### Registration Step Two

-   If user already has password: "This phone number is already registered with a password. Please use 'Forgot Password' to reset your password or login directly."
-   If user has no password: Allows password setting and completes registration

## API Endpoints

### POST /api/register/step/one

**Request:**

```json
{
    "country_code": "255",
    "phone": "123456789"
}
```

**Responses:**

-   201: New registration or continuing incomplete registration
-   409: Phone already registered with password (should use forgot password)

### POST /api/register/step/two

**Request:**

```json
{
    "phone": "123456789",
    "country_code": "255",
    "password": "Test123!@#"
}
```

**Responses:**

-   201: Registration completed successfully
-   409: User already has password (should use forgot password)
-   404: User not found (should start from step one)

### POST /api/forgot-password

**Request:**

```json
{
    "phone": "123456789",
    "country_code": "255"
}
```

**Responses:**

-   200: OTP sent for password reset
-   400: Registration incomplete (should complete registration first)
-   404: User not found (should register first)

## Cleanup Process

### Automatic Cleanup

-   Runs before each new registration attempt
-   Removes incomplete registrations older than 24 hours

### Manual Cleanup

```bash
# Clean up registrations older than 24 hours (default)
php artisan users:cleanup-incomplete

# Clean up registrations older than custom hours
php artisan users:cleanup-incomplete --hours=48
```

## Database States

### User Status Field

-   `inactive`: Default status for new registrations, no password set
-   `active`: Registration completed, user can login
-   `suspended`: User account disabled

### User Password Field

-   `null`: Registration incomplete, user can re-register
-   `not null`: Registration complete, user should use forgot password

## Error Handling

The system provides clear, actionable error messages:

-   Guides users to correct flow (registration vs password reset)
-   Prevents duplicate registrations
-   Allows recovery from incomplete registrations
-   Maintains data integrity while being user-friendly

## Security Considerations

1. **OTP Validation**: All registration steps require proper OTP verification
2. **Password Security**: Strong password requirements enforced
3. **Cleanup**: Old incomplete registrations are automatically removed
4. **Duplicate Prevention**: System prevents creating duplicate complete registrations
5. **Clear Messaging**: Users understand exactly what action to take next
