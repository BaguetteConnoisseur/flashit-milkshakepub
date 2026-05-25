<?php
function db() {
    date_default_timezone_set('Europe/Stockholm');
    static $pdo = null;
    if ($pdo === null) {
        $configPath = __DIR__ . '/../../config.php';

        if (!file_exists($configPath)) {
            header('Content-Type: application/json');
            echo json_encode(["error" => "Missing config file at: " . $configPath]);
            exit;
        }

        require_once($configPath);

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