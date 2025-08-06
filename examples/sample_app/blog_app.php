<?php

/**
 * Sample Blog Application using LightORM
 * 
 * This demonstrates how to use LightORM in a real project
 */

require_once '../../vendor/autoload.php';

use KelvinKurniawan\LightORM\Core\Database;
use KelvinKurniawan\LightORM\Core\Model;

// Configure database
Database::setConfig([
    'driver'   => 'sqlite',
    'database' => __DIR__ . '/blog.db'
]);

// User Model
class User extends Model {
    protected static string $table = 'users';

    protected array $fillable = [
        'name', 'email', 'password', 'bio'
    ];

    protected array $hidden = [
        'password'
    ];

    protected array $casts = [
        'email_verified_at' => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime'
    ];

    protected array $rules = [
        'name'     => 'required|min:2|max:100',
        'email'    => 'required|email',
        'password' => 'required|min:8'
    ];

    protected bool $timestamps  = TRUE;
    protected bool $softDeletes = TRUE;

    // Scope for active users
    public function scopeActive($query) {
        return $query->where('is_active', TRUE);
    }

    // Scope for verified users
    public function scopeVerified($query) {
        return $query->whereNotNull('email_verified_at');
    }
}

// Post Model
class Post extends Model {
    protected static string $table = 'posts';

    protected array $fillable = [
        'user_id', 'title', 'slug', 'content', 'excerpt', 'status'
    ];

    protected array $casts = [
        'published_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime'
    ];

    protected array $rules = [
        'title'   => 'required|min:5|max:255',
        'content' => 'required|min:10',
        'user_id' => 'required|integer'
    ];

    protected bool $timestamps  = TRUE;
    protected bool $softDeletes = TRUE;

    // Scope for published posts
    public function scopePublished($query) {
        return $query->where('status', 'published')
            ->whereNotNull('published_at');
    }

    // Scope for draft posts
    public function scopeDraft($query) {
        return $query->where('status', 'draft');
    }
}

// Create tables if they don't exist
function setupDatabase() {
    $pdo = Database::getConnection();

    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            bio TEXT,
            is_active BOOLEAN DEFAULT 1,
            email_verified_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL
        )
    ");

    // Posts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT NOT NULL,
            excerpt TEXT,
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            view_count INTEGER DEFAULT 0,
            published_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
}

// Initialize database
setupDatabase();

// Sample usage
echo "=== Blog Application Demo ===\n\n";

// 1. Create users
echo "1. Creating users...\n";

$users = [
    [
        'name'     => 'John Doe',
        'email'    => 'john@blog.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'bio'      => 'A passionate writer and developer.'
    ],
    [
        'name'     => 'Jane Smith',
        'email'    => 'jane@blog.com',
        'password' => password_hash('securepass456', PASSWORD_DEFAULT),
        'bio'      => 'Tech enthusiast and blogger.'
    ]
];

foreach($users as $userData) {
    $user = User::create($userData);
    if($user) {
        echo "   ✓ Created user: {$userData['name']} (ID: {$user->getAttribute('id')})\n";
    } else {
        echo "   ✗ Failed to create user: {$userData['name']}\n";
    }
}

// 2. Create posts
echo "\n2. Creating posts...\n";

$allUsers = User::all();
$posts    = [
    [
        'title'        => 'Getting Started with PHP 8',
        'slug'         => 'getting-started-php-8',
        'content'      => 'PHP 8 introduces many exciting features including named arguments, union types, and more...',
        'excerpt'      => 'Learn about the new features in PHP 8',
        'status'       => 'published',
        'published_at' => date('Y-m-d H:i:s')
    ],
    [
        'title'        => 'Database Design Best Practices',
        'slug'         => 'database-design-best-practices',
        'content'      => 'Good database design is crucial for application performance and maintainability...',
        'excerpt'      => 'Essential tips for designing efficient databases',
        'status'       => 'published',
        'published_at' => date('Y-m-d H:i:s')
    ],
    [
        'title'   => 'Draft: Future of Web Development',
        'slug'    => 'future-web-development',
        'content' => 'This is a draft post about the future trends in web development...',
        'excerpt' => 'Exploring upcoming trends in web development',
        'status'  => 'draft'
    ]
];

