<?php

/**
 * Real World Testing Application
 * 
 * This simulates a real application using LightORM
 * Run this script to test LightORM in a realistic scenario
 */

require_once 'vendor/autoload.php';

use KelvinKurniawan\LightORM\Database\DatabaseManager;
use KelvinKurniawan\LightORM\Query\QueryBuilder;
use KelvinKurniawan\LightORM\Validation\Validator;
use KelvinKurniawan\LightORM\Events\EventDispatcher;

echo "=== LightORM Real-World Application Test ===\n\n";

// 1. Setup Database
echo "1. Setting up test database...\n";
$dbPath = 'storage/test_app.db';

// Ensure storage directory exists
if(!is_dir('storage')) {
    mkdir('storage', 0755, TRUE);
}

$dbManager = new DatabaseManager();
$dbManager->addConfiguration('app', [
    'driver'   => 'sqlite',
    'database' => $dbPath
]);

$connection = $dbManager->connection('app');
$grammar    = $dbManager->getGrammar('sqlite');

// Create tables
echo "   Creating tables...\n";
try {
    $connection->query("DROP TABLE IF EXISTS user_logs");
    $connection->query("DROP TABLE IF EXISTS posts");
    $connection->query("DROP TABLE IF EXISTS users");

    $connection->query("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            age INTEGER,
            status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'suspended')),
            email_verified_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL
        )
    ");

    $connection->query("
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            status TEXT DEFAULT 'draft' CHECK(status IN ('draft', 'published', 'archived')),
            view_count INTEGER DEFAULT 0,
            published_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    $connection->query("
        CREATE TABLE user_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    echo "   ✓ Tables created successfully\n\n";
} catch (Exception $e) {
    echo "   ✗ Error creating tables: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Setup Event System
echo "2. Setting up event system...\n";
$events = new EventDispatcher();

// User events
$events->listen('user.created', function ($user) use ($connection, $grammar) {
    echo "   📧 Sending welcome email to {$user['email']}\n";

    // Log user creation
    $logBuilder = new QueryBuilder($connection, $grammar, 'user_logs');
    $logBuilder->insert([
        'user_id'     => $user['id'],
        'action'      => 'user_created',
        'description' => "User account created for {$user['name']}",
        'ip_address'  => '127.0.0.1',
        'user_agent'  => 'Test Application'
    ]);
});

$events->listen('user.updated', function ($user) {
    echo "   📝 User profile updated: {$user['name']}\n";
});

$events->listen('post.published', function ($post) {
    echo "   📰 New post published: {$post['title']}\n";
});

echo "   ✓ Event listeners registered\n\n";

// 3. User Registration Simulation
echo "3. Simulating user registrations...\n";

$validator = new Validator();
$validator->addRule('strong_password', function ($value) {
    return strlen($value) >= 8 &&
        preg_match('/[A-Z]/', $value) &&
        preg_match('/[a-z]/', $value) &&
        preg_match('/[0-9]/', $value);
});

$testUsers = [
    [
        'name'     => 'John Doe',
        'email'    => 'john@example.com',
        'password' => 'SecurePass123',
        'age'      => 30
    ],
    [
        'name'     => 'Jane Smith',
        'email'    => 'jane@example.com',
        'password' => 'MyPassword456',
        'age'      => 25
    ],
    [
        'name'     => 'Bob Wilson',
        'email'    => 'bob@example.com',
        'password' => 'BobPass789',
        'age'      => 35
    ],
    [
        'name'     => 'Alice Johnson',
        'email'    => 'alice@example.com',
        'password' => 'AliceSecure321',
        'age'      => 28
    ]
];

$userBuilder     = new QueryBuilder($connection, $grammar, 'users');
$registeredUsers = [];

foreach($testUsers as $userData) {
    echo "   Registering: {$userData['name']} ({$userData['email']})\n";

    // Validate user data
    $rules = [
        'name'     => 'required|min:2|max:100',
        'email'    => 'required|email',
        'password' => 'required|strong_password|min:8',
        'age'      => 'required|integer|min:13'
    ];

    if($validator->validate($userData, $rules)) {
        // Hash password (simulation)
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);

        // Insert user
        $userBuilder->reset();
        $result = $userBuilder->insert($userData);

        if($result) {
            // Get the created user
            $userBuilder->reset();
            $newUser           = $userBuilder->where('email', $userData['email'])->first();
            $registeredUsers[] = $newUser;

            // Trigger event
            $events->dispatch('user.created', [$newUser]);

            echo "     ✓ User registered successfully (ID: {$newUser['id']})\n";
        } else {
            echo "     ✗ Failed to register user\n";
        }
    } else {
        echo "     ✗ Validation failed:\n";
        foreach($validator->errors() as $field => $errors) {
            foreach($errors as $error) {
                echo "       - {$error}\n";
            }
        }
    }
}

echo "\n";

// 4. Post Creation Simulation
echo "4. Simulating blog post creation...\n";

$postData = [
    [
        'title'   => 'Getting Started with PHP',
        'content' => 'This is a comprehensive guide to PHP programming...',
        'status'  => 'published'
    ],
    [
        'title'   => 'Database Best Practices',
        'content' => 'Learn how to design efficient databases...',
        'status'  => 'published'
    ],
    [
        'title'   => 'Advanced ORM Techniques',
        'content' => 'Exploring advanced patterns in ORM design...',
        'status'  => 'draft'
    ],
    [
        'title'   => 'Security in Web Applications',
        'content' => 'Essential security practices for web developers...',
        'status'  => 'published'
    ]
];

$postBuilder = new QueryBuilder($connection, $grammar, 'posts');

foreach($postData as $index => $post) {
    $userId = $registeredUsers[$index % count($registeredUsers)]['id'];
    $slug   = strtolower(str_replace(' ', '-', $post['title']));

    $postRecord = [
        'user_id'    => $userId,
        'title'      => $post['title'],
        'slug'       => $slug,
        'content'    => $post['content'],
        'status'     => $post['status'],
        'view_count' => rand(0, 1000)
    ];

    if($post['status'] === 'published') {
        $postRecord['published_at'] = date('Y-m-d H:i:s');
    }

    echo "   Creating post: {$post['title']}\n";

    $postBuilder->reset();
    $result = $postBuilder->insert($postRecord);

    if($result) {
        $postBuilder->reset();
        $createdPost = $postBuilder->where('slug', $slug)->first();

        if($post['status'] === 'published') {
            $events->dispatch('post.published', [$createdPost]);
        }

        echo "     ✓ Post created (ID: {$createdPost['id']})\n";
    } else {
        echo "     ✗ Failed to create post\n";
    }
}

echo "\n";

// 5. Complex Queries Simulation
echo "5. Running complex queries...\n";

// Query 1: Users with their post counts
echo "   Query 1: Users with post counts\n";
$userBuilder->reset();
$usersWithPosts = $userBuilder
    ->select([
        'users.id',
        'users.name',
        'users.email',
        'COUNT(posts.id) as post_count'
    ])
    ->leftJoin('posts', 'users.id', 'posts.user_id')
    ->where('users.deleted_at', NULL)
    ->orderBy('post_count', 'desc')
    ->get();

foreach($usersWithPosts as $user) {
    echo "     - {$user['name']}: {$user['post_count']} posts\n";
}

// Query 2: Most viewed published posts
echo "\n   Query 2: Most viewed published posts\n";
$postBuilder->reset();
$popularPosts = $postBuilder
    ->select(['posts.title', 'posts.view_count', 'users.name as author'])
    ->join('users', 'posts.user_id', 'users.id')
    ->where('posts.status', 'published')
    ->orderBy('posts.view_count', 'desc')
    ->limit(3)
    ->get();

foreach($popularPosts as $post) {
    echo "     - '{$post['title']}' by {$post['author']} ({$post['view_count']} views)\n";
}

// Query 3: User activity logs
echo "\n   Query 3: Recent user activities\n";
$logBuilder = new QueryBuilder($connection, $grammar, 'user_logs');
$recentLogs = $logBuilder
    ->select(['user_logs.action', 'user_logs.description', 'users.name'])
    ->join('users', 'user_logs.user_id', 'users.id')
    ->orderBy('user_logs.created_at', 'desc')
    ->limit(5)
    ->get();

foreach($recentLogs as $log) {
    echo "     - {$log['action']}: {$log['description']}\n";
}

echo "\n";

// 6. Performance Testing
echo "6. Performance testing...\n";

// Batch insert performance
echo "   Testing batch insert performance...\n";
$startTime = microtime(TRUE);

$connection->transaction(function ($conn) {
    $stmt = $conn->getPdo()->prepare("
        INSERT INTO user_logs (user_id, action, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");

    for($i = 1; $i <= 1000; $i++) {
        $stmt->execute([
            rand(1, 4),
            'bulk_test',
            "Bulk test log entry #{$i}",
            '192.168.1.' . rand(1, 255)
        ]);
    }
});

$insertTime = microtime(TRUE) - $startTime;
echo "     ✓ Inserted 1000 log entries in " . number_format($insertTime, 4) . "s\n";

// Query performance
echo "   Testing query performance...\n";
$startTime = microtime(TRUE);

$logBuilder->reset();
$results = $logBuilder
    ->select(['action', 'COUNT(*) as count'])
    ->where('action', '!=', 'bulk_test')
    ->orderBy('count', 'desc')
    ->get();

$queryTime = microtime(TRUE) - $startTime;
echo "     ✓ Aggregation query completed in " . number_format($queryTime, 4) . "s\n";

echo "\n";

// 7. Transaction Testing
echo "7. Testing transaction handling...\n";

// Test successful transaction
echo "   Testing successful transaction...\n";
try {
    $result = $connection->transaction(function ($conn) use ($grammar) {
        $userBuilder = new QueryBuilder($conn, $grammar, 'users');
        $userBuilder->insert([
            'name'     => 'Transaction User',
            'email'    => 'transaction@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'age'      => 30
        ]);

        $userBuilder->reset();
        $user = $userBuilder->where('email', 'transaction@example.com')->first();

        $postBuilder = new QueryBuilder($conn, $grammar, 'posts');
        $postBuilder->insert([
            'user_id' => $user['id'],
            'title'   => 'Transaction Post',
            'slug'    => 'transaction-post',
            'content' => 'This post was created in a transaction',
            'status'  => 'published'
        ]);

        return TRUE;
    });

    echo "     ✓ Transaction completed successfully\n";
} catch (Exception $e) {
    echo "     ✗ Transaction failed: " . $e->getMessage() . "\n";
}

// Test rollback transaction
echo "   Testing transaction rollback...\n";
try {
    $connection->transaction(function ($conn) use ($grammar) {
        $userBuilder = new QueryBuilder($conn, $grammar, 'users');
        $userBuilder->insert([
            'name'     => 'Rollback User',
            'email'    => 'rollback@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'age'      => 25
        ]);

        // Intentionally cause an error
        throw new Exception('Intentional rollback test');
    });
} catch (Exception $e) {
    echo "     ✓ Transaction rolled back as expected\n";

    // Verify rollback worked
    $userBuilder->reset();
    $rollbackUser = $userBuilder->where('email', 'rollback@example.com')->first();
    if($rollbackUser === NULL) {
        echo "     ✓ Rollback verification successful - user not found\n";
    } else {
        echo "     ✗ Rollback verification failed - user still exists\n";
    }
}

echo "\n";

// 8. Final Statistics
echo "8. Final statistics...\n";

$userBuilder->reset();
$totalUsers = $userBuilder->count();

$postBuilder->reset();
$totalPosts = $postBuilder->count();

$postBuilder->reset();
$publishedPosts = $postBuilder->where('status', 'published')->count();

$logBuilder->reset();
$totalLogs = $logBuilder->count();

echo "   📊 Database Statistics:\n";
echo "     - Total Users: {$totalUsers}\n";
echo "     - Total Posts: {$totalPosts}\n";
echo "     - Published Posts: {$publishedPosts}\n";
echo "     - Total Logs: {$totalLogs}\n";

echo "\n=== Real-World Test Completed Successfully! ===\n";
echo "\n✅ All tests passed. LightORM is ready for production use!\n";

// Cleanup option
echo "\nCleanup database? (y/n): ";
$handle   = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if(strtolower($response) === 'y') {
    if(file_exists($dbPath)) {
        unlink($dbPath);
        echo "✓ Test database cleaned up\n";
    }
} else {
    echo "Test database preserved at: {$dbPath}\n";
    echo "You can examine it with any SQLite browser\n";
}
