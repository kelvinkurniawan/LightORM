<?php

namespace KelvinKurniawan\LightORM\Seeding;

class Factory {
    private static array $generators = [];

    /**
     * Register custom generator
     */
    public static function define(string $name, callable $generator): void {
        self::$generators[$name] = $generator;
    }

    /**
     * Generate fake data
     */
    public static function fake(string $type, ...$args) {
        if(isset(self::$generators[$type])) {
            return call_user_func(self::$generators[$type], ...$args);
        }

        return match ($type) {
            'name'        => self::name(),
            'firstName'   => self::firstName(),
            'lastName'    => self::lastName(),
            'email'       => self::email(),
            'username'    => self::username(),
            'password'    => self::password(),
            'text'        => self::text($args[0] ?? 100),
            'sentence'    => self::sentence($args[0] ?? 10),
            'paragraph'   => self::paragraph($args[0] ?? 5),
            'number'      => self::number($args[0] ?? 1, $args[1] ?? 100),
            'boolean'     => self::boolean(),
            'date'        => self::date($args[0] ?? '-1 year', $args[1] ?? 'now'),
            'dateTime'    => self::dateTime($args[0] ?? '-1 year', $args[1] ?? 'now'),
            'uuid'        => self::uuid(),
            'slug'        => self::slug($args[0] ?? NULL),
            'url'         => self::url(),
            'ipAddress'   => self::ipAddress(),
            'phoneNumber' => self::phoneNumber(),
            'address'     => self::address(),
            'city'        => self::city(),
            'country'     => self::country(),
            'companyName' => self::companyName(),
            default       => throw new \InvalidArgumentException("Unknown fake type: {$type}")
        };
    }

    /**
     * Generate full name
     */
    public static function name(): string {
        return self::firstName() . ' ' . self::lastName();
    }

    /**
     * Generate first name
     */
    public static function firstName(): string {
        $names = [
            'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'James', 'Jessica',
            'Robert', 'Ashley', 'William', 'Amanda', 'Richard', 'Stephanie', 'Charles',
            'Melissa', 'Thomas', 'Nicole', 'Christopher', 'Elizabeth', 'Daniel', 'Helen',
            'Matthew', 'Sharon', 'Anthony', 'Linda', 'Mark', 'Carol', 'Donald', 'Ruth'
        ];
        return $names[array_rand($names)];
    }

    /**
     * Generate last name
     */
    public static function lastName(): string {
        $names = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
            'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
            'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
            'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson'
        ];
        return $names[array_rand($names)];
    }

    /**
     * Generate email
     */
    public static function email(): string {
        $domains  = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'example.com'];
        $username = strtolower(self::firstName() . self::number(10, 99));
        return $username . '@' . $domains[array_rand($domains)];
    }

    /**
     * Generate username
     */
    public static function username(): string {
        return strtolower(self::firstName() . self::number(10, 999));
    }

    /**
     * Generate password hash
     */
    public static function password(string $password = 'password'): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Generate text
     */
    public static function text(int $maxChars = 100): string {
        $words = explode(' ', self::paragraph());
        $text  = '';

        foreach($words as $word) {
            if(strlen($text . ' ' . $word) > $maxChars) {
                break;
            }
            $text .= ($text ? ' ' : '') . $word;
        }

        return $text;
    }

    /**
     * Generate sentence
     */
    public static function sentence(int $wordCount = 10): string {
        $words     = [];
        $baseWords = [
            'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
            'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore',
            'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam', 'quis', 'nostrud',
            'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo'
        ];

        for($i = 0; $i < $wordCount; $i++) {
            $words[] = $baseWords[array_rand($baseWords)];
        }

        return ucfirst(implode(' ', $words)) . '.';
    }

    /**
     * Generate paragraph
     */
    public static function paragraph(int $sentenceCount = 5): string {
        $sentences = [];
        for($i = 0; $i < $sentenceCount; $i++) {
            $sentences[] = self::sentence(rand(6, 15));
        }
        return implode(' ', $sentences);
    }

    /**
     * Generate random number
     */
    public static function number(int $min = 1, int $max = 100): int {
        return rand($min, $max);
    }

    /**
     * Generate boolean
     */
    public static function boolean(): bool {
        return (bool) rand(0, 1);
    }

    /**
     * Generate date
     */
    public static function date(string $from = '-1 year', string $to = 'now'): string {
        $fromTime   = strtotime($from);
        $toTime     = strtotime($to);
        $randomTime = rand($fromTime, $toTime);
        return date('Y-m-d', $randomTime);
    }

    /**
     * Generate datetime
     */
    public static function dateTime(string $from = '-1 year', string $to = 'now'): string {
        $fromTime   = strtotime($from);
        $toTime     = strtotime($to);
        $randomTime = rand($fromTime, $toTime);
        return date('Y-m-d H:i:s', $randomTime);
    }

    /**
     * Generate UUID
     */
    public static function uuid(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Generate slug
     */
    public static function slug(?string $text = NULL): string {
        if(!$text) {
            $text = self::sentence(rand(3, 6));
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
        return trim($slug, '-');
    }

    /**
     * Generate URL
     */
    public static function url(): string {
        $domains = ['example.com', 'test.org', 'demo.net', 'sample.io'];
        $paths   = ['home', 'about', 'contact', 'blog', 'products', 'services'];

        return 'https://' . $domains[array_rand($domains)] . '/' . $paths[array_rand($paths)];
    }

    /**
     * Generate IP address
     */
    public static function ipAddress(): string {
        return rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 255);
    }

    /**
     * Generate phone number
     */
    public static function phoneNumber(): string {
        return '+1-' . rand(100, 999) . '-' . rand(100, 999) . '-' . rand(1000, 9999);
    }

    /**
     * Generate address
     */
    public static function address(): string {
        $numbers = rand(100, 9999);
        $streets = ['Main St', 'Oak Ave', 'Park Dr', 'First St', 'Second Ave', 'Third Blvd'];
        return $numbers . ' ' . $streets[array_rand($streets)];
    }

    /**
     * Generate city
     */
    public static function city(): string {
        $cities = [
            'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia',
            'San Antonio', 'San Diego', 'Dallas', 'San Jose', 'Austin', 'Jacksonville',
            'Fort Worth', 'Columbus', 'Charlotte', 'San Francisco', 'Indianapolis', 'Seattle'
        ];
        return $cities[array_rand($cities)];
    }

    /**
     * Generate country
     */
    public static function country(): string {
        $countries = [
            'United States', 'Canada', 'United Kingdom', 'France', 'Germany', 'Italy',
            'Spain', 'Australia', 'Japan', 'South Korea', 'Brazil', 'Mexico', 'India',
            'China', 'Russia', 'South Africa', 'Nigeria', 'Egypt', 'Turkey', 'Indonesia'
        ];
        return $countries[array_rand($countries)];
    }

    /**
     * Generate company name
     */
    public static function companyName(): string {
        $prefixes = ['Tech', 'Global', 'Digital', 'Smart', 'Advanced', 'Modern', 'Future'];
        $suffixes = ['Solutions', 'Systems', 'Corporation', 'Inc', 'LLC', 'Group', 'Services'];

        return $prefixes[array_rand($prefixes)] . ' ' . $suffixes[array_rand($suffixes)];
    }
}
