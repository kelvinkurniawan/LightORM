<?php

use PHPUnit\Framework\TestCase;
use KelvinKurniawan\LightORM\Database\DatabaseManager;
use KelvinKurniawan\LightORM\Migration\MigrationManager;
use KelvinKurniawan\LightORM\Migration\Schema;
use KelvinKurniawan\LightORM\Seeding\SeederManager;
use KelvinKurniawan\LightORM\Seeding\Factory;

class MigrationAndSeedingTest extends TestCase {
    private DatabaseManager  $dbManager;
    private MigrationManager $migrationManager;
    private SeederManager    $seederManager;
    private string           $testDbPath;

    protected function setUp(): void {
        $this->testDbPath = sys_get_temp_dir() . '/lightorm_migration_test_' . uniqid() . '.sqlite';

        $this->dbManager = new DatabaseManager();
        $this->dbManager->addConfiguration('test', [
            'driver'   => 'sqlite',
            'database' => $this->testDbPath
        ]);

        $connection = $this->dbManager->connection('test');
        Schema::setConnection($connection);

        $this->migrationManager = new MigrationManager($connection, 'migrations/');
        $this->seederManager    = new SeederManager($connection, 'seeders/');
    }

    protected function tearDown(): void {
        if(file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
    }

    public function testMigrationTableInitialization(): void {
        $this->migrationManager->initializeMigrationsTable();

        // Check if migrations table exists
        $this->assertTrue(Schema::hasTable('migrations'));
    }

    public function testMigrationExecution(): void {
        // Run migrations
        $result = $this->migrationManager->migrate();

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('migrated', $result);

        // Check if tables were created
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('posts'));

        // Check if columns exist
        $this->assertTrue(Schema::hasColumn('users', 'name'));
        $this->assertTrue(Schema::hasColumn('users', 'email'));
        $this->assertTrue(Schema::hasColumn('posts', 'title'));
        $this->assertTrue(Schema::hasColumn('posts', 'content'));
    }

    public function testMigrationStatus(): void {
        // Run migrations first
        $this->migrationManager->migrate();

        // Check status
        $status = $this->migrationManager->status();

        $this->assertIsArray($status);
        $this->assertNotEmpty($status);

        foreach($status as $migration) {
            $this->assertArrayHasKey('name', $migration);
            $this->assertArrayHasKey('status', $migration);
            $this->assertEquals('executed', $migration['status']);
        }
    }

    public function testMigrationRollback(): void {
        // Run migrations first
        $this->migrationManager->migrate();

        // Verify tables exist
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('posts'));

        // Rollback
        $result = $this->migrationManager->rollback(1);

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('rolled_back', $result);

        // At least one table should be dropped (posts was created last)
        $this->assertFalse(Schema::hasTable('posts'));
    }

    public function testSeederExecution(): void {
        // Run migrations first
        $this->migrationManager->migrate();

        // Run seeders
        $result = $this->seederManager->run();

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('executed', $result);

        // Check if data was inserted
        $connection = $this->dbManager->connection('test');

        $userCount = $connection->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $this->assertGreaterThan(0, $userCount);

        $postCount = $connection->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        $this->assertGreaterThan(0, $postCount);
    }

    public function testSpecificSeeder(): void {
        // Run migrations first
        $this->migrationManager->migrate();

        // Run specific seeder
        $result = $this->seederManager->runSeeder('UserSeeder');

        $this->assertArrayHasKey('message', $result);
        $this->assertContains('UserSeeder', $result['executed']);

        // Check if users were created
        $connection = $this->dbManager->connection('test');
        $userCount  = $connection->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $this->assertEquals(10, $userCount);
    }

    public function testFactoryDataGeneration(): void {
        // Test various fake data types
        $name = Factory::fake('name');
        $this->assertIsString($name);
        $this->assertNotEmpty($name);

        $email = Factory::fake('email');
        $this->assertIsString($email);
        $this->assertStringContainsString('@', $email);

        $number = Factory::fake('number', 1, 10);
        $this->assertIsInt($number);
        $this->assertGreaterThanOrEqual(1, $number);
        $this->assertLessThanOrEqual(10, $number);

        $boolean = Factory::fake('boolean');
        $this->assertIsBool($boolean);

        $uuid = Factory::fake('uuid');
        $this->assertIsString($uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    public function testSchemaOperations(): void {
        // Test table creation
        Schema::create('test_table', function ($table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->integer('count')->default(0);
            $table->boolean('active')->default(TRUE);
            $table->timestamps();
        });

        $this->assertTrue(Schema::hasTable('test_table'));
        $this->assertTrue(Schema::hasColumn('test_table', 'name'));
        $this->assertTrue(Schema::hasColumn('test_table', 'description'));
        $this->assertTrue(Schema::hasColumn('test_table', 'count'));

        // Test table drop
        Schema::drop('test_table');
        $this->assertFalse(Schema::hasTable('test_table'));
    }

    public function testMigrationCreation(): void {
        $migrationPath = $this->migrationManager->createMigration('create_test_table');

        $this->assertFileExists($migrationPath);

        $content = file_get_contents($migrationPath);
        $this->assertStringContainsString('CreateTestTable', $content);
        $this->assertStringContainsString('create_test_table', $content);

        // Cleanup
        unlink($migrationPath);
    }

    public function testSeederCreation(): void {
        $seederPath = $this->seederManager->createSeeder('TestSeeder');

        $this->assertFileExists($seederPath);

        $content = file_get_contents($seederPath);
        $this->assertStringContainsString('TestSeeder', $content);
        $this->assertStringContainsString('SeederInterface', $content);

        // Cleanup
        unlink($seederPath);
    }

    public function testCompleteWorkflow(): void {
        echo "\n=== Testing Complete Migration & Seeding Workflow ===\n";

        // 1. Check initial state
        echo "1. Checking initial state...\n";
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('posts'));

        // 2. Run migrations
        echo "2. Running migrations...\n";
        $migrationResult = $this->migrationManager->migrate();
        echo "   ✓ Migration result: {$migrationResult['message']}\n";

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('posts'));

        // 3. Check migration status
        echo "3. Checking migration status...\n";
        $status = $this->migrationManager->status();
        echo "   ✓ Found " . count($status) . " migrations\n";

        // 4. Run seeders
        echo "4. Running seeders...\n";
        $seederResult = $this->seederManager->run();
        echo "   ✓ Seeder result: {$seederResult['message']}\n";

        // 5. Verify data
        echo "5. Verifying seeded data...\n";
        $connection = $this->dbManager->connection('test');

        $userCount = $connection->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $postCount = $connection->query("SELECT COUNT(*) FROM posts")->fetchColumn();

        echo "   ✓ Users created: {$userCount}\n";
        echo "   ✓ Posts created: {$postCount}\n";

        $this->assertGreaterThan(0, $userCount);
        $this->assertGreaterThan(0, $postCount);

        // 6. Test rollback (skip for this test to avoid complexity)
        echo "6. Workflow completed successfully!\n";

        echo "=== Complete Workflow Test Passed! ===\n\n";
    }
}
