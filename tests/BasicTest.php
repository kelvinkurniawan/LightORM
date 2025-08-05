<?php

namespace KelvinKurniawan\LightORM\Tests;

use PHPUnit\Framework\TestCase;
use KelvinKurniawan\LightORM\Core\Database;
use KelvinKurniawan\LightORM\Core\Model;

class DatabaseTest extends TestCase {
    public function testSetConfig() {
        $config = [
            'host'     => 'localhost',
            'dbname'   => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass'
        ];

        Database::setConfig($config);

        $this->assertEquals($config, Database::getConfig());
    }

    public function testGetConfigBeforeSet() {
        // Reset static state
        Database::setConfig([]);

        $this->assertIsArray(Database::getConfig());
    }
}

// Test Model for testing purposes
class TestUser extends Model {
    protected static string $table = 'users';

    protected array $fillable = [
        'name', 'email'
    ];
}

class ModelTest extends TestCase {
    public function testTableName() {
        $this->assertEquals('users', TestUser::tableName());
    }

    public function testQuery() {
        $query = TestUser::query();
        $this->assertInstanceOf(TestUser::class, $query);
    }

    public function testMakeInstance() {
        $user = TestUser::make();
        $this->assertInstanceOf(TestUser::class, $user);
    }
}
