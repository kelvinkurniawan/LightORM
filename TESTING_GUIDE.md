# LightORM Deep Testing Guide

## 🧪 Comprehensive Testing Strategy

### 1. **Unit Tests** (Already implemented)
- Location: `tests/Priority1Test.php`
- Coverage: Core components, validation, events
- Run: `./vendor/bin/phpunit tests/Priority1Test.php`

### 2. **Integration Tests** 
- Location: `tests/Integration/DeepIntegrationTest.php`
- Coverage: Real database operations, performance
- Run: `./vendor/bin/phpunit tests/Integration/DeepIntegrationTest.php`

### 3. **Real-World Application Test**
- Location: `tests/RealWorldTest.php`
- Coverage: Complete application simulation
- Run: `php tests/RealWorldTest.php`

### 4. **Sample Application**
- Location: `examples/sample_app/blog_app.php`
- Coverage: Practical usage with Model patterns
- Run: `php examples/sample_app/blog_app.php`

## 🚀 Quick Test Commands

```bash
# Run all PHPUnit tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit tests/Priority1Test.php
./vendor/bin/phpunit tests/Integration/DeepIntegrationTest.php

# Run real-world simulation
php tests/RealWorldTest.php

# Run sample application
php examples/sample_app/blog_app.php

# Run original demo
php examples/priority1_demo.php
```

## 📊 Testing Scenarios Covered

### Core Functionality
- ✅ Database connections (MySQL, PostgreSQL, SQLite)
- ✅ Query building and execution
- ✅ CRUD operations
- ✅ Transaction handling
- ✅ Validation system
- ✅ Event dispatching

### Performance Testing
- ✅ Bulk insert operations (1000+ records)
- ✅ Complex query performance
- ✅ Transaction performance
- ✅ Memory usage monitoring

### Error Handling
- ✅ Invalid data validation
- ✅ Database connection failures
- ✅ Transaction rollbacks
- ✅ SQL injection prevention

### Real-World Scenarios
- ✅ User registration system
- ✅ Blog post management
- ✅ Activity logging
- ✅ Multi-table relationships
- ✅ Complex reporting queries

## 🔧 Testing in Your Own Project

### Step 1: Install in Your Project

```bash
# Create new project
composer init
composer require kelvinkurniawan/lightorm

# Or add to existing project
composer require kelvinkurniawan/lightorm
```

### Step 2: Basic Setup

```php
<?php
require_once 'vendor/autoload.php';

use KelvinKurniawan\LightORM\Database\DatabaseManager;

// Setup database
$db = new DatabaseManager();
$db->addConfiguration('default', [
    'driver' => 'sqlite',
    'database' => ':memory:' // or file path
]);

// Test connection
try {
    $connection = $db->connection();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
}
```

### Step 3: Test CRUD Operations

```php
use KelvinKurniawan\LightORM\Query\QueryBuilder;

$connection = $db->connection();
$grammar = $db->getGrammar('sqlite');
$builder = new QueryBuilder($connection, $grammar, 'users');

// Create table
$connection->query("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL
    )
");

// Test Insert
$result = $builder->insert([
    'name' => 'Test User',
    'email' => 'test@example.com'
]);
echo $result ? "✓ Insert successful\n" : "✗ Insert failed\n";

// Test Select
$users = $builder->reset()->get();
echo "✓ Found " . count($users) . " users\n";

// Test Update
$builder->reset();
$affected = $builder->where('email', '=', 'test@example.com')
                  ->update(['name' => 'Updated User']);
echo "✓ Updated {$affected} records\n";

// Test Delete
$builder->reset();
$deleted = $builder->where('email', '=', 'test@example.com')->delete();
echo "✓ Deleted {$deleted} records\n";
```

### Step 4: Test Validation

```php
use KelvinKurniawan\LightORM\Validation\Validator;

$validator = new Validator();

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25
];

$rules = [
    'name' => 'required|min:2',
    'email' => 'required|email',
    'age' => 'required|integer|min:18'
];

if ($validator->validate($data, $rules)) {
    echo "✓ Validation passed\n";
} else {
    echo "✗ Validation failed:\n";
    foreach ($validator->errors() as $field => $errors) {
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }
}
```

### Step 5: Test Events

```php
use KelvinKurniawan\LightORM\Events\EventDispatcher;

$events = new EventDispatcher();

// Register listener
$events->listen('user.created', function($user) {
    echo "✓ Event fired: User {$user['name']} created\n";
});

// Dispatch event
$events->dispatch('user.created', [['name' => 'John Doe']]);
```

## 🎯 Production Testing Checklist

### Before Deployment
- [ ] All unit tests pass
- [ ] Integration tests pass with your database
- [ ] Performance tests meet requirements
- [ ] Error handling works correctly
- [ ] Transaction rollbacks work
- [ ] Validation prevents invalid data
- [ ] Events fire correctly
- [ ] Memory usage is acceptable
- [ ] Connection pooling works (if applicable)

### Production Monitoring
- [ ] Query performance monitoring
- [ ] Error rate monitoring
- [ ] Memory usage monitoring
- [ ] Database connection monitoring
- [ ] Transaction success/failure rates

## 🚨 Common Issues & Solutions

### Issue: Tests fail with "Connection refused"
**Solution**: Ensure database server is running or use SQLite for testing

### Issue: Memory usage too high
**Solution**: Use limit() in queries, implement pagination

### Issue: Slow query performance
**Solution**: Add database indexes, optimize query structure

### Issue: Transaction deadlocks
**Solution**: Keep transactions short, use proper ordering

## 📈 Performance Benchmarks

Based on testing with SQLite:
- **Simple SELECT**: < 0.001s
- **Complex JOIN**: < 0.01s  
- **Bulk INSERT (1000 records)**: < 2s
- **Transaction with multiple operations**: < 0.1s

## 🔄 Continuous Testing

```bash
# Create test script for CI/CD
#!/bin/bash
echo "Running LightORM tests..."

# Unit tests
./vendor/bin/phpunit tests/Priority1Test.php || exit 1

# Integration tests  
./vendor/bin/phpunit tests/Integration/DeepIntegrationTest.php || exit 1

# Real-world test
php tests/RealWorldTest.php || exit 1

echo "✅ All tests passed!"
```

## 📝 Custom Test Template

```php
<?php
// tests/MyProjectTest.php

use PHPUnit\Framework\TestCase;
use KelvinKurniawan\LightORM\Database\DatabaseManager;

class MyProjectTest extends TestCase 
{
    private $dbManager;
    
    protected function setUp(): void {
        $this->dbManager = new DatabaseManager();
        // Setup your specific configuration
    }
    
    public function testMySpecificFeature(): void {
        // Your specific tests here
        $this->assertTrue(true);
    }
}
```

---

**Ready for Production?** ✅  
Run all test scenarios above to ensure LightORM works perfectly in your environment!
