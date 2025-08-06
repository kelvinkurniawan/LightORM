<?php

namespace KelvinKurniawan\LightORM\Tests;

use PHPUnit\Framework\TestCase;
use KelvinKurniawan\LightORM\Core\Database;
use KelvinKurniawan\LightORM\Database\DatabaseManager;
use KelvinKurniawan\LightORM\Validation\Validator;
use KelvinKurniawan\LightORM\Events\EventDispatcher;
use KelvinKurniawan\LightORM\Query\QueryBuilder;
use KelvinKurniawan\LightORM\Database\Grammar\MySqlGrammar;
use KelvinKurniawan\LightORM\Database\Connections\MySqlConnection;

class DatabaseManagerTest extends TestCase {
    public function testSetConfigurations(): void {
        $manager = new DatabaseManager();
        $config  = [
            'default' => [
                'driver'   => 'mysql',
                'host'     => 'localhost',
                'dbname'   => 'test_db',
                'username' => 'test_user',
                'password' => 'test_pass'
            ]
        ];

        $manager->setConfigurations($config);
        $this->assertEquals('default', $manager->getDefaultConnection());
        $this->assertTrue($manager->hasConnection('default'));
    }

    public function testAddConfiguration(): void {
        $manager = new DatabaseManager();
        $config  = [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'dbname'   => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass'
        ];

        $manager->addConfiguration('test', $config);
        $this->assertTrue($manager->hasConnection('test'));
        $this->assertEquals('test', $manager->getDefaultConnection());
    }

    public function testGetGrammar(): void {
        $manager = new DatabaseManager();

        $grammar = $manager->getGrammar('mysql');
        $this->assertInstanceOf(MySqlGrammar::class, $grammar);
    }
}

class ValidatorTest extends TestCase {
    public function testBasicValidation(): void {
        $data = [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'age'   => 25
        ];

        $rules = [
            'name'  => 'required|min:2',
            'email' => 'required|email',
            'age'   => 'required|numeric|min:18'
        ];

        $validator = new Validator();
        $isValid   = $validator->validate($data, $rules);

        $this->assertTrue($isValid);
        $this->assertEmpty($validator->errors());
    }

    public function testValidationFailure(): void {
        $data = [
            'name'  => '',
            'email' => 'invalid-email',
            'age'   => 15
        ];

        $rules = [
            'name'  => 'required',
            'email' => 'required|email',
            'age'   => 'required|numeric|min:18'
        ];

        $validator = new Validator();
        $isValid   = $validator->validate($data, $rules);

        $this->assertFalse($isValid);
        $this->assertNotEmpty($validator->errors());
        $this->assertArrayHasKey('name', $validator->errors());
        $this->assertArrayHasKey('email', $validator->errors());
        $this->assertArrayHasKey('age', $validator->errors());
    }

    public function testCustomValidationRule(): void {
        $validator = new Validator();

        // Add custom rule
        $validator->addRule('custom_rule', function ($value) {
            return $value === 'valid';
        });

        $data  = ['field' => 'valid'];
        $rules = ['field' => 'custom_rule'];

        $this->assertTrue($validator->validate($data, $rules));

        $data = ['field' => 'invalid'];
        $this->assertFalse($validator->validate($data, $rules));
    }
}

class EventDispatcherTest extends TestCase {
    public function testBasicEventDispatch(): void {
        $dispatcher = new EventDispatcher();
        $called     = FALSE;

        $dispatcher->listen('test.event', function () use (&$called) {
            $called = TRUE;
        });

        $dispatcher->dispatch('test.event');
        $this->assertTrue($called);
    }

    public function testEventWithPayload(): void {
        $dispatcher   = new EventDispatcher();
        $receivedData = NULL;

        $dispatcher->listen('test.event', function ($data) use (&$receivedData) {
            $receivedData = $data;
        });

        $testData = ['key' => 'value'];
        $dispatcher->dispatch('test.event', [$testData]);

        $this->assertEquals($testData, $receivedData);
    }

    public function testEventCancellation(): void {
        $dispatcher           = new EventDispatcher();
        $secondListenerCalled = FALSE;

        $dispatcher->listen('test.event', function () {
            return FALSE; // Cancel event
        });

        $dispatcher->listen('test.event', function () use (&$secondListenerCalled) {
            $secondListenerCalled = TRUE;
        });

        $result = $dispatcher->dispatch('test.event');

        $this->assertFalse($result);
        $this->assertFalse($secondListenerCalled);
    }
}

class QueryBuilderTest extends TestCase {
    protected QueryBuilder $queryBuilder;

    protected function setUp(): void {
        // Mock connection and grammar for testing
        $connection = $this->createMock(\KelvinKurniawan\LightORM\Contracts\ConnectionInterface::class);
        $grammar    = new MySqlGrammar();

        $this->queryBuilder = new QueryBuilder($connection, $grammar, 'users');
    }

    public function testSelectClause(): void {
        $sql = $this->queryBuilder
            ->select(['name', 'email'])
            ->toSql();

        $this->assertStringContainsString('select `name`, `email`', $sql);
        $this->assertStringContainsString('from `users`', $sql);
    }

    public function testWhereClause(): void {
        $sql = $this->queryBuilder
            ->where('name', 'John')
            ->toSql();

        $this->assertStringContainsString('where `name` = ?', $sql);
        $this->assertEquals(['John'], $this->queryBuilder->getBindings());
    }

    public function testMultipleWhereClauses(): void {
        $sql = $this->queryBuilder
            ->where('name', 'John')
            ->where('age', '>', 18)
            ->toSql();

        $this->assertStringContainsString('where `name` = ? and `age` > ?', $sql);
        $this->assertEquals(['John', 18], $this->queryBuilder->getBindings());
    }

    public function testOrderByClause(): void {
        $sql = $this->queryBuilder
            ->orderBy('name', 'desc')
            ->toSql();

        $this->assertStringContainsString('order by `name` DESC', $sql);
    }

    public function testLimitAndOffset(): void {
        $sql = $this->queryBuilder
            ->limit(10)
            ->offset(20)
            ->toSql();

        $this->assertStringContainsString('limit 10', $sql);
        $this->assertStringContainsString('offset 20', $sql);
    }

    public function testJoinClause(): void {
        $sql = $this->queryBuilder
            ->join('posts', 'users.id', 'posts.user_id')
            ->toSql();

        $this->assertStringContainsString('INNER JOIN `posts` ON `users`.`id` = `posts`.`user_id`', $sql);
    }
}

// Legacy compatibility test
class LegacyDatabaseTest extends TestCase {
    public function testSetConfig(): void {
        $config = [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'dbname'   => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass'
        ];

        Database::setConfig($config);
        $manager = Database::getManager();

        $this->assertTrue($manager->hasConnection('default'));
    }
}

// Helper functions test
class HelperFunctionsTest extends TestCase {
    public function testLightormDbHelper(): void {
        $manager = lightorm_db();
        $this->assertInstanceOf(DatabaseManager::class, $manager);
    }

    public function testLightormValidatorHelper(): void {
        $validator = lightorm_validator(['name' => 'John'], ['name' => 'required']);
        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertTrue($validator->passes());
    }

    public function testLightormEventsHelper(): void {
        $dispatcher = lightorm_events();
        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }
}
