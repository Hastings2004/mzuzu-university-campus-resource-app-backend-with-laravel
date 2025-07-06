<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackUserSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track sessions for authenticated users
        if (Auth::check()) {
            $user = Auth::user();
            $token = $request->bearerToken();

            if ($token) {
                // Find or create session for this token
                $session = UserSession::where('user_id', $user->id)
                    ->where('uuid', $token)
                    ->first();

                if (!$session) {
                    // Create new session
                    $session = UserSession::create([
                        'user_id' => $user->id,
                        'uuid' => $token,
                        'device' => $this->getDeviceInfo($request),
                        'location' => $this->getLocationInfo($request),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'last_active' => now(),
                        'is_active' => true,
                    ]);
                } else {
                    // Update last active time
                    $session->update([
                        'last_active' => now(),
                        'device' => $this->getDeviceInfo($request),
                        'location' => $this->getLocationInfo($request),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                }
            }
        }

        return $response;
    }

    /**
     * Get device information from request
     */
    private function getDeviceInfo(Request $request): string
    {
        $userAgent = $request->userAgent();
        
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'Mobile Device';
        } elseif (preg_match('/Windows/', $userAgent)) {
            return 'Windows PC';
        } elseif (preg_match('/Mac/', $userAgent)) {
            return 'Mac';
        } elseif (preg_match('/Linux/', $userAgent)) {
            return 'Linux PC';
        }
        
        return 'Unknown Device';
    }

    /**
     * Get location information (simplified - in production you might use a geolocation service)
     */
    private function getLocationInfo(Request $request): string
    {
        // This is a simplified version. In production, you might use:
        // - IP geolocation services
        // - Browser geolocation API
        // - VPN detection services
        
        $ip = $request->ip();
        
        // For now, just return a generic location
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'Local Development';
        }
        
        return 'Unknown Location';
    }
}
