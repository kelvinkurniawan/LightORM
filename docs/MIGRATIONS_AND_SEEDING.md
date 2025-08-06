# Migrations and Seeding Guide

LightORM provides a robust migration and seeding system that allows you to version your database schema and populate it with test data.

## Table of Contents

1. [Migrations](#migrations)
2. [Schema Builder](#schema-builder)
3. [Seeding](#seeding)
4. [CLI Commands](#cli-commands)
5. [Examples](#examples)

## Migrations

Migrations are like version control for your database, allowing you to modify your database schema in a structured and repeatable way.

### Creating Migrations

Use the CLI tool to create a new migration:

```bash
php lightorm make:migration create_users_table
```

This creates a migration file with a timestamp prefix in the `migrations/` directory.

### Migration Structure

```php
<?php

use KelvinKurniawan\LightORM\Contracts\MigrationInterface;
use KelvinKurniawan\LightORM\Migration\Blueprint;
use KelvinKurniawan\LightORM\Migration\Schema;

class CreateUsersTable implements MigrationInterface {
    
    public function up(): void {
        Schema::create('users', function(Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    
    public function down(): void {
        Schema::dropIfExists('users');
    }
    
    public function getName(): string {
        return 'create_users_table';
    }
    
    public function getTimestamp(): string {
        return '2025_01_06_120000';
    }
}
```

### Running Migrations

```bash
# Run all pending migrations
php lightorm migrate

# Check migration status
php lightorm migrate:status

# Rollback migrations (default: 1 batch)
php lightorm migrate:rollback

# Rollback specific number of batches
php lightorm migrate:rollback 2
```

## Schema Builder

The Schema builder provides a fluent interface for creating and modifying database tables.

### Available Methods

#### Table Operations

```php
// Create a new table
Schema::create('table_name', function(Blueprint $table) {
    // Define columns here
});

// Modify an existing table
Schema::table('table_name', function(Blueprint $table) {
    // Add or modify columns
});

// Drop a table if it exists
Schema::dropIfExists('table_name');

// Check if table exists
if (Schema::hasTable('table_name')) {
    // Table exists
}

// Check if column exists
if (Schema::hasColumn('table_name', 'column_name')) {
    // Column exists
}
```

#### Column Types

```php
$table->id();                           // Auto-incrementing primary key
$table->string('name', 255);            // VARCHAR column (default length 255)
$table->text('description');            // TEXT column
$table->integer('count');               // INTEGER column
$table->boolean('is_active');           // BOOLEAN column
$table->timestamp('created_at');        // TIMESTAMP column
$table->timestamps();                   // created_at and updated_at columns
```

#### Column Modifiers

```php
$table->string('email')->unique();      // Add unique constraint
$table->string('name')->nullable();     // Allow NULL values
$table->boolean('active')->default(true); // Set default value
$table->integer('sort_order')->default(0);
```

#### Indexes and Constraints

```php
$table->unique('email');                // Add unique index
$table->index('name');                  // Add regular index
```

## Seeding

Seeders allow you to populate your database with test or initial data.

### Creating Seeders

```bash
php lightorm make:seeder UserSeeder
```

This creates a seeder file in the `seeders/` directory.

### Seeder Structure

```php
<?php

use KelvinKurniawan\LightORM\Contracts\SeederInterface;
use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Seeding\Factory;

class UserSeeder implements SeederInterface {
    
    public function run(ConnectionInterface $connection): void {
        echo "🔄 Seeding users...\n";
        
        $factory = new Factory();
        $users = [];
        
        for ($i = 0; $i < 10; $i++) {
            $users[] = [
                'name' => $factory->fake('name'),
                'email' => $factory->fake('email'),
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'is_active' => $factory->fake('boolean'),
                'created_at' => $factory->fake('datetime'),
                'updated_at' => $factory->fake('datetime')
            ];
        }
        
        foreach ($users as $user) {
            $connection->query(
                "INSERT INTO users (name, email, password, is_active, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                array_values($user)
            );
        }
        
        echo "✅ Created " . count($users) . " users\n";
    }
    
    public function getName(): string {
        return 'UserSeeder';
    }
}
```

### Running Seeders

```bash
# Run all seeders
php lightorm seed

# Run specific seeder
php lightorm seed UserSeeder
```

### Factory for Fake Data

The `Factory` class provides realistic fake data generation:

```php
$factory = new Factory();

// Available data types
$factory->fake('name');          // Random full name
$factory->fake('firstName');     // Random first name
$factory->fake('lastName');      // Random last name
$factory->fake('email');         // Random email address
$factory->fake('username');      // Random username
$factory->fake('password');      // Random password
$factory->fake('text');          // Random text (50-200 chars)
$factory->fake('sentence');      // Random sentence
$factory->fake('paragraph');     // Random paragraph
$factory->fake('boolean');       // Random true/false
$factory->fake('integer');       // Random integer (1-1000)
$factory->fake('float');         // Random float
$factory->fake('date');          // Random date (Y-m-d)
$factory->fake('datetime');      // Random datetime (Y-m-d H:i:s)
$factory->fake('time');          // Random time (H:i:s)
$factory->fake('year');          // Random year (1990-2025)
$factory->fake('month');         // Random month (1-12)
$factory->fake('day');           // Random day (1-28)
$factory->fake('phone');         // Random phone number
$factory->fake('address');       // Random address
$factory->fake('city');          // Random city name
$factory->fake('country');       // Random country
$factory->fake('url');           // Random URL
$factory->fake('slug');          // Random slug
$factory->fake('uuid');          // Random UUID
$factory->fake('color');         // Random hex color
```

## CLI Commands

The LightORM CLI tool provides several commands for managing migrations and seeders:

```bash
# Migration commands
php lightorm migrate                    # Run pending migrations
php lightorm migrate:rollback [steps]   # Rollback migrations
php lightorm migrate:status             # Show migration status
php lightorm make:migration <name>      # Create new migration

# Seeding commands
php lightorm seed [seeder]              # Run seeders
php lightorm make:seeder <name>         # Create new seeder

# Help
php lightorm help                       # Show all commands
```

## Examples

### Complete Workflow Example

1. **Create migration for users table:**

```bash
php lightorm make:migration create_users_table
```

2. **Edit the migration file:**

```php
public function up(): void {
    Schema::create('users', function(Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void {
    Schema::dropIfExists('users');
}
```

3. **Run the migration:**

```bash
php lightorm migrate
```

4. **Create a seeder:**

```bash
php lightorm make:seeder UserSeeder
```

5. **Edit the seeder file:**

```php
public function run(ConnectionInterface $connection): void {
    $factory = new Factory();
    
    for ($i = 0; $i < 10; $i++) {
        $connection->query(
            "INSERT INTO users (name, email, password, is_active, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $factory->fake('name'),
                $factory->fake('email'),
                password_hash('password123', PASSWORD_DEFAULT),
                $factory->fake('boolean'),
                $factory->fake('datetime'),
                $factory->fake('datetime')
            ]
        );
    }
}
```

6. **Run the seeder:**

```bash
php lightorm seed
```

### Advanced Example: Posts with Foreign Keys

1. **Create posts migration:**

```bash
php lightorm make:migration create_posts_table
```

2. **Edit the migration:**

```php
public function up(): void {
    Schema::create('posts', function(Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->integer('user_id'); // Foreign key to users
        $table->boolean('is_published')->default(false);
        $table->timestamps();
    });
}
```

3. **Create posts seeder:**

```php
public function run(ConnectionInterface $connection): void {
    $factory = new Factory();
    
    // Get existing user IDs
    $userIds = $connection->query("SELECT id FROM users")->fetchAll(\PDO::FETCH_COLUMN);
    
    for ($i = 0; $i < 20; $i++) {
        $connection->query(
            "INSERT INTO posts (title, content, user_id, is_published, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $factory->fake('sentence'),
                $factory->fake('paragraph'),
                $userIds[array_rand($userIds)], // Random user ID
                $factory->fake('boolean'),
                $factory->fake('datetime'),
                $factory->fake('datetime')
            ]
        );
    }
}
```

## Configuration

Migrations and seeders use your database configuration from `config/config.php`. Make sure your database connection is properly configured before running migrations or seeders.

## Best Practices

1. **Always include both `up()` and `down()` methods** in migrations for rollback support
2. **Use descriptive migration names** that clearly indicate what the migration does
3. **Test migrations in development** before deploying to production
4. **Use seeders for test data only** - not for production data
5. **Keep migrations small and focused** - one migration per table or feature
6. **Use transactions** when running multiple related database operations
7. **Version control your migration files** along with your application code

## Troubleshooting

### Common Issues

1. **Class not found errors**: Make sure your migration files are properly named and the class names match
2. **Database connection errors**: Verify your database configuration in `config/config.php`
3. **Permission errors**: Ensure the migrations and seeders directories are writable
4. **Rollback failures**: Check that your `down()` methods properly reverse the `up()` operations

### Migration Status Shows Wrong Information

If migration status is not accurate, you can manually check the migrations table:

```sql
SELECT * FROM migrations ORDER BY batch, id;
```

This will show you all executed migrations and their batch numbers.
