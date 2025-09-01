<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('new user can register successfully', function () {
    $response = $this->postJson('/api/register/step/one', [
        'country_code' => '255',
        'phone' => '123456789'
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'OTP sent to 255123456789 successfully'
        ]);

    $this->assertDatabaseHas('users', [
        'phone' => '123456789',
        'country_code' => '255',
        'password' => null,
        'status' => 'inactive'
    ]);
});

test('user with incomplete registration can re-register', function () {
    // Create a user with incomplete registration (no password)
    User::create([
        'phone' => '123456789',
        'country_code' => '255',
        'status' => 'inactive',
        'password' => null
    ]);

    $response = $this->postJson('/api/register/step/one', [
        'country_code' => '255',
        'phone' => '123456789'
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'OTP sent to 255123456789 successfully. Continuing previous registration.'
        ]);
});

test('user with complete registration cannot re-register', function () {
    // Create a user with complete registration (has password)
    User::create([
        'phone' => '123456789',
        'country_code' => '255',
        'status' => 'active',
        'password' => bcrypt('Test123!@#')
    ]);

    $response = $this->postJson('/api/register/step/one', [
        'country_code' => '255',
        'phone' => '123456789'
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'status' => 'error',
            'message' => 'This phone number is already registered. Please use "Forgot Password" to reset your password instead of creating a new account.'
        ]);
});

test('user cannot set password if already has one', function () {
    // Create a user with password already set
    User::create([
        'phone' => '123456789',
        'country_code' => '255',
        'status' => 'active',
        'password' => bcrypt('Test123!@#')
    ]);

    $response = $this->postJson('/api/register/step/two', [
        'phone' => '123456789',
        'country_code' => '255',
        'password' => 'NewPass123!@#'
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'status' => 'error',
            'message' => 'This phone number is already registered with a password. Please use "Forgot Password" to reset your password or login directly.'
        ]);
});

test('user with incomplete registration can complete it', function () {
    // Create a user with incomplete registration
    User::create([
        'phone' => '123456789',
        'country_code' => '255',
        'status' => 'inactive',
        'password' => null
    ]);

    $response = $this->postJson('/api/register/step/two', [
        'phone' => '123456789',
        'country_code' => '255',
        'password' => 'Test123!@#'
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Registration successful'
        ]);

    $this->assertDatabaseHas('users', [
        'phone' => '123456789',
        'country_code' => '255',
        'status' => 'active'
    ]);

    $user = User::where('phone', '123456789')->first();
    $this->assertNotNull($user->password);
});

test('forgot password fails for users without password', function () {
    // Create a user with incomplete registration
    User::create([
        'phone' => '123456789',
        'country_code' => '255',
        'status' => 'inactive',
        'password' => null
    ]);

    $response = $this->postJson('/api/forgot-password', [
        'phone' => '123456789',
        'country_code' => '255'
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'status' => 'error',
            'message' => 'Your registration is incomplete. Please complete your registration instead of resetting password.'
        ]);
});

test('forgot password works for users with password', function () {
    // Create a user with complete registration
    User::create([
        'phone' => '123456789',
        'country_code' => '255',
        'status' => 'active',
        'password' => bcrypt('Test123!@#')
    ]);

    $response = $this->postJson('/api/forgot-password', [
        'phone' => '123456789',
        'country_code' => '255'
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'OTP sent successfully for password reset'
        ]);
});
