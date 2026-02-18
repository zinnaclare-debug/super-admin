<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = 'lytebridgeprofessionalservices@gmail.com';
$password = '12345678=TEN';

$user = User::where('email', $email)->first();
if (!$user) {
    $user = new User();
    $user->email = $email;
}

$user->name = 'Super Admin';
$user->password = Hash::make($password);
$user->role = User::ROLE_SUPER_ADMIN;
$user->school_id = null;
$user->is_active = true;
$user->save();

User::where('email', '!=', $email)->delete();

echo "Super admin is ready: {$email}\n";
echo "Deleted all other users.\n";
