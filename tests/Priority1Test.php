<?php

namespace KelvinKurniawan\LightORM\Tests;

use PHPUnit\Framework\TestCase;
use KelvinKurniawan\LightORM\Core\Database;
use KelvinKurniawan\LightORM\Database\DatabaseManager;
use KelvinKurniawan\LightORM\Validation\Validator;
use KelvinKurniawan\LightORM\Events\EventDispatcher;
use KelvinKurniawan\LightORM\Database\Grammar\MySqlGrammar;

class Priority1Test extends TestCase {
    public function testDatabaseManagerConfigurations(): void {
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

    public function testDatabaseManagerGrammar(): void {
        $manager = new DatabaseManager();

        $grammar = $manager->getGrammar('mysql');
        $this->assertInstanceOf(MySqlGrammar::class, $grammar);
    }

    public function testValidatorBasicValidation(): void {
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

    public function testValidatorFailure(): void {
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
    }

    public function testEventDispatcherBasic(): void {
        $dispatcher = new EventDispatcher();
        $called     = FALSE;

        $dispatcher->listen('test.event', function () use (&$called) {
            $called = TRUE;
        });

        $dispatcher->dispatch('test.event');
        $this->assertTrue($called);
    }

    public function testEventDispatcherWithPayload(): void {
        $dispatcher   = new EventDispatcher();
        $receivedData = NULL;

        $dispatcher->listen('test.event', function ($data) use (&$receivedData) {
            $receivedData = $data;
        });

        $testData = ['key' => 'value'];
        $dispatcher->dispatch('test.event', [$testData]);

        $this->assertEquals($testData, $receivedData);
    }

    public function testLegacyDatabaseCompatibility(): void {
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
