<?php
function db() {
    // Always use Stockholm time for all date/time functions
    date_default_timezone_set('Europe/Stockholm');
    static $pdo = null;
    if ($pdo === null) {
        // Use the absolute path within the Docker container
        $configPath = __DIR__ . '/../../config.php';

        if (!file_exists($configPath)) {
            // This will tell us exactly where it's looking if it fails
            header('Content-Type: application/json');
            echo json_encode(["error" => "Missing config file at: " . $configPath]);
            exit;
        }

        require_once($configPath);

        // Now that the file is loaded, these constants will exist
        $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}