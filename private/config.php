<?php
define("DB_SERVER", "db");
define("DB_USER",   getenv('MYSQL_USER') ?: 'flashit');
define("DB_PASS",   getenv('MYSQL_PASSWORD') ?: 'flashit_msp');
define("DB_NAME",   getenv('MYSQL_DATABASE') ?: 'flashit_milkshakepub');

// Use a hashed admin password for better security
define("ADMIN_PASS_HASH", getenv('ADMIN_PASS_HASH') ?: '$2y$10$REPLACE_WITH_YOUR_HASH');