<?php

use KelvinKurniawan\LightORM\Contracts\MigrationInterface;
use KelvinKurniawan\LightORM\Migration\Blueprint;
use KelvinKurniawan\LightORM\Migration\Schema;

class CreateUsersTable implements MigrationInterface {

    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(TRUE);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }

    public function getName(): string {
        return 'create_users_table';
    }

    public function getTimestamp(): string {
        return '2025_01_06_120000';
    }
}
