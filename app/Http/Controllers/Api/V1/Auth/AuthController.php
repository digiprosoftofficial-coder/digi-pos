<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterStoreRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\StoreProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponses;

    public function __construct(protected StoreProvisioningService $provisioning) {}

    /**
     * Register a new store (tenant) + owner and return an access token.
     */
    public function register(RegisterStoreRequest $request): JsonResponse
    {
        ['store' => $store, 'user' => $user] = $this->provisioning->provision($request->validated());

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return $this->created([
            'token' => $token,
            'user' => new UserResource($user->load('store')),
        ], 'Store created successfully.');
    }

    /**
     * Authenticate a user and issue a Sanctum token (works for web + mobile).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return $this->error('Your account is disabled. Please contact your store owner.', 403);
        }

        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user' => new UserResource($user->load('store')),
        ], 'Logged in successfully.');
    }

    /**
     * Current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()->load('store')));
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Logged out successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return $this->ok(null, 'If that email exists, a reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->ok(null, 'Password has been reset. You can log in now.');
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->error('Invalid or expired verification link.', 403);
        }

        $user = User::findOrFail($request->integer('id'));

        if (! hash_equals((string) $request->query('hash'), sha1($user->getEmailForVerification()))) {
            return $this->error('Invalid verification link.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->ok(null, 'Email already verified.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $this->ok(null, 'Email verified successfully.');
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->ok(null, 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->ok(null, 'Verification link sent.');
    }
}
