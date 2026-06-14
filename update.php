<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $str, $modulname, $version, $plugin;

$modulname = 'downloads';
$version   = (string)($plugin['version'] ?? '0.0.0');
$str       = 'Downloads';

PluginInstallerHelper::addColumnIfMissing(
    'plugins_downloads',
    'downloads',
    'INT DEFAULT 0 AFTER file'
);

PluginInstallerHelper::addColumnIfMissing(
    'plugins_downloads',
    'userID',
    'INT NOT NULL DEFAULT 0 AFTER categoryID'
);

PluginInstallerHelper::addIndexIfMissing(
    'plugins_downloads',
    'idx_userID',
    '(userID)'
);

PluginInstallerHelper::addColumnIfMissing(
    'plugins_downloads',
    'access_roles',
    'TEXT AFTER downloads'
);

PluginInstallerHelper::addColumnIfMissing(
    'plugins_downloads',
    'uploaded_at',
    'DATETIME DEFAULT CURRENT_TIMESTAMP AFTER access_roles'
);

PluginInstallerHelper::addColumnIfMissing(
    'plugins_downloads',
    'updated_at',
    'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER uploaded_at'
);
