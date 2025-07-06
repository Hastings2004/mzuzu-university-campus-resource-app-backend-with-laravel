<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BinarySearchController;
use App\Http\Controllers\BookingApprovalController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Reservation;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ResourceIssueController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ResourceRecommendationController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Password reset routes (no authentication required)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:6,1')
    ->name('password.email');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:6,1')
    ->name('password.reset');

Route::post('/check-reset-token', [AuthController::class, 'checkResetToken'])
    ->middleware('throttle:6,1')
    ->name('password.token.check');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/logout', [AuthController::class, 'logout']);
    Route::apiResource('/resources', ResourceController::class);    
    Route::apiResource('/bookings', BookingController::class);
    Route::get('/user/upcoming-booking', [BookingController::class, 'getUserBookings']);
    Route::post('/admin/bookings', [BookingController::class, 'storeForUser']); // Admin booking for other users
    Route::get('/profile', [UserController::class, 'getProfile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/users/{id}/update', [UserController::class, 'update']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/roles', [UserController::class, 'getRoles']);
    Route::patch('user/change-password', [UserController::class, 'changePassword']);
    Route::patch('user/password', [UserController::class, 'changePassword']);
    Route::put('/users/{userId}/role-type', [UserController::class, 'updateUserRoleAndType']);
    Route::get('/dashboard-stats', [DashboardController::class, 'index']);
    Route::get('/dashboard-debug', [DashboardController::class, 'debug']);
    Route::put('/bookings/{booking}/user-cancel', [BookingController::class, 'userCancelBooking']);
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancelBooking']);
    Route::get('/bookings/cancellable', [BookingController::class, 'getCancellableBookings']);
    Route::get('/bookings/{booking}/can-cancel', [BookingController::class, 'checkCancellationEligibility']);
    Route::post('/reservations', [Reservation::class, 'store']); 
    Route::post('/bookings/{id}/approve', [BookingApprovalController::class, 'approve']);
    Route::put('/bookings/{id}/approve', [BookingApprovalController::class, 'approve']);
    Route::get('/resources/{id}/bookings', [ResourceController::class, 'getResourceBookings']);
    Route::post('/bookings/{id}/reject', [BookingApprovalController::class, 'reject']);
    Route::post('/bookings/{id}/cancel', [BookingApprovalController::class, 'cancel']); 
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']); 
    Route::post('/bookings/bulk-approve', [BookingApprovalController::class, 'bulkApprove']);
    Route::get('/reports/resource-utilization', [ReportController::class, 'getResourceUtilization']);
    Route::get('/reports/booking-summary', [ReportController::class, 'getBookingSummary']);
    Route::get('/reports/upcoming-bookings', [ReportController::class, 'getUpcomingBookings']);
    Route::get('/reports/canceled-bookings', [ReportController::class, 'getCanceledBookings']);
    Route::post('/bookings/{id}/in-use', [BookingApprovalController::class, 'inUseApproval']);
    Route::post('/bookings/{id}/complete', [BookingApprovalController::class, 'complete']);
    Route::prefix('timetable')->group(function () {
        Route::post('/import', [TimetableController::class, 'import']);
        Route::get('/', [TimetableController::class, 'getTimetable']);
        Route::post('/check-conflicts', [TimetableController::class, 'checkConflicts']);
    });

    Route::post('/resource-issues', [ResourceIssueController::class, 'store']);
    Route::get('/resource-issues', [ResourceIssueController::class, 'index']);
    Route::get('/resource-issues/{issue}', [ResourceIssueController::class, 'show']);
    Route::get('/resource-issues/{issue}/photo', [ResourceIssueController::class, 'servePhoto']);
    Route::get('/bookings/{booking}/document', [BookingController::class, 'serveDocument']);
    Route::get('/bookings/{booking}/document-metadata', [BookingController::class, 'getDocumentMetadata']);
    Route::get('/bookings/{booking}/download-document', [BookingController::class, 'downloadDocument']);
    Route::put('/resource-issues/{issue}', [ResourceIssueController::class, 'update']);
    Route::delete('/resource-issues/{issue}', [ResourceIssueController::class, 'destroy']); 
    
    Route::apiResource('/notifications', NotificationController::class);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']); 
    Route::post('/notifications/{notification}/mark-as-read', [NotificationController::class, 'markAsRead']); // New route
    Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']); 
    Route::apiResource('/news', \App\Http\Controllers\NewsController::class);
    
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware(['auth:sanctum', 'throttle:6,1'])
        ->name('verification.send');

    Route::get('/email/verification-status', [AuthController::class, 'checkVerificationStatus']);
    Route::get('/dashboard/bookings-by-resource-type', [DashboardController::class, 'bookingsByResourceType']);
    Route::get('/dashboard/booking-duration-distribution', [DashboardController::class, 'bookingDurationDistribution']);
    Route::get('/dashboard/cancellation-rate', [DashboardController::class, 'cancellationRate']);
    Route::get('/dashboard/cancellation-trends', [DashboardController::class, 'cancellationTrends']);
    Route::get('/resource-issues/summary-report', [ResourceIssueController::class, 'issueSummaryReport']);
    Route::get('/recommendations/resources', [RecommendationController::class, 'getRecommendations']);
    Route::get('/recommendations/resources/time-based', [RecommendationController::class, 'getTimeBasedRecommendations']);
    Route::get('/recommendations/user/preferences', [RecommendationController::class, 'getUserPreferences']);
    Route::get('/bookings-recent', [BookingController::class, 'recentBookings']);
    Route::get('/bookings/lookup-uuid/{uuid}', [BookingController::class, 'lookupByUuid']);
    Route::get('/resources/lookup-uuid/{uuid}', [ResourceController::class, 'lookupByUuid']);
    Route::get('/resources-trending', [ResourceController::class, 'trending']);
    Route::get('/resources-recently-booked', [ResourceController::class, 'getRecentlyBookedResources']);
    Route::put('/user/preferences', [UserController::class, 'updatePreferences']);
    Route::get('/features', [\App\Http\Controllers\ResourceController::class, 'allFeatures']);
    
    // Security Settings Routes
    Route::prefix('user')->middleware('track.session')->group(function () {
        // 2FA Routes
        Route::post('/2fa/setup', [SecurityController::class, 'setup2FA']);
        Route::post('/2fa/verify', [SecurityController::class, 'verify2FA']);
        Route::delete('/2fa/disable', [SecurityController::class, 'disable2FA']);
        
        // Session Management Routes
        Route::get('/sessions', [SecurityController::class, 'getSessions']);
        Route::delete('/sessions/logout-all', [SecurityController::class, 'logoutAllDevices']);
        Route::delete('/sessions/{sessionId}', [SecurityController::class, 'logoutSession']);
        
        // Privacy Settings Routes
        Route::put('/privacy', [SecurityController::class, 'updatePrivacySettings']);
    });
});

// Email verification route (no authentication required)
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Routes that require email verification
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::post('/bookings/check-availability', [BookingController::class, 'checkAvailability']);
});

// Public email verification routes (no authentication required)
Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationPublic']);

Route::prefix('search')->middleware('auth:sanctum')->group(function () {
    Route::post('/', [BinarySearchController::class, 'search']);
    Route::post('/multi-field', [BinarySearchController::class, 'multiFieldSearch']);
    Route::post('/global', [BinarySearchController::class, 'globalSearch']);
    Route::delete('/cache', [BinarySearchController::class, 'clearCache']);
    Route::post('/perform-multi-search', [BinarySearchController::class, 'performMultiSearch']);
});

// Chatbot endpoint
Route::post('/chatbot', [\App\Http\Controllers\ChatbotController::class, 'handle']);

 


