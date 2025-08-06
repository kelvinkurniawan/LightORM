<?php

use KelvinKurniawan\LightORM\Contracts\MigrationInterface;
use KelvinKurniawan\LightORM\Migration\Blueprint;
use KelvinKurniawan\LightORM\Migration\Schema;

class CreatePostsTable implements MigrationInterface {

    public function up(): void {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('slug')->unique();
            $table->integer('user_id');
            $table->boolean('is_published')->default(FALSE);
            $table->integer('views')->default(0);
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['is_published']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('posts');
    }

    public function getName(): string {
        return 'create_posts_table';
    }

    public function getTimestamp(): string {
        return '2025_01_06_120100';
    }
}
