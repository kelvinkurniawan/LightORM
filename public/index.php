<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\User;

// Create new user
$user = new User([
    'name'  => 'Jane Doe',
    'email' => 'jane@example.com'
]);
$user->save();

// Read all users
$users = User::all();
foreach($users as $u) {
    echo "ID: {$u->id}, Name: {$u->name}, Email: {$u->email}<br>";
}

// Update user
$editUser = User::find(1);
if($editUser) {
    $editUser->name = 'Updated Jane';
    $editUser->save();
}

// Delete user
$deleteUser = User::find(2);
if($deleteUser) {
    $deleteUser->delete();
}
