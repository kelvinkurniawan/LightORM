<?php

/**
 * Example usage of LightORM
 * 
 * This file demonstrates how to use the LightORM package
 * in your project after installing via Composer.
 */

require_once 'vendor/autoload.php';

use KelvinKurniawan\LightORM\Core\Database;
use KelvinKurniawan\LightORM\Core\Model;

// Configure database connection
Database::setConfig([
    'host'     => 'localhost',
    'dbname'   => 'test_db',
    'username' => 'root',
    'password' => '',
]);

// Example User model
class User extends Model {
    protected static string $table = 'users';

    protected array $fillable = [
        'name', 'email', 'password', 'is_active'
    ];

    protected array $hidden = [
        'password'
    ];

    protected array $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean'
    ];

    protected array $rules = [
        'name'  => 'required|min:2|max:100',
        'email' => 'required|email',
    ];

    // Enable soft deletes
    protected bool $softDeletes = TRUE;

    // Scope for active users
    public function scopeActive($query) {
        return $query->where('is_active', TRUE);
    }

    // Event hook - hash password before creating
    protected function onCreating() {
        if($this->password) {
            $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        }
    }
}

try {
    // Create a new user
    $user = User::create([
        'name'      => 'John Doe',
        'email'     => 'john@example.com',
        'password'  => 'secret123',
        'is_active' => TRUE
    ]);

    echo "Created user: {$user->name}\n";

    // Find users
    $users = User::query()->active()->get();
    echo "Found " . count($users) . " active users\n";

    // Update user
    $user->name = 'John Smith';
    $user->save();
    echo "Updated user name to: {$user->name}\n";

    // Soft delete
    $user->delete();
    echo "Soft deleted user\n";

    // Find with trashed
    $trashedUsers = User::query()->onlyTrashed()->get();
    echo "Found " . count($trashedUsers) . " deleted users\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
