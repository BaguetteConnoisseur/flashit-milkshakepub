<?php
require_once("db-config.php");
$conn = mysqli_connect($conn_host, $conn_username, $conn_password, $conn_database);
if (!$conn) {
    echo "Connection failed " . mysqli_connect_error();
}

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET collation_connection = 'utf8mb4_swedish_ci', time_zone = '+01:00'");
