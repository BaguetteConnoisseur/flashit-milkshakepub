<?php
function db() {
    static $pdo = null;
    if ($pdo === null) {
        // We add charset=utf8mb4 to handle the 'ä'
        $dsn = "mysql:host=db;dbname=flashit_bestallningsark;charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, "root", "root", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            // This is what's sending the "Connection..." string to your JS
            header('Content-Type: application/json');
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}