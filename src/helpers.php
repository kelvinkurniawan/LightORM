<?php

/**
 * Helper functions for LightORM
 */

if(!function_exists('lightorm_db')) {
    /**
     * Get database manager instance
     */
    function lightorm_db(): \KelvinKurniawan\LightORM\Database\DatabaseManager {
        return \KelvinKurniawan\LightORM\Core\Database::getManager();
    }
}

if(!function_exists('lightorm_validator')) {
    /**
     * Create a new validator instance
     */
    function lightorm_validator(array $data, array $rules, array $messages = []): \KelvinKurniawan\LightORM\Validation\Validator {
        return \KelvinKurniawan\LightORM\Validation\Validator::make($data, $rules, $messages);
    }
}

if(!function_exists('lightorm_events')) {
    /**
     * Get event dispatcher instance
     */
    function lightorm_events(): \KelvinKurniawan\LightORM\Events\EventDispatcher {
        static $dispatcher = NULL;

        if($dispatcher === NULL) {
            $dispatcher = new \KelvinKurniawan\LightORM\Events\EventDispatcher();
        }

        return $dispatcher;
    }
}
