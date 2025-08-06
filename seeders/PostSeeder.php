<?php

use KelvinKurniawan\LightORM\Contracts\SeederInterface;
use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Seeding\Factory;

class PostSeeder implements SeederInterface {
    private ?ConnectionInterface $connection = NULL;

    public function setConnection(ConnectionInterface $connection): void {
        $this->connection = $connection;
    }

    public function run(): void {
        echo "🔄 Seeding posts...\n";

        // Get user IDs
        $users = $this->connection->query("SELECT id FROM users")->fetchAll(\PDO::FETCH_COLUMN);

        if(empty($users)) {
            echo "⚠️  No users found. Please run UserSeeder first.\n";
            return;
        }

        // Create 20 fake posts
        for($i = 0; $i < 20; $i++) {
            $title = Factory::fake('sentence', 5);
            $title = rtrim($title, '.');

            $this->connection->query(
                "INSERT INTO posts (title, content, slug, user_id, is_published, views, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $title,
                    Factory::fake('paragraph', 3),
                    Factory::fake('slug', $title),
                    $users[array_rand($users)],
                    Factory::fake('boolean'),
                    Factory::fake('number', 0, 1000),
                    Factory::fake('dateTime'),
                    Factory::fake('dateTime')
                ]
            );
        }

        echo "✅ Created 20 posts\n";
    }

    public function getName(): string {
        return 'PostSeeder';
    }
}
