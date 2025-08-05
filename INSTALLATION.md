# Installation and Setup Guide

## Quick Installation

```bash
composer require kelvinkurniawan/lightorm
```

## Database Setup

Create your database tables with the following recommended structure:

```sql
-- Example users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL
);

-- Example posts table with foreign key
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    user_id INT NOT NULL,
    is_published BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## Configuration

### Option 1: Direct Configuration
```php
<?php
require_once 'vendor/autoload.php';

use KelvinKurniawan\LightORM\Core\Database;

Database::setConfig([
    'host'     => 'localhost',
    'dbname'   => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
]);
```

### Option 2: Environment-based Configuration
```php
<?php
require_once 'vendor/autoload.php';

use KelvinKurniawan\LightORM\Core\Database;

Database::setConfig([
    'host'     => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'   => $_ENV['DB_NAME'] ?? 'your_database',
    'username' => $_ENV['DB_USER'] ?? 'your_username',
    'password' => $_ENV['DB_PASS'] ?? 'your_password',
]);
```

## Creating Models

```php
<?php
use KelvinKurniawan\LightORM\Core\Model;

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
        'is_active' => 'boolean'
    ];
    
    protected bool $softDeletes = true;
    
    // Custom scope
    public function scopeVerified($query) {
        return $query->whereNotNull('email_verified_at');
    }
    
    // Event hook
    protected function onCreating() {
        if ($this->password) {
            $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        }
    }
}
```

## Basic Usage Examples

### CRUD Operations
```php
// Create
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret123'
]);

// Read
$user = User::find(1);
$users = User::all();
$activeUsers = User::query()->where('is_active', '=', true)->get();

// Update
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Delete
$user->delete(); // Soft delete if enabled
$user->forceDelete(); // Permanent delete
```

### Query Builder
```php
$users = User::query()
    ->select(['name', 'email'])
    ->where('is_active', '=', true)
    ->where('created_at', '>', '2025-01-01')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->get();
```

### Scopes
```php
$verifiedUsers = User::query()->verified()->get();
```

### Soft Deletes
```php
// Include soft deleted
$allUsers = User::query()->withTrashed()->get();

// Only soft deleted
$deletedUsers = User::query()->onlyTrashed()->get();

// Restore
$user->restore();
```

## Framework Integration

### Laravel
```php
// In a service provider
use KelvinKurniawan\LightORM\Core\Database;

Database::setConfig([
    'host'     => config('database.connections.mysql.host'),
    'dbname'   => config('database.connections.mysql.database'),
    'username' => config('database.connections.mysql.username'),
    'password' => config('database.connections.mysql.password'),
]);
```

### Slim Framework
```php
// In your bootstrap file
$container['db'] = function ($c) {
    $settings = $c->get('settings')['db'];
    \KelvinKurniawan\LightORM\Core\Database::setConfig($settings);
    return \KelvinKurniawan\LightORM\Core\Database::getConnection();
};
```

## Troubleshooting

### Common Issues

1. **"Database configuration not set" error**
   - Make sure to call `Database::setConfig()` before using any models

2. **"Table doesn't exist" error**
   - Verify your table name in the model's `$table` property
   - Check database connection settings

3. **Mass assignment errors**
   - Add fields to the `$fillable` array in your model
   - Or remove them from the `$guarded` array

4. **Validation errors**
   - Check the validation rules in your model
   - Use `$model->getValidationErrors()` to see detailed errors

### Performance Tips

1. Use `select()` to limit columns when you don't need all data
2. Use `limit()` for pagination
3. Index frequently queried columns
4. Use scopes for reusable query logic

## Next Steps

- Read the full [README.md](README.md) for complete documentation
- Check out [examples/](examples/) for more usage patterns
- Contribute to the project via [CONTRIBUTING.md](CONTRIBUTING.md)
