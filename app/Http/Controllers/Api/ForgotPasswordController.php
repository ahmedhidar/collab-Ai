<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ForgotPasswordController extends Controller
{
    /**
     * Handle sending the reset link via email.
     */
    public function sendResetLink(ForgotPasswordRequest $request)
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $resetUrl = "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($email);

        Mail::to($email)->queue(new ResetPasswordMail($resetUrl, $user->full_name));

        return $this->successResponse(
            message: 'Password reset link sent to your email successfully.'
        );
    }

    /**
     * Handle updating user password using reset token.
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $validated = $request->validated();

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (! $record) {
            return $this->errorResponse(
                message: 'Invalid reset request or expired token.',
                statusCode: Response::HTTP_BAD_REQUEST
            );
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            return $this->errorResponse(
                message: 'Reset token has expired.',
                statusCode: Response::HTTP_BAD_REQUEST
            );
        }

        if (! Hash::check($validated['token'], $record->token)) {
            return $this->errorResponse(
                message: 'Invalid token provided.',
                statusCode: Response::HTTP_BAD_REQUEST
            );
        }

        $user = User::where('email', $validated['email'])->first();
        $user->password = Hash::make($validated['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return $this->successResponse(
            message: 'Password updated successfully. You can now log in.'
        );
    }
}
