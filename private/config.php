<?php
define("DB_SERVER", "db");
define("DB_USER",   getenv('MYSQL_USER') ?: 'flashit');
define("DB_PASS",   getenv('MYSQL_PASSWORD') ?: 'flashit_msp');
define("DB_NAME",   getenv('MYSQL_DATABASE') ?: 'flashit_milkshakepub');

define("ADMIN_USER", getenv('ADMIN_USER') ?: 'flashit');
define("ADMIN_PASS", getenv('ADMIN_PASS') ?: 'flashit_msp');