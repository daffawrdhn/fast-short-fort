<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Models\User;
use App\Core\Hash;

// Initial setup to run standalone
$_SERVER['REQUEST_METHOD'] = 'GET';

try {
    $user = User::findByEmail('wardhanadty@gmail.com');
    if ($user === null) {
        echo "User not found\n";
        exit;
    }

    echo "Current password hash: " . $user->password_hash . "\n";

    // Test updating password
    $newPassword = "TestPassword123";
    $newHash = Hash::make($newPassword);
    
    echo "Updating password...\n";
    $success = $user->update(['password_hash' => $newHash]);
    
    if ($success) {
        echo "Update successful!\n";
        // Reload user
        $reloadedUser = User::findById($user->id);
        echo "Reloaded password hash: " . $reloadedUser->password_hash . "\n";
        
        // Verify hash
        if (Hash::check($newPassword, $reloadedUser->password_hash)) {
            echo "Verification PASSED!\n";
        } else {
            echo "Verification FAILED!\n";
        }
    } else {
        echo "Update FAILED!\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
