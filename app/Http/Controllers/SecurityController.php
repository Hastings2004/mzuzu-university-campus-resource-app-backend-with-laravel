<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Http\JsonResponse;

class SecurityController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Setup 2FA for the authenticated user
     */
    public function setup2FA(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->two_factor_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA is already enabled for this account.'
                ], 400);
            }

            // Generate a new secret
            $secret = $this->google2fa->generateSecretKey();
            
            // Generate backup codes
            $backupCodes = $this->generateBackupCodes();
            
            // Create QR code URL
            $qrCodeUrl = $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret
            );

            // Store the secret temporarily (we'll save it after verification)
            $user->two_factor_secret = $secret;
            $user->two_factor_backup_codes = $backupCodes;
            $user->save();

            return response()->json([
                'success' => true,
                'qr_code' => $qrCodeUrl,
                'backup_codes' => $backupCodes,
                'message' => '2FA setup initiated successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('2FA setup failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to setup 2FA. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify 2FA code and enable 2FA
     */
    public function verify2FA(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string|max:6'
            ]);

            $user = Auth::user();
            $code = $request->input('code');

            // Check if the code is valid
            if (!$this->google2fa->verifyKey($user->two_factor_secret, $code)) {
                // Check if it's a backup code
                if (!$this->verifyBackupCode($user, $code)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid verification code.'
                    ], 400);
                }
            }

            // Enable 2FA
            $user->two_factor_enabled = true;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => '2FA enabled successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('2FA verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify 2FA code. Please try again.'
            ], 500);
        }
    }

    /**
     * Disable 2FA for the authenticated user
     */
    public function disable2FA(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->two_factor_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA is not enabled for this account.'
                ], 400);
            }

            // Clear 2FA data
            $user->two_factor_enabled = false;
            $user->two_factor_secret = null;
            $user->two_factor_backup_codes = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => '2FA disabled successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('2FA disable failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable 2FA. Please try again.'
            ], 500);
        }
    }

    /**
     * Get active sessions for the authenticated user
     */
    public function getSessions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $sessions = $user->sessions()
                ->where('is_active', true)
                ->orderBy('last_active', 'desc')
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'device' => $session->device ?: 'Unknown Device',
                        'location' => $session->location ?: 'Unknown Location',
                        'last_active' => $session->last_active,
                        'ip_address' => $session->ip_address,
                    ];
                });

            return response()->json([
                'success' => true,
                'sessions' => $sessions
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch sessions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active sessions.'
            ], 500);
        }
    }

    /**
     * Logout from all devices
     */
    public function logoutAllDevices(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Deactivate all sessions
            $user->sessions()->update(['is_active' => false]);
            
            // Revoke all tokens
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all devices successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout all devices failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout from all devices.'
            ], 500);
        }
    }

    /**
     * Logout from a specific session
     */
    public function logoutSession(Request $request, $sessionId): JsonResponse
    {
        try {
            $user = Auth::user();
            $session = $user->sessions()->find($sessionId);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found.'
                ], 404);
            }

            $session->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Session logged out successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout session failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout session.'
            ], 500);
        }
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacySettings(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'profile_visibility' => 'sometimes|in:public,private,friends',
                'data_sharing' => 'sometimes|boolean',
                'email_notifications' => 'sometimes|boolean',
            ]);

            $user = Auth::user();
            
            $user->fill($request->only([
                'profile_visibility',
                'data_sharing',
                'email_notifications'
            ]));
            
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully.',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('Privacy settings update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update privacy settings.'
            ], 500);
        }
    }

    /**
     * Generate backup codes for 2FA
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(Str::random(8));
        }
        return $codes;
    }

    /**
     * Verify backup code
     */
    private function verifyBackupCode(User $user, string $code): bool
    {
        $backupCodes = $user->two_factor_backup_codes ?? [];
        
        if (in_array($code, $backupCodes)) {
            // Remove the used backup code
            $backupCodes = array_diff($backupCodes, [$code]);
            $user->two_factor_backup_codes = array_values($backupCodes);
            $user->save();
            return true;
        }
        
        return false;
    }
}
