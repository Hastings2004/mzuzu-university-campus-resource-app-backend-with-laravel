<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index()
    {
        // ... authentication and authorization checks ...

        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        if (!$user || $user->user_type !== 'admin') {       
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only administrators can view users.'
            ], 403);
        }

        // If authorized, fetch all users
        $users = User::all();
        $usersArray = $users->map(function($user) {
            return array_merge($user->toArray(), ['uuid' => $user->uuid]);
        });
        Log::info("user info");
        Log::info($usersArray);
        return response()->json([
            "success" => true,
            "users" => $usersArray 
        ]);

    }

    public function getProfile(Request $request)
    {
        try {
            // Ensure the user is authenticated
            if (!Auth::check()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $user = Auth::user();
            $userArray = array_merge($user->toArray(), ['uuid' => $user->uuid]);
            // Return the authenticated user's data
            return response()->json([
                'success' => true,
                'user' => $userArray 
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to fetch user profile', [
                'user_id' => Auth::id(), 
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(), 
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load profile. Please try again.'
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $authenticatedUser = Auth::user();

        try {
            $rules = [
                'first_name' => ['sometimes', 'string', 'max:255'],
                'last_name' => ['sometimes', 'string', 'max:255'],
                'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $authenticatedUser->id],
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
                'identity_number' => ['sometimes', 'string', 'max:255'],
                'phone' => ['sometimes', 'string', 'max:30'],
                'physical_address' => ['sometimes', 'string'],
                'post_address' => ['sometimes', 'string'],
                'district' => ['sometimes', 'string', 'max:255'],
                'village' => ['sometimes', 'string', 'max:255'],
                'age' => ['sometimes']
            ];

            $validatedData = $request->validate($rules);

            if (isset($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            }

            $authenticatedUser->fill($validatedData);
            $authenticatedUser->save();

            $userArray = array_merge($authenticatedUser->toArray(), ['uuid' => $authenticatedUser->uuid]);
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully!',
                'user' => $userArray,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Profile update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during profile update.',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $authenticatedUser = Auth::user();

        if (!$authenticatedUser || !($authenticatedUser instanceof \App\Models\User)) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if ($authenticatedUser->user_type === 'admin' && Auth::id() !== (int)$id) {
            return $this->updateUserRoleAndType($request, $id, true);
        }

        if (Auth::id() !== (int)$id) {
            return response()->json(['message' => 'Unauthorized to update this profile.'], 403);
        }

        try {
            $rules = [
                'first_name' => ['sometimes', 'string', 'max:255'],
                'last_name' => ['sometimes', 'string', 'max:255'],
                'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $authenticatedUser->id],
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
                'identity_number' => ['sometimes', 'string', 'max:255'],
                'phone' => ['sometimes', 'string', 'max:30'],
                'physical_address' => ['sometimes', 'string'],
                'post_address' => ['sometimes', 'string'],
                'district' => ['sometimes', 'string', 'max:255'],
                'village' => ['sometimes', 'string', 'max:255'],
                'age' => ['sometimes']
                // 'user_type' is not updatable by this endpoint; do not include in validation rules
            ];

            $validatedData = $request->validate($rules);

            if (isset($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            }

            $authenticatedUser->fill($validatedData);
            $authenticatedUser->save();

            $userArray = array_merge($authenticatedUser->toArray(), ['uuid' => $authenticatedUser->uuid]);
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully!',
                'user' => $userArray,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Profile update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during profile update.',
            ], 500);
        }
    }

    /**
     * Update user type and role for a specific user (Admin only)
     *
     * @param Request $request
     * @param int $userId
     * @param bool $skipAdminCheck
     * @return JsonResponse
     */
    public function updateUserRoleAndType(Request $request, $userId, $skipAdminCheck = false): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $adminUser = Auth::user();

        if (!$skipAdminCheck && (!$adminUser || $adminUser->user_type !== 'admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only administrators can update user roles and types.'
            ], 403);
        }

        try {
            $validatedData = $request->validate([
                'user_type' => 'required|string|in:staff,student,admin, porters',
                //'role_id' => 'required|integer|exists:roles,id',
            ]);

            $role_id = Role::where('name', $validatedData['user_type'])->first()->id;

            $userToUpdate = User::find($userId);
            if (!$userToUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            // Prevent admin from changing their own role/type (optional security measure)
            if ($userToUpdate->id === $adminUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot modify your own role and type.'
                ], 403);
            }

            // Update the user's type
            $userToUpdate->user_type = $validatedData['user_type'];
            $userToUpdate->save();

            // Update the user's role in the role_user table
            // First, detach all existing roles
            $userToUpdate->roles()->detach();
            
            // Then attach the new role
            $userToUpdate->roles()->attach($role_id);

            // Load the updated user with roles
            $userToUpdate->load('roles');

            // Log the action for audit purposes
            Log::info('User role and type updated by admin', [
                'admin_id' => $adminUser->id,
                'admin_email' => $adminUser->email,
                'target_user_id' => $userToUpdate->id,
                'target_user_email' => $userToUpdate->email,
                'old_user_type' => $userToUpdate->getOriginal('user_type'),
                'new_user_type' => $validatedData['user_type'],
                'new_role_id' => $role_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User role and type updated successfully!',
                'user' => [
                    'id' => $userToUpdate->id,
                    'first_name' => $userToUpdate->first_name,
                    'last_name' => $userToUpdate->last_name,
                    'email' => $userToUpdate->email,
                    'user_type' => $userToUpdate->user_type,
                    'roles' => $userToUpdate->roles,
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update user role and type', [
                'admin_id' => $adminUser->id,
                'target_user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating user role and type.',
            ], 500);
        }
    }

    /**
     * Handle the user password change request.
     *
     * @param  \App\Http\Requests\UpdatePasswordRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(UpdatePasswordRequest $request)
    {
        
        $newPassword = $request->input('password');

        try {
            $user = Auth::user(); 

            if (!$user || !($user instanceof User)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }

            
            $user->password = Hash::make($newPassword);
            $user->save(); 

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully!',
                'user' => $user,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Password change failed for user ' . Auth::id() . ': ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while changing password.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a single user's details (Admin only)
     *
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $adminUser = Auth::user();
        if (!$adminUser || $adminUser->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only administrators can view user details.'
            ], 403);
        }

        $user->load('roles');

        $arrayUser = array_merge($user->toArray(), ['uuid' => $user->uuid]);
        Log::error("user information");
        Log::info($arrayUser);
        return response()->json([
            'success' => true,
            'user' => $arrayUser
        ], 200);
    }

    /**
     * Get all available roles (Admin only)
     *
     * @return JsonResponse
     */
    public function getRoles(): JsonResponse
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $adminUser = Auth::user();
        if (!$adminUser || $adminUser->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only administrators can view roles.'
            ], 403);
        }

        try {
            $roles = Role::all();
            return response()->json([
                'success' => true,
                'roles' => $roles
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch roles', [
                'admin_id' => $adminUser->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching roles.',
            ], 500);
        }
    }

    /**
     * Update the authenticated user's booking preferences.
     */
    public function updatePreferences(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $user = Auth::user();
        $data = $request->validate([
            'categories' => 'array',
            'times' => 'array',
            'features' => 'array',
            'capacity' => 'integer|nullable',
            'locations' => 'array|nullable'
        ]);
        $user->preferences = $data;
        $user->save();
        return response()->json(['success' => true, 'preferences' => $user->preferences]);
    }

    /**
     * Get available resource categories for preferences.
     */
    public function getAvailableCategories()
    {
        $categories = \App\Models\Resource::distinct()->pluck('category')->filter()->values();
        return response()->json(['categories' => $categories]);
    }

    /**
     * Get available features for preferences.
     */
    public function getAvailableFeatures()
    {
        $features = \App\Models\Feature::pluck('name');
        return response()->json(['features' => $features]);
    }

    /**
     * Get available locations for preferences.
     */
    public function getAvailableLocations()
    {
        $locations = \App\Models\Resource::distinct()->pluck('location')->filter()->values();
        return response()->json(['locations' => $locations]);
    }

    /**
     * Get available booking times for preferences.
     * (Static for now, can be made dynamic if needed)
     */
    public function getAvailableTimes()
    {
        $times = [
            '08:00', '09:00', '10:00', '11:00', '12:00',
            '13:00', '14:00', '15:00', '16:00', '17:00',
            '18:00', '19:00', '20:00'
        ];
        return response()->json(['times' => $times]);
    }

}
