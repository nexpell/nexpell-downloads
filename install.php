<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $plugin;

$version = isset($plugin['version']) ? (string)$plugin['version'] : ($version ?? '1.0.3.3');
$pluginPath = 'includes/plugins/downloads/';

if (!function_exists('downloads_sql')) {
    function downloads_sql($value): string
    {
        return escape((string)$value);
    }
}

if (!function_exists('downloads_column_exists')) {
    function downloads_column_exists(string $table, string $column): bool
    {
        $res = safe_query("SHOW COLUMNS FROM `$table` LIKE '" . downloads_sql($column) . "'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

safe_query("
CREATE TABLE IF NOT EXISTS plugins_downloads_categories (
  categoryID int(11) NOT NULL AUTO_INCREMENT,
  title varchar(255) NOT NULL,
  description text DEFAULT NULL,
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
  logID int(11) NOT NULL AUTO_INCREMENT,
  userID int(11) NOT NULL,
  fileID int(11) NOT NULL,
  downloaded_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (logID),
  KEY userID (userID),
  KEY fileID (fileID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

if (!downloads_column_exists('plugins_downloads', 'downloads')) {
    safe_query("ALTER TABLE plugins_downloads ADD COLUMN downloads INT DEFAULT 0 AFTER file");
}
if (!downloads_column_exists('plugins_downloads', 'userID')) {
    safe_query("ALTER TABLE plugins_downloads ADD COLUMN userID INT NOT NULL DEFAULT 0 AFTER categoryID");
    safe_query("ALTER TABLE plugins_downloads ADD KEY idx_userID (userID)");
}
if (!downloads_column_exists('plugins_downloads', 'access_roles')) {
    safe_query("ALTER TABLE plugins_downloads ADD COLUMN access_roles TEXT AFTER downloads");
}
if (!downloads_column_exists('plugins_downloads', 'uploaded_at')) {
    safe_query("ALTER TABLE plugins_downloads ADD COLUMN uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER access_roles");
}
if (!downloads_column_exists('plugins_downloads', 'updated_at')) {
    safe_query("ALTER TABLE plugins_downloads ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER uploaded_at");
}

PluginInstallerHelper::registerPlugin([
    'modulname'   => 'downloads',
    'name'        => 'Downloads',
    'version'     => $version,
    'admin_file'  => 'admin_downloads,admin_download_stats',
    'path'        => $pluginPath,
    'author'      => 'T-Seven',
    'website'     => 'https://www.nexpell.de',
    'index_link'  => 'downloads',
    'hiddenfiles' => '',
    'sidebar'     => 'deactivated'
]);

PluginInstallerHelper::addLanguageItem('plugin_info_downloads', 'downloads', [
    'de' => 'Mit diesem Plugin könnt ihr eure Downloads anzeigen und verwalten.',
    'en' => 'With this plugin you can display and manage your downloads.',
    'it' => 'Con questo plugin è possibile mostrare e gestire i download sul sito web.'
]);

PluginInstallerHelper::registerAdminNavigation([
    'modulname' => 'downloads',
    'url'       => 'admincenter.php?site=admin_downloads',
    'catID'     => 13,
    'sort'      => 1,
    'labels'    => [
        'de' => 'Downloads',
        'en' => 'Downloads',
        'it' => 'Download'
    ]
]);

PluginInstallerHelper::registerWebsiteNavigation([
    'modulname'  => 'downloads',
    'url'        => 'index.php?site=downloads',
    'mnavID'     => 5,
    'sort'       => 1,
    'indropdown' => 1,
    'labels'     => [
        'de' => 'Downloads',
        'en' => 'Downloads',
        'it' => 'Download'
    ]
]);

PluginInstallerHelper::registerAdminRight('downloads');
