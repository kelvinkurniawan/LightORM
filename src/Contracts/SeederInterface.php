<?php

namespace KelvinKurniawan\LightORM\Contracts;

interface SeederInterface {
    /**
     * Run the seeder
     */
    public function run(): void;

    /**
     * Get seeder name
     */
    public function getName(): string;
}
