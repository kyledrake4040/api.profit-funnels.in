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
     * Login user and create token
     *
     * @param  AuthRequest  $request
     *
     * @return JsonResponse [string] access_token
     */
    public function login(AuthRequest $request)
    {
        $user = User::where('email', $request->input('email'))->first();

        if ($user === null || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'code' => 401,
                'success' => false,
                'message' => __('These credentials do not match our records.'),
            ], 401);
        }

        if ($user->status !== config('custom.user.status_active')) {
            return response()->json([
                'code' => 403,
                'success' => false,
                'message' => __('Your account is not active.'),
            ], 403);
        }

        $token = $user->createToken('authToken')->accessToken;

        return response()->json([
            'code' => 200,
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }
}
