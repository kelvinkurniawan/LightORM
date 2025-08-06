<?php

use KelvinKurniawan\LightORM\Contracts\SeederInterface;
use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Seeding\Factory;

class UserSeeder implements SeederInterface {
    private ?ConnectionInterface $connection = NULL;

    public function setConnection(ConnectionInterface $connection): void {
        $this->connection = $connection;
    }

    public function run(): void {
        echo "🔄 Seeding users...\n";

        // Create 10 fake users
        for($i = 0; $i < 10; $i++) {
            $this->connection->query(
                "INSERT INTO users (name, email, password, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    Factory::fake('name'),
                    Factory::fake('email'),
                    Factory::fake('password'),
                    Factory::fake('boolean'),
                    Factory::fake('dateTime'),
                    Factory::fake('dateTime')
                ]
            );
        }

        echo "✅ Created 10 users\n";
    }

    public function getName(): string {
        return 'UserSeeder';
    }
}
