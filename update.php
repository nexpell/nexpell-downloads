<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $str, $modulname, $version, $plugin;

$modulname = 'downloads';
$version   = (string)($plugin['version'] ?? '0.0.0');
$str       = 'Downloads';

$res = safe_query("SHOW COLUMNS FROM plugins_downloads LIKE 'userID'");
if (!$res || mysqli_num_rows($res) === 0) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD COLUMN userID INT NOT NULL DEFAULT 0 AFTER categoryID
    ");
}

$res = safe_query("SHOW COLUMNS FROM plugins_downloads LIKE 'access_roles'");
if (!$res || mysqli_num_rows($res) === 0) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD COLUMN access_roles TEXT AFTER downloads
    ");
}

$res = safe_query("SHOW COLUMNS FROM plugins_downloads LIKE 'uploaded_at'");
if (!$res || mysqli_num_rows($res) === 0) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD COLUMN uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER access_roles
    ");
}

$res = safe_query("SHOW COLUMNS FROM plugins_downloads LIKE 'updated_at'");
if (!$res || mysqli_num_rows($res) === 0) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
        AFTER uploaded_at
    ");
}

$res = safe_query("
    SHOW INDEX
    FROM plugins_downloads
    WHERE Key_name = 'idx_userID'
");

if (!$res || mysqli_num_rows($res) === 0) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD INDEX idx_userID (userID)
    ");
}