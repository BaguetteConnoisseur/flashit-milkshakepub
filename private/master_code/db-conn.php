<?php
require_once("db-acc-uphd.php");
$conn = mysqli_connect($conn_host, $conn_username, $conn_password, $conn_database);
if (!$conn) {
    echo "Connection failed " . mysqli_connect_error();
}

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET collation_connection = 'utf8mb4_swedish_ci'");

$timezone = new DateTimeZone(date_default_timezone_get());
$offsetSeconds = $timezone->getOffset(new DateTime('now', $timezone));
$offsetSign = $offsetSeconds < 0 ? '-' : '+';
$absoluteOffset = abs($offsetSeconds);
$offsetHours = str_pad((string) floor($absoluteOffset / 3600), 2, '0', STR_PAD_LEFT);
$offsetMinutes = str_pad((string) floor(($absoluteOffset % 3600) / 60), 2, '0', STR_PAD_LEFT);
$mysqlOffset = $offsetSign . $offsetHours . ':' . $offsetMinutes;
mysqli_query($conn, "SET time_zone = '$mysqlOffset'");
