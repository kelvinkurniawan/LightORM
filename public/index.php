<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\User;

$admins = User::query()
    ->where('name', '=', 'Jane')
    ->orWhere('email', 'LIKE', '%example.com')
    ->whereJson('profile', '$.age', 30)
    ->whereJsonContains('roles', 'admin')
    ->get();

foreach($admins as $admin) {
    echo $admin->name . "<br>";
}