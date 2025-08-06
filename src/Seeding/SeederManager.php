<?php

namespace KelvinKurniawan\LightORM\Seeding;

use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Contracts\SeederInterface;
use Exception;

class SeederManager {
    private ConnectionInterface $connection;
    private string              $seedersPath;

    public function __construct(ConnectionInterface $connection, string $seedersPath = 'seeders/') {
        $this->connection  = $connection;
        $this->seedersPath = rtrim($seedersPath, '/') . '/';
    }

    /**
     * Run all seeders
     */
    public function run(): array {
        $seeders  = $this->getAllSeeders();
        $executed = [];

        foreach($seeders as $seeder) {
            try {
                $seeder->run();
                $executed[] = $seeder->getName();
            } catch (Exception $e) {
                throw new Exception("Seeder failed: {$seeder->getName()}. Error: " . $e->getMessage());
            }
        }

        return [
            'message'  => 'Seeding completed successfully',
            'executed' => $executed
        ];
    }

    /**
     * Run specific seeder
     */
    public function runSeeder(string $name): array {
        $seeder = $this->loadSeeder($name);

        try {
            $seeder->run();
            return [
                'message'  => 'Seeder completed successfully',
                'executed' => [$seeder->getName()]
            ];
        } catch (Exception $e) {
            throw new Exception("Seeder failed: {$seeder->getName()}. Error: " . $e->getMessage());
        }
    }

    /**
     * Create new seeder file
     */
    public function createSeeder(string $name): string {
        $className = $this->studlyCase($name);
        $filename  = "{$className}.php";
        $filepath  = $this->seedersPath . $filename;

        $template = $this->getSeederTemplate($className);

        if(!is_dir($this->seedersPath)) {
            mkdir($this->seedersPath, 0755, TRUE);
        }

        file_put_contents($filepath, $template);

        return $filepath;
    }

    /**
     * Get all seeders
     */
    private function getAllSeeders(): array {
        if(!is_dir($this->seedersPath)) {
            return [];
        }

        $files = glob($this->seedersPath . '*.php');
        sort($files);

        $seeders    = [];
        $userSeeder = NULL;

        foreach($files as $file) {
            $seeder = $this->loadSeederFromFile($file);
            if($seeder->getName() === 'UserSeeder') {
                $userSeeder = $seeder;
            } else {
                $seeders[] = $seeder;
            }
        }

        // Put UserSeeder first if it exists
        if($userSeeder) {
            array_unshift($seeders, $userSeeder);
        }

        return $seeders;
    }

    /**
     * Load seeder by name
     */
    private function loadSeeder(string $name): SeederInterface {
        $files = glob($this->seedersPath . '*.php');

        foreach($files as $file) {
            $seeder = $this->loadSeederFromFile($file);
            if($seeder->getName() === $name) {
                return $seeder;
            }
        }

        throw new Exception("Seeder not found: {$name}");
    }

    /**
     * Load seeder from file
     */
    private function loadSeederFromFile(string $file): SeederInterface {
        require_once $file;

        // Extract class name from file
        $content = file_get_contents($file);
        preg_match('/class\s+(\w+)/i', $content, $matches);

        if(!isset($matches[1])) {
            throw new Exception("Could not find class in seeder file: {$file}");
        }

        $className = $matches[1];

        if(!class_exists($className)) {
            throw new Exception("Seeder class not found: {$className}");
        }

        $seeder = new $className();

        // Inject connection if seeder accepts it
        if(method_exists($seeder, 'setConnection')) {
            $seeder->setConnection($this->connection);
        }

        return $seeder;
    }

    /**
     * Convert string to StudlyCase
     */
    private function studlyCase(string $value): string {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    /**
     * Get seeder template
     */
    private function getSeederTemplate(string $className): string {
        return "<?php

use KelvinKurniawan\LightORM\Contracts\SeederInterface;
use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;

class {$className} implements SeederInterface {
    private ?ConnectionInterface \$connection = null;
    
    public function setConnection(ConnectionInterface \$connection): void {
        \$this->connection = \$connection;
    }
    
    public function run(): void {
        // \$this->connection->query(\"INSERT INTO table_name (column) VALUES (?)\", ['value']);
        
        // Or use Query Builder:
        // \$queryBuilder = new KelvinKurniawan\LightORM\Query\QueryBuilder(
        //     \$this->connection, 
        //     \$grammar, 
        //     'table_name'
        // );
        // \$queryBuilder->insert(['column' => 'value']);
    }
    
    public function getName(): string {
        return '{$className}';
    }
}
";
    }
}
