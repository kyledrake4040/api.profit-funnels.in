<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate a user by email + password and issue an API access token.
     *
     * @return JsonResponse
     */
    public function login(AuthRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('These credentials do not match our records.'),
            ], 401);
        }

        $token = $user->createToken('api')->accessToken;

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
