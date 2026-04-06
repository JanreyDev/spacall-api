<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Step 1: Initial Entry.
     * Checks if user exists. If yes, go to PIN login. If no, send OTP for registration.
     */
    public function loginEntry(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
            'app_type' => 'required|in:client,therapist',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mobileNumber = $request->mobile_number;

        $user = User::where('mobile_number', $mobileNumber)->first();
        if ($user && $mobileNumber !== '09123456789' && $mobileNumber !== '+639123456789') {
            $userRole = $user->role ?? 'client';
            if ($userRole !== $request->app_type) {
                $appName = ucfirst($userRole) . ' App';
                return response()->json([
                    'message' => "This mobile number is already registered as a {$userRole}. Please use the Spacall {$appName} to log in."
                ], 403);
            }
        }

        // Path A: Trigger OTP for everyone
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        $otpRecord = Otp::create([
            'mobile_number' => $mobileNumber,
            'otp_code' => $otpCode,
            'expires_at' => $expiresAt,
            'used' => false,
        ]);

        $message = "Your 6-digit OTP for Spacall is: {$otpCode}. It will expire in 5 minutes.";
        \Illuminate\Support\Facades\Log::info("Generated OTP for {$mobileNumber}: {$otpCode}");

        // Bypass SMS for Google Reviewer
        if ($mobileNumber === '09123456789' || $mobileNumber === '+639123456789') {
            $otpCode = '123456';
            $otpRecord->update(['otp_code' => $otpCode]);
            \Illuminate\Support\Facades\Log::info("Bypassing SMS for Google Reviewer. Using static OTP: {$otpCode}");
            return response()->json([
                'status' => 'new_user',
                'next_step' => 'otp_verification',
                'message' => 'OTP sent successfully (Test Mode)'
            ]);
        }

        $this->smsService->sendSms($mobileNumber, $message);

        return response()->json([
            'status' => 'new_user',
            'next_step' => 'otp_verification',
            'message' => 'OTP sent successfully'
        ]);
    }

    /**
     * Step 2: Verify OTP.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
            'otp' => 'required|string|size:6',
            'app_type' => 'required|in:client,therapist',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $otpRecord = Otp::where('mobile_number', $request->mobile_number)
            ->where('otp_code', $request->otp)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $otpRecord->update(['used' => true]);

        $user = User::where('mobile_number', $request->mobile_number)
            ->where('is_verified', true)
            ->first();

        if ($user && $request->mobile_number !== '09123456789' && $request->mobile_number !== '+639123456789') {
            $userRole = $user->role ?? 'client';
            if ($userRole !== $request->app_type) {
                $appName = ucfirst($userRole) . ' App';
                return response()->json([
                    'message' => "This mobile number is already registered as a {$userRole}. Please use the Spacall {$appName} to log in."
                ], 403);
            }

            // Ensure provider record exists for therapists
            if ($user->role === 'therapist') {
                $provider = \App\Models\Provider::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'type' => 'therapist',
                        'verification_status' => 'verified', // Auto-verify for dev
                        'is_active' => true,
                        'is_available' => true,
                        'is_accepting_bookings' => true,
                    ]
                );

                \App\Models\TherapistProfile::firstOrCreate(
                    ['provider_id' => $provider->id],
                    [
                        'bio' => 'Awaiting profile completion.',
                        'years_of_experience' => 0,
                        'specializations' => [],
                    ]
                );

                if ($user->customer_tier === User::TIER_STORE) {
                    \App\Models\StoreProfile::firstOrCreate(
                        ['provider_id' => $provider->id],
                        [
                            'store_name' => $request->store_name ?? ($user->first_name . "'s Spa"),
                            'address' => $request->address ?? 'Main Street',
                            'city' => $request->city ?? 'Tarlac City',
                            'province' => $request->province ?? 'Tarlac',
                            'latitude' => $request->latitude ?? 0,
                            'longitude' => $request->longitude ?? 0,
                            'description' => 'Welcome to our premium store.',
                        ]
                    );
                }
            }

            $user->load('provider');
            $token = $user->createToken('wallet-token')->plainTextToken;
            return response()->json([
                'message' => 'Welcome back!',
                'next_step' => 'dashboard',
                'token' => $token,
                'user' => $user,
                'provider' => $user->provider
            ]);
        }

        return response()->json([
            'message' => 'OTP verified',
            'next_step' => 'registration',
            'provider' => null // Added provider to this response as well, assuming it should be null if user is not found yet
        ]);
    }

    /**
     * Step 3: Register profile + set PIN.
     */
    public function registerProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|in:male,female,lgbt',
            'date_of_birth' => 'required|date',
            'pin' => 'required|string|size:6',
            'profile_photo' => 'nullable|image|max:8192',
            'id_card_photo' => 'nullable|image|max:8192',
            'id_card_back_photo' => 'nullable|image|max:8192',
            'id_selfie_photo' => 'nullable|image|max:8192',
            'app_type' => 'required|in:client,therapist',
            'role' => 'nullable|in:client,therapist',
            'customer_tier' => 'nullable|in:classic,vip,store',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'store_name' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ensure OTP was verified (simple check for demo/mvp, can use signed tokens in real prod)
        $otpVerified = Otp::where('mobile_number', $request->mobile_number)
            ->where('used', true)
            ->where('updated_at', '>', Carbon::now()->subMinutes(15))
            ->exists();

        if (!$otpVerified) {
            return response()->json(['message' => 'Mobile number not verified by OTP'], 403);
        }

        $user = User::updateOrCreate(
            ['mobile_number' => $request->mobile_number],
            [
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'pin_hash' => Hash::make($request->pin),
                'is_verified' => true,
                'role' => $request->app_type,
                'customer_tier' => $request->customer_tier ?? User::TIER_CLASSIC,
                'wallet_balance' => $request->app_type === 'client' ? 5000 : 0,
            ]
        );

        // Assign Spatie Role (create missing role records on-the-fly)
        $roleName = $user->role ?? 'client';
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        if (!$user->hasRole($roleName)) {
            $user->assignRole($role);
        }

        if ($user->role === 'therapist') {
            $provider = \App\Models\Provider::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'type' => 'therapist',
                    'verification_status' => 'verified', // Auto-verify for dev
                    'is_active' => true,
                    'is_available' => true,
                    'is_accepting_bookings' => true,
                ]
            );

            \App\Models\TherapistProfile::firstOrCreate(
                ['provider_id' => $provider->id],
                [
                    'bio' => 'Awaiting profile completion.',
                    'years_of_experience' => 0,
                    'specializations' => [],
                ]
            );

            if ($user->customer_tier === User::TIER_STORE) {
                \App\Models\StoreProfile::firstOrCreate(
                    ['provider_id' => $provider->id],
                    [
                        'store_name' => $request->store_name ?? ($user->first_name . "'s Spa"),
                        'address' => $request->address ?? 'Main Street',
                        'city' => $request->city ?? 'Tarlac City',
                        'province' => $request->province ?? 'Tarlac',
                        'latitude' => $request->latitude ?? 0,
                        'longitude' => $request->longitude ?? 0,
                        'description' => 'Welcome to our premium store.',
                    ]
                );
            }
        }

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile_photos', 'public');
            $user->update(['profile_photo_url' => Storage::url($path)]);
        }

        if ($request->hasFile('id_card_photo')) {
            $path = $request->file('id_card_photo')->store('id_card_photos', 'public');
            $user->update(['id_card_photo_url' => Storage::url($path)]);
        }

        if ($request->hasFile('id_card_back_photo')) {
            $path = $request->file('id_card_back_photo')->store('id_card_back_photos', 'public');
            $user->update(['id_card_back_photo_url' => Storage::url($path)]);
        }

        if ($request->hasFile('id_selfie_photo')) {
            $path = $request->file('id_selfie_photo')->store('id_selfie_photos', 'public');
            $user->update(['id_selfie_photo_url' => Storage::url($path)]);
        }

        $user->load('provider');
        $token = $user->createToken('wallet-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user,
            'provider' => $user->provider
        ]);
    }

    /**
     * Secure Login with PIN.
     */
    public function loginPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
            'pin' => 'required|string|size:6',
            'app_type' => 'required|in:client,therapist',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('mobile_number', $request->mobile_number)->first();

        if ($user && $request->mobile_number !== '09123456789' && $request->mobile_number !== '+639123456789') {
            $userRole = $user->role ?? 'client';
            if ($userRole !== $request->app_type) {
                $appName = ucfirst($userRole) . ' App';
                return response()->json([
                    'message' => "This mobile number is already registered as a {$userRole}. Please use the Spacall {$appName} to log in."
                ], 403);
            }
        }

        if (!$user || !Hash::check($request->pin, $user->pin_hash)) {
            return response()->json(['message' => 'Invalid PIN'], 401);
        }

        // Fix missing roles for existing users
        if ($user->role && !$user->hasRole($user->role)) {
            $role = Role::firstOrCreate([
                'name' => $user->role,
                'guard_name' => 'web',
            ]);
            $user->assignRole($role);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            $messages = [
                User::STATUS_PENDING => 'Your account is currently pending verification.',
                User::STATUS_SUSPENDED => 'Your account has been suspended. Please contact support.',
                User::STATUS_DECLINED => 'Your account registration was declined.',
            ];

            return response()->json([
                'message' => $messages[$user->status] ?? 'Your account is not active.'
            ], 403);
        }

        // Ensure provider record exists for therapists
        if ($user->role === 'therapist') {
            $provider = \App\Models\Provider::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'type' => 'therapist',
                    'verification_status' => 'pending',
                    'is_active' => true,
                    'is_available' => false,
                    'is_accepting_bookings' => false,
                ]
            );

            \App\Models\TherapistProfile::firstOrCreate(
                ['provider_id' => $provider->id],
                [
                    'bio' => 'Awaiting profile completion.',
                    'years_of_experience' => 0,
                    'specializations' => [],
                ]
            );

            if ($user->customer_tier === User::TIER_STORE) {
                \App\Models\StoreProfile::firstOrCreate(
                    ['provider_id' => $provider->id],
                    [
                        'store_name' => $request->store_name ?? ($user->first_name . "'s Spa"),
                        'address' => $request->address ?? 'Main Street',
                        'city' => $request->city ?? 'Tarlac City',
                        'province' => $request->province ?? 'Tarlac',
                        'latitude' => $request->latitude ?? 0,
                        'longitude' => $request->longitude ?? 0,
                        'description' => 'Welcome to our premium store.',
                    ]
                );
            }
        }

        $user->load('provider');
        $token = $user->createToken('wallet-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'role' => $user->role,
            'is_provider' => $user->role === 'therapist',
            'provider' => $user->provider // explicit for compatibility
        ]);
    }

    /**
     * Forgot PIN: Send OTP to reset.
     */
    public function forgotPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mobileNumber = $request->mobile_number;
        $user = User::where('mobile_number', $mobileNumber)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Trigger OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Otp::create([
            'mobile_number' => $mobileNumber,
            'otp_code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(5),
            'used' => false,
        ]);

        $this->smsService->sendSms($mobileNumber, "Your PIN reset code is: {$otpCode}");

        return response()->json(['message' => 'Reset code sent successfully']);
    }

    /**
     * Reset PIN using OTP.
     */
    public function resetPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
            'otp' => 'required|string|size:6',
            'new_pin' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $otpRecord = Otp::where('mobile_number', $request->mobile_number)
            ->where('otp_code', $request->otp)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $otpRecord->update(['used' => true]);

        $user = User::where('mobile_number', $request->mobile_number)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->update(['pin_hash' => Hash::make($request->new_pin)]);

        return response()->json(['message' => 'PIN reset successful']);
    }
    /**
     * Upload single photo (Sequential Upload).
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        // Debug logging to help identify the 422
        \Log::info('Upload Photo Request:', $request->all());

        $allowedTypes = [
            'profile_photo',
            'id_card_photo',
            'id_card_back_photo',
            'id_selfie_photo',
            'license_photo',
            'certificate_photo',
            'gallery_photo',
            'license', // Add fallback
            'certificate' // Add fallback
        ];

        $type = $request->input('type');

        // Manual validation for better error message
        if (!in_array($type, $allowedTypes)) {
            return response()->json([
                'message' => 'The selected type is invalid.',
                'errors' => ['type' => ["The type '$type' is not allowed."]],
                'allowed_types' => $allowedTypes
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:8192', // 8MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Image validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $file = $request->file('image');
        $path = $file->store($type . 's', 'public');
        $url = Storage::url($path);

        $updateData = [];
        switch ($type) {
            case 'profile_photo':
                $updateData['profile_photo_url'] = $url;
                break;
            case 'id_card_photo':
                $updateData['id_card_photo_url'] = $url;
                break;
            case 'id_card_back_photo':
                $updateData['id_card_back_photo_url'] = $url;
                break;
            case 'id_selfie_photo':
                $updateData['id_selfie_photo_url'] = $url;
                break;
            case 'license_photo':
            case 'license':
                $updateData['license_photo_url'] = $url;
                break;
            case 'certificate_photo':
            case 'certificate':
                if ($user->role === 'therapist' && $user->provider) {
                    $profile = $user->provider->therapistProfile;
                    if ($profile) {
                        $certs = $profile->certifications ?? [];
                        $certs[] = $url;
                        $profile->update(['certifications' => $certs]);
                    }
                }
                break;
            case 'gallery_photo':
                if ($user->role === 'therapist' && $user->provider) {
                    $profile = $user->provider->therapistProfile;
                    if ($profile) {
                        $gallery = $profile->gallery_images ?? [];
                        $gallery[] = $url;
                        $profile->update(['gallery_images' => $gallery]);
                    }
                }
                break;
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'url' => $url,
            'type' => $type
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $hasEmailColumn = Schema::hasColumn('users', 'email');
        $rules = [
            'first_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'nickname' => 'nullable|string|max:50',
            'gender' => 'nullable|in:male,female,lgbt',
            'age' => 'nullable|integer',
            'date_of_birth' => 'nullable|date',
            'image' => 'nullable|image|max:8192', // Profile photo
        ];

        if ($hasEmailColumn) {
            $rules['email'] = 'nullable|email|unique:users,email,' . $user->id;
        } else {
            $rules['email'] = 'nullable|email';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = $request->only([
            'first_name',
            'middle_name',
            'last_name',
            'nickname',
            'gender',
            'age',
            'date_of_birth',
        ]);

        if ($hasEmailColumn && $request->has('email')) {
            $updateData['email'] = $request->input('email');
        }

        // Filter out null values to avoid overwriting existing data with null if not intended
        // However, if the user explicitly sends empty string, we might want to update it.
        // For now, let's assume sent fields are to be updated.
        $updateData = array_filter($updateData, function ($value) {
            return !is_null($value);
        });

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('profile_photos', 'public');
            $updateData['profile_photo_url'] = Storage::url($path);
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->refresh(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|size:6',
        ]);

        if ($validator->fails() || !Hash::check($request->pin, $request->user()->pin_hash)) {
            return response()->json(['message' => 'The security PIN you entered is incorrect.'], 403);
        }

        $user = $request->user();

        // Optionally, could do a soft delete or manual cleanup of related models, e.g. provider, otps, tokens
        // For now, hard delete cascade usually handles it, or explicit delete.

        // Revoke all tokens
        $user->tokens()->delete();

        // Hard Delete the user from the database
        $user->forceDelete();

        return response()->json(['success' => true, 'message' => 'Account deleted successfully']);
    }
}
