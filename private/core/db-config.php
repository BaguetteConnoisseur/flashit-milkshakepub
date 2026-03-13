<?php
$conn_username = getenv("DB_USER") ?: getenv("MYSQL_USER") ?: "YOUR_DB_USER";
$conn_password = getenv("DB_PASS") ?: getenv("MYSQL_PASSWORD") ?: "YOUR_DB_PASSWORD";
$conn_host = getenv("DB_HOST") ?: "localhost";
$conn_database = getenv("DB_NAME") ?: getenv("MYSQL_DATABASE") ?: "YOUR_DB_NAME";
