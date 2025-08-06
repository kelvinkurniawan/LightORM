<?php

namespace KelvinKurniawan\LightORM\Contracts;

interface MigrationInterface {
    /**
     * Run the migration
     */
    public function up(): void;

    /**
     * Reverse the migration
     */
    public function down(): void;

    /**
     * Get migration name
     */
    public function getName(): string;

    /**
     * Get migration timestamp
     */
    public function getTimestamp(): string;
}
