<?php

/**
 * Test script for the updateUserRoleAndType function
 * 
 * This script demonstrates how to use the new admin function to update user roles and types.
 * 
 * Usage:
 * 1. Make sure you have an admin user in your database
 * 2. Make sure you have the basic roles seeded (admin, staff, student)
 * 3. Run this script to test the functionality
 */

// Example API call using cURL
function testUpdateUserRoleAndType() {
    $adminToken = 'YOUR_ADMIN_TOKEN_HERE'; // Replace with actual admin token
    $userId = 2; // Replace with actual user ID to update
    $roleId = 2; // Replace with actual role ID (1=admin, 2=staff, 3=student)
    
    $url = 'http://your-domain.com/api/users/' . $userId . '/role-type';
    
    $data = [
        'user_type' => 'staff', // Can be 'staff', 'student', or 'admin'
        'role_id' => $roleId
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $adminToken,
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n";
}

// Example usage with JavaScript/fetch
function getJavaScriptExample() {
    return '
// JavaScript example for updating user role and type
async function updateUserRoleAndType(userId, userType, roleId, adminToken) {
    try {
        const response = await fetch(`/api/users/${userId}/role-type`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                "Authorization": `Bearer ${adminToken}`,
                "Accept": "application/json"
            },
            body: JSON.stringify({
                user_type: userType, // "staff", "student", or "admin"
                role_id: roleId
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            console.log("Success:", data);
            return data;
        } else {
            console.error("Error:", data);
            throw new Error(data.message || "Failed to update user role and type");
        }
    } catch (error) {
        console.error("Request failed:", error);
        throw error;
    }
}

// Usage example:
// updateUserRoleAndType(2, "staff", 2, "your-admin-token");
    ';
}

// Example Laravel Tinker commands
function getTinkerExample() {
    return '
// Laravel Tinker examples for testing:

// 1. Create an admin user (if not exists)
$admin = App\Models\User::create([
    "first_name" => "Admin",
    "last_name" => "User", 
    "email" => "admin@example.com",
    "password" => Hash::make("password"),
    "user_type" => "admin"
]);

// 2. Assign admin role to the user
$adminRole = App\Models\Role::where("name", "admin")->first();
$admin->roles()->attach($adminRole->id);

// 3. Create a test user to update
$testUser = App\Models\User::create([
    "first_name" => "Test",
    "last_name" => "User",
    "email" => "test@example.com", 
    "password" => Hash::make("password"),
    "user_type" => "student"
]);

// 4. Assign student role to test user
$studentRole = App\Models\Role::where("name", "student")->first();
$testUser->roles()->attach($studentRole->id);

// 5. Check current roles
echo "Admin roles: " . $admin->roles->pluck("name") . "\n";
echo "Test user roles: " . $testUser->roles->pluck("name") . "\n";

// 6. Test the update function manually
$staffRole = App\Models\Role::where("name", "staff")->first();
$testUser->user_type = "staff";
$testUser->save();
$testUser->roles()->detach();
$testUser->roles()->attach($staffRole->id);
echo "Updated test user roles: " . $testUser->fresh()->roles->pluck("name") . "\n";
    ';
}

// Display examples
echo "=== PHP cURL Example ===\n";
echo "Function: testUpdateUserRoleAndType()\n\n";

echo "=== JavaScript Example ===\n";
echo getJavaScriptExample() . "\n";

echo "=== Laravel Tinker Examples ===\n";
echo getTinkerExample() . "\n";

echo "=== API Endpoint Details ===\n";
echo "URL: PUT /api/users/{userId}/role-type\n";
echo "Headers: Authorization: Bearer {admin_token}\n";
echo "Body: {\n";
echo "  \"user_type\": \"staff|student|admin\",\n";
echo "  \"role_id\": {role_id}\n";
echo "}\n\n";

echo "=== Validation Rules ===\n";
echo "- user_type: required, must be one of: staff, student, admin\n";
echo "- role_id: required, must exist in roles table\n";
echo "- Only admin users can access this endpoint\n";
echo "- Admin cannot modify their own role/type\n\n";

echo "=== Response Format ===\n";
echo "Success (200):\n";
echo "{\n";
echo "  \"success\": true,\n";
echo "  \"message\": \"User role and type updated successfully!\",\n";
echo "  \"user\": {\n";
echo "    \"id\": 2,\n";
echo "    \"first_name\": \"John\",\n";
echo "    \"last_name\": \"Doe\",\n";
echo "    \"email\": \"john@example.com\",\n";
echo "    \"user_type\": \"staff\",\n";
echo "    \"roles\": [...]\n";
echo "  }\n";
echo "}\n\n";

echo "Error (403):\n";
echo "{\n";
echo "  \"success\": false,\n";
echo "  \"message\": \"Unauthorized. Only administrators can update user roles and types.\"\n";
echo "}\n"; 