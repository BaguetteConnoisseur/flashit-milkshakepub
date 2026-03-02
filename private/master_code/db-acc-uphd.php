<?php
// Load database credentials from environment variables
// NEVER commit real credentials to the repository!
$conn_username = getenv("DB_USER") ?: "YOUR_DB_USER";
$conn_password = getenv("DB_PASS") ?: "YOUR_DB_PASSWORD";
$conn_host = getenv("DB_HOST") ?: "localhost";
$conn_database = getenv("DB_NAME") ?: "YOUR_DB_NAME";
