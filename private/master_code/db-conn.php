<?php
require_once("db-acc-uphd.php");
$conn = mysqli_connect($conn_host, $conn_username, $conn_password, $conn_database);
if (!$conn) {
    echo "Connection failed " . mysqli_connect_error();
}
