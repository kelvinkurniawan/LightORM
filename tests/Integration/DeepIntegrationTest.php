<?php

namespace KelvinKurniawan\LightORM\Tests\Integration;

use PHPUnit\Framework\TestCase;
use KelvinKurniawan\LightORM\Database\DatabaseManager;
use KelvinKurniawan\LightORM\Query\QueryBuilder;
use KelvinKurniawan\LightORM\Database\Grammar\MySqlGrammar;
use KelvinKurniawan\LightORM\Database\Grammar\SqliteGrammar;
use KelvinKurniawan\LightORM\Database\Connections\SqliteConnection;
use PDO;

/**
 * Deep Integration Tests for LightORM
 * Tests actual database operations with SQLite (file-based for safety)
 */
class DeepIntegrationTest extends TestCase {
    private DatabaseManager $dbManager;
    private string          $testDbPath;

    protected function setUp(): void {
        parent::setUp();

        // Create temporary SQLite database for testing
        $this->testDbPath = sys_get_temp_dir() . '/lightorm_test_' . uniqid() . '.db';

        $this->dbManager = new DatabaseManager();
        $this->dbManager->addConfiguration('test', [
            'driver'   => 'sqlite',
            'database' => $this->testDbPath
        ]);

        // Create test tables
        $this->setupTestTables();
    }

