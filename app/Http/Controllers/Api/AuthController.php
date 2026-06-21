<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new user account.
     *
     * @param  RegisterRequest  $request
     *
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User();
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->password = Hash::make($request->input('password'));
        $user->status = config('custom.user.status_active');
        $user->save();

        if (!$user->hasRole(config('custom.user.role_customer'))) {
            $user->assignRole(config('custom.user.role_customer'));
        }

        $token = $user->createToken('authToken')->accessToken;

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], __('Registration successful.'), 201);
    }

    /**
     * Login user and create token
     *
     * @param  AuthRequest  $request
     *
     * @return JsonResponse [string] access_token
     */
    public function login(AuthRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if ($user === null || !Hash::check($request->input('password'), $user->password)) {
            return $this->errorResponse(__('These credentials do not match our records.'), 401);
        }

        if ($user->status !== config('custom.user.status_active')) {
            return $this->errorResponse(__('Your account is not active.'), 403);
        }

        $token = $user->createToken('authToken')->accessToken;

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * Return the currently authenticated user.
     *
     * @param  Request  $request
     *
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse($request->user());
    }

    /**
     * Revoke the current access token (logout).
     *
     * @param  Request  $request
     *
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return $this->successResponse(null, __('Logged out successfully.'));
    }
}
