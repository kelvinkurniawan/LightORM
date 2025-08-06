# LightORM

A lightweight, modern ORM for PHP with Active Record pattern, query builder, soft deletes, and advanced features.

## Features

- 🚀 **Active Record Pattern** - Intuitive object-relational mapping
- 🔍 **Advanced Query Builder** - Fluent interface for complex queries
- 🗑️ **Soft Deletes** - Preserve data with soft delete functionality
- ⏰ **Automatic Timestamps** - Auto-managed created_at and updated_at
- ✅ **Model Validation** - Built-in validation with custom rules
- 🎯 **Event System** - Model lifecycle hooks
- 🔄 **Attribute Casting** - Automatic type conversion
- 🛡️ **Mass Assignment Protection** - Secure fillable/guarded attributes
- 📦 **Query Scopes** - Reusable query constraints
- 💾 **Cache Integration** - Query result caching
- 🔄 **Database Transactions** - Transaction support

## Installation

Install via Composer:

```bash
composer require kelvinkurniawan/lightorm
```

## Quick Start

### 1. Configuration

First, configure your database connection:

```php
<?php
require_once 'vendor/autoload.php';

use KelvinKurniawan\LightORM\Core\Database;

// Set database configuration
Database::setConfig([
    'host'     => 'localhost',
    'dbname'   => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
]);
```

### 2. Create a Model

```php
<?php
use KelvinKurniawan\LightORM\Core\Model;

class User extends Model {
    protected static string $table = 'users';
    
    protected array $fillable = [
        'name', 'email', 'password'
    ];
    
    protected array $hidden = [
        'password'
    ];
    
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean'
    ];
}
```

### 3. Basic Usage

```php
// Create new record
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// Or using mass assignment
$user = User::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);

// Find records
$user = User::find(1);
$users = User::all();
$activeUsers = User::where('is_active', true)->get();

// Update records
$user = User::find(1);
$user->name = 'Updated Name';
$user->save();

// Delete records
$user = User::find(1);
$user->delete();

// Query builder
$users = User::query()
    ->select(['name', 'email'])
    ->where('is_active', '=', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

## Advanced Features

### Soft Deletes

```php
class User extends Model {
    protected bool $softDeletes = true;
}

// Soft delete
$user->delete(); // Sets deleted_at timestamp

// Include soft deleted records
$users = User::query()->withTrashed()->get();

// Only soft deleted records
$deletedUsers = User::query()->onlyTrashed()->get();

// Restore soft deleted record
$user->restore();

// Permanently delete
$user->forceDelete();
```

### Validation

```php
class User extends Model {
    protected array $rules = [
        'name' => 'required|min:2|max:100',
        'email' => 'required|email|unique:users',
        'age' => 'numeric|min:18'
    ];
    
    protected array $messages = [
        'email.unique' => 'This email address is already taken.',
        'age.min' => 'You must be at least 18 years old.'
    ];
}

// Validation happens automatically on save()
$user = new User();
$user->name = 'J'; // Too short, will fail validation
if (!$user->save()) {
    $errors = $user->getValidationErrors();
}
```

### Event Hooks

```php
class User extends Model {
    protected function onCreating() {
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
    }
    
    protected function onCreated() {
        // Send welcome email
    }
    
    protected function onUpdating() {
        // Log changes
    }
}
```

### Query Scopes

```php
class User extends Model {
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }
    
    public function scopeByRole($query, $role) {
        return $query->where('role', $role);
    }
}

// Usage
$activeUsers = User::query()->active()->get();
$admins = User::query()->active()->byRole('admin')->get();
```

### Attribute Casting

```php
class User extends Model {
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'json',
        'score' => 'float'
    ];
}

// Automatic casting
$user = User::find(1);
$user->is_active; // Returns boolean true/false
$user->email_verified_at; // Returns DateTime object
$user->metadata; // Returns array from JSON
```

## Database Schema Requirements

For full functionality, your tables should include these columns:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL
);
```

## Configuration Options

You can customize model behavior through properties:

```php
class User extends Model {
    // Table name (auto-detected from class name if not set)
    protected static string $table = 'users';
    
    // Primary key column
    protected string $primaryKey = 'id';
    
    // Enable/disable timestamps
    protected bool $timestamps = true;
    
    // Custom timestamp column names
    protected string $createdAt = 'created_at';
    protected string $updatedAt = 'updated_at';
    
    // Enable soft deletes
    protected bool $softDeletes = true;
    protected string $deletedAt = 'deleted_at';
    
    // Mass assignment protection
    protected array $fillable = ['name', 'email'];
    protected array $guarded = ['id', 'password'];
    
    // Hide attributes in serialization
    protected array $hidden = ['password'];
    protected array $visible = ['name', 'email'];
}
```

## Requirements

- PHP 7.4 or higher
- PDO extension
- MySQL database

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

**Kelvin Kurniawan**
- Email: kelvin@aksarastudio.tech
- GitHub: [@kelvinkurniawan](https://github.com/kelvinkurniawan)
