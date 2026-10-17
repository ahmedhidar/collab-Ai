<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Register a new user and return JWT payload.
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'full_name' => $validated['full_name'] ?? $validated['name'] ?? '',
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->successResponse(
            data: $this->buildAuthPayload($user, $token),
            message: 'User registered successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Authenticate user credentials and return JWT payload.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return $this->errorResponse(
                message: 'Invalid email or password',
                statusCode: Response::HTTP_UNAUTHORIZED
            );
        }

        $user = Auth::guard('api')->user();

        return $this->successResponse(
            data: $this->buildAuthPayload($user, $token),
            message: 'Logged in successfully'
        );
    }

    /**
     * Retrieve the authenticated user's profile.
     */
    public function me()
    {
        return $this->successResponse(
            data: [
                'user' => new UserResource(Auth::guard('api')->user()),
            ],
            message: 'User profile retrieved successfully'
        );
    }

    /**
     * Refresh the existing JWT token.
     */
    public function refresh()
    {
        $token = Auth::guard('api')->refresh();
        $user = Auth::guard('api')->user();

        return $this->successResponse(
            data: $this->buildAuthPayload($user, $token),
            message: 'Token refreshed successfully'
        );
    }

    /**
     * Log out authenticated user.
     */
    public function logout()
    {
        Auth::guard('api')->logout();

        return $this->successResponse(
            message: 'Successfully logged out'
        );
    }

    /**
     * Assemble consistent user and JWT response structure.
     */
    protected function buildAuthPayload($user, string $token): array
    {
        return [
            'user' => new UserResource($user),
            'token' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            ],
        ];
    }
}
