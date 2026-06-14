<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $plugin;

PluginInstallerHelper::install([

    'modulname'  => 'downloads',
    'name'       => 'Downloads',
    'version'    => (string)($plugin['version'] ?? '0.0.0'),
    'author'     => 'Nexpell-Team',
    'website'    => 'https://www.nexpell.de',
    'path'       => 'includes/plugins/downloads/',

    'admin_file' => 'admin_downloads,admin_download_stats',
    'index_link' => 'downloads',
    'sidebar'    => 'deactivated',

    'languages' => [
        'plugin_info_downloads' => [
            'de' => 'Mit diesem Plugin könnt ihr eure Downloads anzeigen und verwalten.',
            'en' => 'With this plugin you can display and manage your downloads.',
            'it' => 'Con questo plugin è possibile mostrare e gestire i download sul sito web.'
        ]
    ],

    'permissions' => [
        'downloads'
    ],

    'admin_navigation' => [
        [
            'url'   => 'admincenter.php?site=admin_downloads',
            'catID' => 13,
            'sort'  => 1,
            'labels' => [
                'de' => 'Downloads',
                'en' => 'Downloads',
                'it' => 'Download'
            ]
        ]
    ],

    'website_navigation' => [
        [
            'url'        => 'index.php?site=downloads',
            'mnavID'     => 5,
            'sort'       => 1,
            'indropdown' => 1,
            'labels' => [
                'de' => 'Downloads',
                'en' => 'Downloads',
                'it' => 'Download'
            ]
        ]
    ]

]);

safe_query("
CREATE TABLE IF NOT EXISTS plugins_downloads_categories (
    categoryID INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    PRIMARY KEY (categoryID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

safe_query("
CREATE TABLE IF NOT EXISTS plugins_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoryID INT NOT NULL,
    userID INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file VARCHAR(255) NOT NULL,
    downloads INT DEFAULT 0,
    access_roles TEXT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_categoryID (categoryID),
    KEY idx_userID (userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

safe_query("
CREATE TABLE IF NOT EXISTS plugins_downloads_logs (
    logID INT(11) NOT NULL AUTO_INCREMENT,
    userID INT(11) NOT NULL,
    fileID INT(11) NOT NULL,
    downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (logID),
    KEY userID (userID),
    KEY fileID (fileID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");