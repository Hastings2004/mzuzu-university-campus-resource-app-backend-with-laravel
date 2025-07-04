<?php

require_once 'vendor/autoload.php';

use App\Models\User;

// Test creating a user with contact details
echo "Testing User creation with contact details...\n";

try {
    $user = User::create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'user_type' => 'student',
        'email' => 'john.doe@test.com',
        'password' => bcrypt('password123'),
        'identity_number' => 'ID12345678',
        'phone' => '+1234567890',
        'physical_address' => '123 Main Street, City',
        'post_address' => 'P.O. Box 123, City',
        'district' => 'Central District',
        'village' => 'Downtown Village',
    ]);

    echo "✅ User created successfully!\n";
    echo "User ID: " . $user->id . "\n";
    echo "Name: " . $user->first_name . " " . $user->last_name . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Phone: " . $user->phone . "\n";
    echo "Identity Number: " . $user->identity_number . "\n";
    echo "Physical Address: " . $user->physical_address . "\n";
    echo "Post Address: " . $user->post_address . "\n";
    echo "District: " . $user->district . "\n";
    echo "Village: " . $user->village . "\n";

    // Test updating user contact details
    echo "\nTesting User update with contact details...\n";
    
    $user->update([
        'phone' => '+0987654321',
        'physical_address' => '456 New Street, New City',
        'district' => 'New District',
    ]);

    echo "✅ User updated successfully!\n";
    echo "Updated Phone: " . $user->phone . "\n";
    echo "Updated Physical Address: " . $user->physical_address . "\n";
    echo "Updated District: " . $user->district . "\n";

    // Clean up - delete the test user
    $user->delete();
    echo "\n✅ Test user cleaned up successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🎉 Contact fields test completed!\n"; 