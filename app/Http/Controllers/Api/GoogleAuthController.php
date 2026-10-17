<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\GoogleLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Generate and return the Google OAuth redirect URL.
     */
    public function getRedirectUrl(): JsonResponse
    {
        try {
            /** @var AbstractProvider $driver */
            $driver = $this->googleDriver();
            $url = $driver->redirect()->getTargetUrl();

            return $this->successResponse(
                data: ['url' => $url],
                message: 'Google redirect URL generated successfully'
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                message: 'Failed to generate Google redirect URL',
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errors: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Handle the callback returned from Google OAuth authentication.
     */
    public function handleWebCallback(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->has('error')) {
            return $this->errorResponse(
                message: 'Google authorization was denied or cancelled.',
                statusCode: Response::HTTP_UNAUTHORIZED,
                errors: ['error' => $request->query('error')]
            );
        }

        try {
            /** @var ProviderUser $googleUser */
            $googleUser = $this->googleDriver()->user();

            $user = $this->findOrCreateUser($googleUser);
            $token = Auth::guard('api')->login($user);

            $frontendUrl = config('services.google.frontend_url');
            if (! empty($frontendUrl)) {
                return redirect()->away($frontendUrl.'?token='.$token);
            }

            return $this->successResponse(
                data: $this->buildAuthPayload($user, $token),
                message: 'Authenticated successfully with Google'
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                message: 'Google authentication failed',
                statusCode: Response::HTTP_UNAUTHORIZED,
                errors: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Authenticate using a Google access token (for mobile apps and SPAs).
     */
    public function loginWithToken(GoogleLoginRequest $request): JsonResponse
    {
        try {
            /** @var AbstractProvider $driver */
            $driver = $this->googleDriver();

            /** @var ProviderUser $googleUser */
            $googleUser = $driver->userFromToken($request->validated('access_token'));

            $user = $this->findOrCreateUser($googleUser);
            $token = Auth::guard('api')->login($user);

            return $this->successResponse(
                data: $this->buildAuthPayload($user, $token),
                message: 'Authenticated successfully with Google'
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                message: 'Invalid or expired Google token',
                statusCode: Response::HTTP_UNAUTHORIZED,
                errors: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Configure stateless Google Socialite driver and bypass SSL verification in local environment.
     */
    protected function googleDriver(): SocialiteProvider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google')->stateless();

        if (app()->isLocal()) {
            $driver->setHttpClient(new Client(['verify' => false]));
        }

        return $driver;
    }

    /**
     * Find existing user by google_id or email, or create a new user record.
     */
    protected function findOrCreateUser(ProviderUser $googleUser): User
    {
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'image' => $user->image ?? $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            return $user;
        }

        $fullName = $googleUser->getName()
            ?: ($googleUser->getNickname() ?: explode('@', (string) $googleUser->getEmail())[0]);

        return User::create([
            'full_name' => $fullName,
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'image' => $googleUser->getAvatar(),
            'system_role' => 'TeamMember',
            'availability_status' => 'Available',
            'years_of_experience' => 0,
            'email_verified_at' => now(),
            'password' => null,
        ]);
    }

    /**
     * Assemble consistent user and JWT response structure.
     *
     * @return array{user: UserResource, token: array{access_token: string, token_type: string, expires_in: int}}
     */
    protected function buildAuthPayload(User $user, string $token): array
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
