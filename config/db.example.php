<?php
/**
 * Hotel Saba Restaurant Management System
 * Database Configuration Template
 *
 * Copy this file to `config/db.local.php` or set your environment variables.
 */

// Database Host (e.g. 127.0.0.1 or localhost)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');

// Database User
define('DB_USER', getenv('DB_USER') ?: 'root');

// Database Password
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Database Name
define('DB_NAME', getenv('DB_NAME') ?: 'restaurant_pos');

// Database Charset
define('DB_CHARSET', 'utf8mb4');