foreach($posts as $index => $postData) {
    $postData['user_id'] = $allUsers[$index % count($allUsers)]->getAttribute('id');

    $post = Post::create($postData);
    if($post) {
        echo "   ✓ Created post: {$postData['title']} (ID: {$post->getAttribute('id')})\n";
    } else {
        echo "   ✗ Failed to create post: {$postData['title']}\n";
    }
}

// 3. Query examples
echo "\n3. Running queries...\n";

// Get all published posts
echo "   Published posts:\n";
$publishedPosts = Post::published()->orderBy('created_at', 'desc')->get();
foreach($publishedPosts as $post) {
    echo "     - {$post->getAttribute('title')}\n";
}

// Get draft posts
echo "\n   Draft posts:\n";
$draftPosts = Post::draft()->get();
foreach($draftPosts as $post) {
    echo "     - {$post->getAttribute('title')}\n";
}

// Get active users
echo "\n   Active users:\n";
$activeUsers = User::active()->get();
foreach($activeUsers as $user) {
    echo "     - {$user->getAttribute('name')} ({$user->getAttribute('email')})\n";
}

// 4. Advanced queries
echo "\n4. Advanced queries...\n";

// Search posts by title
echo "   Posts containing 'PHP':\n";
$phpPosts = Post::where('title', 'like', '%PHP%')->get();
foreach($phpPosts as $post) {
    echo "     - {$post->getAttribute('title')}\n";
}

// Count posts per user
echo "\n   Posts per user:\n";
foreach($allUsers as $user) {
    $postCount = Post::where('user_id', '=', $user->getAttribute('id'))->count();
    echo "     - {$user->getAttribute('name')}: {$postCount} posts\n";
}

// 5. Update examples
echo "\n5. Update operations...\n";

// Update a post
$firstPost = Post::first();
if($firstPost) {
    $firstPost->setAttribute('view_count', 150);
    $firstPost->save();
    echo "   ✓ Updated post view count\n";
}

// Update user bio
$firstUser = User::first();
if($firstUser) {
    $firstUser->setAttribute('bio', 'Updated bio: Expert in PHP and web development');
    $firstUser->save();
    echo "   ✓ Updated user bio\n";
}

// 6. Validation examples
echo "\n6. Validation examples...\n";

// Try to create an invalid user
$invalidUser = User::create([
    'name'     => 'A', // Too short
    'email'    => 'invalid-email', // Invalid email
    'password' => '123' // Too short
]);

if(!$invalidUser) {
    echo "   ✓ Invalid user creation properly rejected\n";
}

// Try to create an invalid post
$invalidPost = Post::create([
    'title'   => 'Hi', // Too short
    'content' => 'Short', // Too short
    'user_id' => 999 // Non-existent user
]);

if(!$invalidPost) {
    echo "   ✓ Invalid post creation properly rejected\n";
}

// 7. Transaction example
echo "\n7. Transaction example...\n";

try {
    Database::transaction(function () {
        // Create user and post in single transaction
        $newUser = User::create([
            'name'     => 'Transaction User',
            'email'    => 'transaction@blog.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);

        if(!$newUser) {
            throw new Exception('Failed to create user');
        }

        $newPost = Post::create([
            'user_id'      => $newUser->getAttribute('id'),
            'title'        => 'Transaction Test Post',
            'slug'         => 'transaction-test-post',
            'content'      => 'This post was created in a transaction with the user',
            'status'       => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ]);

        if(!$newPost) {
            throw new Exception('Failed to create post');
        }

        echo "   ✓ Transaction completed successfully\n";
    });
} catch (Exception $e) {
    echo "   ✗ Transaction failed: " . $e->getMessage() . "\n";
}

// 8. Final statistics
echo "\n8. Final statistics...\n";

$totalUsers     = User::count();
$totalPosts     = Post::count();
$publishedCount = Post::published()->count();
$draftCount     = Post::draft()->count();

echo "   📊 Blog Statistics:\n";
echo "     - Total Users: {$totalUsers}\n";
echo "     - Total Posts: {$totalPosts}\n";
echo "     - Published Posts: {$publishedCount}\n";
echo "     - Draft Posts: {$draftCount}\n";

echo "\n=== Blog Application Demo Complete ===\n";
echo "\nDatabase file: " . __DIR__ . "/blog.db\n";
echo "You can examine the database with any SQLite browser.\n";