    protected function tearDown(): void {
        // Clean up test database
        if(file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        parent::tearDown();
    }

    private function setupTestTables(): void {
        $connection = $this->dbManager->connection('test');

        // Create users table
        $connection->query("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                age INTEGER,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL
            )
        ");

        // Create posts table
        $connection->query("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                published BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Insert sample data
        $connection->query("
            INSERT INTO users (name, email, age, is_active) VALUES 
            ('John Doe', 'john@example.com', 30, 1),
            ('Jane Smith', 'jane@example.com', 25, 1),
            ('Bob Wilson', 'bob@example.com', 35, 0),
            ('Alice Johnson', 'alice@example.com', 28, 1)
        ");

        $connection->query("
            INSERT INTO posts (user_id, title, content, published) VALUES 
            (1, 'First Post', 'This is the first post content', 1),
            (1, 'Second Post', 'This is the second post content', 0),
            (2, 'Jane Post', 'Jane first post content', 1),
            (4, 'Alice Post', 'Alice amazing content', 1)
        ");
    }

    public function testDatabaseConnection(): void {
        $connection = $this->dbManager->connection('test');
        $this->assertInstanceOf(SqliteConnection::class, $connection);

        $pdo = $connection->getPdo();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testQueryBuilderSelect(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');

        // Test basic select
        $users = $queryBuilder->select(['name', 'email'])->get();
        $this->assertCount(4, $users);
        $this->assertArrayHasKey('name', $users[0]);
        $this->assertArrayHasKey('email', $users[0]);
    }

    public function testQueryBuilderWhere(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');

        // Test where clause
        $activeUsers = $queryBuilder->where('is_active', 1)->get();
        $this->assertCount(3, $activeUsers);

        // Test multiple where clauses
        $queryBuilder->reset();
        $youngActiveUsers = $queryBuilder
            ->where('is_active', 1)
            ->where('age', '<', 30)
            ->get();
        $this->assertCount(2, $youngActiveUsers);
    }

    public function testQueryBuilderInsert(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');

        // Test single insert
        $result = $queryBuilder->insert([
            'name'      => 'Test User',
            'email'     => 'test@example.com',
            'age'       => 25,
            'is_active' => 1
        ]);

        $this->assertTrue($result);

        // Verify insert
        $queryBuilder->reset();
        $user = $queryBuilder->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user['name']);
    }

    public function testQueryBuilderUpdate(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');

        // Test update
        $affected = $queryBuilder
            ->where('email', 'john@example.com')
            ->update(['age' => 31]);

        $this->assertEquals(1, $affected);

        // Verify update
        $queryBuilder->reset();
        $user = $queryBuilder->where('email', 'john@example.com')->first();
        $this->assertEquals(31, $user['age']);
    }

    public function testQueryBuilderDelete(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');

        // Insert a test user
        $queryBuilder->reset();
        $insertResult = $queryBuilder->insert([
            'name'  => 'Test Delete User',
            'email' => 'delete_test@example.com',
            'age'   => 99
        ]);
        $this->assertTrue($insertResult);

        // Count after insert
        $queryBuilder->reset();
        $countAfterInsert = $queryBuilder->count();

        // Test delete
        $queryBuilder->reset();
        $affected = $queryBuilder
            ->where('email', 'delete_test@example.com')
            ->delete();

        $this->assertEquals(1, $affected);

        // Count after delete
        $queryBuilder->reset();
        $countAfterDelete = $queryBuilder->count();

        $this->assertEquals($countAfterInsert - 1, $countAfterDelete);
    }

    public function testQueryBuilderJoin(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');

        // Test join
        $results = $queryBuilder
            ->select(['users.name', 'posts.title'])
            ->join('posts', 'users.id', 'posts.user_id')
            ->where('posts.published', 1)
            ->get();

        $this->assertGreaterThan(0, count($results));

        foreach($results as $result) {
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('title', $result);
        }
    }

    public function testTransactions(): void {
        $connection = $this->dbManager->connection('test');

        // Test successful transaction
        $result = $connection->transaction(function ($conn) {
            $stmt = $conn->getPdo()->prepare("INSERT INTO users (name, email, age) VALUES (?, ?, ?)");
            $stmt->execute(['Transaction User 1', 'trans1@example.com', 30]);
            $stmt->execute(['Transaction User 2', 'trans2@example.com', 25]);
            return TRUE;
        });

        $this->assertTrue($result);

        // Verify both users were inserted
        $grammar      = $this->dbManager->getGrammar('sqlite');
        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');

        $user1 = $queryBuilder->where('email', 'trans1@example.com')->first();
        $this->assertNotNull($user1);

        $queryBuilder->reset();
        $user2 = $queryBuilder->where('email', 'trans2@example.com')->first();
        $this->assertNotNull($user2);
    }

    public function testTransactionRollback(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        // Count users before transaction
        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');
        $countBefore  = $queryBuilder->count();

        // Test failed transaction (should rollback)
        try {
            $connection->transaction(function ($conn) {
                $stmt = $conn->getPdo()->prepare("INSERT INTO users (name, email, age) VALUES (?, ?, ?)");
                $stmt->execute(['Rollback User 1', 'rollback1@example.com', 30]);

                // This should cause a rollback
                throw new \Exception('Intentional error for rollback test');
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        // Verify no users were added (rollback successful)
        $queryBuilder->reset();
        $countAfter = $queryBuilder->count();
        $this->assertEquals($countBefore, $countAfter);

        // Verify user was not inserted
        $queryBuilder->reset();
        $user = $queryBuilder->where('email', 'rollback1@example.com')->first();
        $this->assertNull($user);
    }

    public function testComplexQuery(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');

        // Complex query with multiple conditions, joins, and ordering
        $results = $queryBuilder
            ->select(['users.name', 'users.email', 'posts.title', 'posts.published'])
            ->join('posts', 'users.id', 'posts.user_id')
            ->where('users.is_active', 1)
            ->where('users.age', '>', 25)
            ->orderBy('users.name', 'asc')
            ->orderBy('posts.title', 'desc')
            ->limit(10)
            ->get();

        $this->assertIsArray($results);

        // Verify results are ordered correctly
        if(count($results) > 1) {
            for($i = 1; $i < count($results); $i++) {
                $this->assertLessThanOrEqual(
                    $results[$i]['name'],
                    $results[$i - 1]['name']
                );
            }
        }
    }

    public function testPerformanceWithLargeDataset(): void {
        $connection = $this->dbManager->connection('test');
        $grammar    = $this->dbManager->getGrammar('sqlite');

        // Insert 1000 test records
        $startTime = microtime(TRUE);

        $connection->transaction(function ($conn) {
            $stmt = $conn->getPdo()->prepare("INSERT INTO users (name, email, age, is_active) VALUES (?, ?, ?, ?)");

            for($i = 1; $i <= 1000; $i++) {
                $stmt->execute([
                    "Perf User {$i}",
                    "perf{$i}@example.com",
                    rand(18, 60),
                    rand(0, 1)
                ]);
            }
        });

        $insertTime = microtime(TRUE) - $startTime;

        // Test query performance
        $startTime = microtime(TRUE);

        $queryBuilder = new QueryBuilder($connection, $grammar, 'users');
        $results      = $queryBuilder
            ->where('is_active', 1)
            ->where('age', '>', 30)
            ->orderBy('name')
            ->limit(50)
            ->get();

        $queryTime = microtime(TRUE) - $startTime;

        // Performance assertions (adjust thresholds as needed)
        $this->assertLessThan(5.0, $insertTime, "Insert performance too slow: {$insertTime}s");
        $this->assertLessThan(1.0, $queryTime, "Query performance too slow: {$queryTime}s");

        // Verify results
        $this->assertLessThanOrEqual(50, count($results));

        echo "\nPerformance Results:\n";
        echo "- Insert 1000 records: " . number_format($insertTime, 4) . "s\n";
        echo "- Complex query: " . number_format($queryTime, 4) . "s\n";
        echo "- Query results: " . count($results) . " records\n";
    }
}
