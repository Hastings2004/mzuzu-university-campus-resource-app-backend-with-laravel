<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    //register a new user
    public function register(Request $request){
        $field = $request->validate([
            'first_name'=> 'required|alpha|max:255',
            'last_name'=> 'required|alpha|max:255',
            'user_type'=> 'required',
            'email'=> 'required|max:255|email|unique:users|regex:/^[a-zA-Z0-9._-]+@my\.mzuni\.ac\.mw$/',
            'password'=> 'required|confirmed|min:6',
            'identity_number'=> 'nullable|string|max:255',
            'phone'=> 'nullable|string|max:30',
            'physical_address'=> 'nullable|string',
            'post_address'=> 'nullable|string',
            'district'=> 'nullable|string|max:255',
            'village'=> 'nullable|string|max:255',
        ]);

        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'message' => 'Invalid email address. Please use a valid my.mzuni.ac.mw email address.',
            ], 400);
        }

        $user = User::create($field);

        $roleName = $request->input('user_type'); 
        $role = Role::where('name', $roleName)->first();

        if ($role) {
            $user->roles()->attach($role->id); // This is the line that performs the role assignment
        } else {
           return ['message'=>"role not found"]; // This means the role 'student' or 'staff' doesn't exist in your 'roles' table
        }

        // Send email verification
        $user->sendEmailVerificationNotification();

        $token = $user->createToken($request->first_name);

         
        return [
            'user'=> $user,
            'success'=> true,
            'token'=> $token->plainTextToken, 
            'message' => 'Registration successful! Please check your email to verify your account.',
        ];
    }

    //login a user
    public function login(Request $request){
        $request->validate([
            'email'=> 'required|max:255|email|exists:users',
            'password'=> 'required',
        ]);

         $user = User::where('email', $request->email)->first();

        if(!$user || !Hash::check($request->password, $user->password)){
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address before logging in.',
                'email_verified' => false,
                'user_id' => $user->id
            ], 403);
        }

        // Load user with roles
        $user->load('roles');
        
        
        $roleId = $user->roles->first() ? $user->roles->first()->id : null;
        
        $token = $user->createToken($user->first_name)->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role_id' => $roleId,
                'user_type' => $user->user_type, 
                'email_verified_at' => $user->email_verified_at,
            ],
            'success' => true,
            'token' => $token
        ], 200);
    }

    //logout a user
    public function logout(Request $request){
        $request -> user() -> tokens() -> delete();

        return [
            'message'=> 'you have logged out'
        ];
    }

    /**
     * Mark the authenticated user's email address as verified.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  string  $hash
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        // Check if email is already verified
        if ($user->hasVerifiedEmail()) {
            return redirect()->away(config('app.frontend_url') . '/verify-email?status=already_verified');
        }

        // Check the hash
        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->away(config('app.frontend_url') . '/verify-email?status=invalid_link');
        }

        // Mark email as verified
        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return redirect()->away(config('app.frontend_url') . '/verify-email?status=success');
    }

    /**
     * Resend the email verification notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified.'
            ], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification link sent!'
        ]);
    }

    /**
     * Resend verification email for unauthenticated users (public endpoint).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationPublic(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified.'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification link sent!'
        ]);
    }

    /**
     * Check if user's email is verified.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkVerificationStatus(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at,
        ]);
    }

    /**
     * Send a reset link to the given user.
     *
     * @param  \App\Http\Requests\ForgotPasswordRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email address.'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to send password reset link. Please try again.'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Password reset request failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again.'
            ], 500);
        }
    }

    /**
     * Reset the given user's password.
     *
     * @param  \App\Http\Requests\ResetPasswordRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password has been reset successfully.'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to reset password. Please check your token and try again.'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while resetting your password. Please try again.'
            ], 500);
        }
    }

    /**
     * Check if a password reset token is valid.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkResetToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            // Check if the token exists and is not expired
            $tokenExists = \DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('token', $request->token)
                ->where('created_at', '>', now()->subMinutes(60))
                ->exists();

            if ($tokenExists) {
                return response()->json([
                    'success' => true,
                    'message' => 'Token is valid.'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Token validation failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while validating the token.'
            ], 500);
        }
    }
}
