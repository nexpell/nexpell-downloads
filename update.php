<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $str, $modulname, $version, $plugin;

$modulname = 'downloads';
$version   = (string)($plugin['version'] ?? '0.0.0');
$str       = 'Downloads';

if (!downloads_column_exists('plugins_downloads', 'userID')) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD COLUMN userID INT NOT NULL DEFAULT 0 AFTER categoryID
    ");
}

if (!downloads_column_exists('plugins_downloads', 'access_roles')) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD COLUMN access_roles TEXT AFTER downloads
    ");
}

if (!downloads_column_exists('plugins_downloads', 'uploaded_at')) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD COLUMN uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER access_roles
    ");
}

if (!downloads_column_exists('plugins_downloads', 'updated_at')) {
    safe_query("
        ALTER TABLE plugins_downloads
        ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
        AFTER uploaded_at
    ");
}