<?php

/**
 * Example database configuration
 * 
 * Copy this file and update the values according to your database setup.
 * Use Database::setConfig() to set the configuration in your application.
 */

return [
    'host'     => env('DB_HOST', 'localhost'),
    'dbname'   => env('DB_NAME', 'your_database'),
    'username' => env('DB_USER', 'your_username'),
    'password' => env('DB_PASS', 'your_password'),
];

/**
 * Helper function for environment variables (optional)
 * You can implement your own env() function or use a package like vlucas/phpdotenv
 */
if(!function_exists('env')) {
    function env($key, $default = NULL) {
        $value = $_ENV[$key] ?? getenv($key);
        return $value !== FALSE ? $value : $default;
    }
}
