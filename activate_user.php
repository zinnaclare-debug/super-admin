<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$user = User::where('email', 'test@example.com')->first();
if ($user) {
    $user->update(['is_active' => true]);
    echo "✓ User activated: " . $user->email . "\n";
} else {
    // Create if doesn't exist
    User::create([
        'name' => 'Super Admin',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'role' => 'super_admin',
        'is_active' => true,
    ]);
    echo "✓ User created and activated: test@example.com\n";
}
