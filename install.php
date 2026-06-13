<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $_database, $plugin;

$modulname = 'downloads';
$version = isset($plugin['version']) ? (string)$plugin['version'] : ($version ?? '1.0.3.3');
$pluginName = 'Downloads';
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

## SYSTEM #######################################################################

$pluginRes = safe_query("SELECT pluginID FROM settings_plugins WHERE modulname = 'downloads' LIMIT 1");
if ($pluginRes && ($pluginRow = mysqli_fetch_assoc($pluginRes))) {
    safe_query("UPDATE settings_plugins SET
        admin_file = 'admin_downloads,admin_download_stats',
        activate = 1,
        author = 'T-Seven',
        website = 'https://www.nexpell.de',
        index_link = 'downloads',
        hiddenfiles = '',
        version = '" . downloads_sql($version) . "',
        path = '" . downloads_sql($pluginPath) . "',
        status_display = 1,
        plugin_display = 1,
        widget_display = 1,
        delete_display = 1,
        sidebar = 'deactivated'
        WHERE pluginID = " . (int)$pluginRow['pluginID'] . "
    ");
} else {
    safe_query("INSERT INTO settings_plugins
        (modulname, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar)
    VALUES
        ('downloads', 'admin_downloads,admin_download_stats', 1, 'T-Seven', 'https://www.nexpell.de', 'downloads', '', '" . downloads_sql($version) . "', '" . downloads_sql($pluginPath) . "', 1, 1, 1, 1, 'deactivated')
    ");
}

safe_query("
    INSERT INTO settings_plugins_lang
        (content_key, language, content, modulname, updated_at)
    VALUES
        ('plugin_name_downloads', 'de', 'Downloads', 'downloads', NOW()),
        ('plugin_name_downloads', 'en', 'Downloads', 'downloads', NOW()),
        ('plugin_name_downloads', 'it', 'Download', 'downloads', NOW()),
        ('plugin_info_downloads', 'de', 'Mit diesem Plugin koennt ihr eure Downloads anzeigen und verwalten.', 'downloads', NOW()),
        ('plugin_info_downloads', 'en', 'With this plugin you can display and manage your downloads.', 'downloads', NOW()),
        ('plugin_info_downloads', 'it', 'Con questo plugin e possibile mostrare e gestire i download sul sito web.', 'downloads', NOW())
    ON DUPLICATE KEY UPDATE
        content = VALUES(content),
        modulname = VALUES(modulname),
        updated_at = VALUES(updated_at)
");

safe_query("
    INSERT INTO settings_plugins_installed
        (name, modulname, description, version, author, url, folder, installed_date)
    VALUES
        ('Downloads', 'downloads', 'With this plugin you can display and manage your downloads.', '" . downloads_sql($version) . "', 'T-Seven', 'https://www.nexpell.de', 'downloads', NOW())
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        version = VALUES(version),
        author = VALUES(author),
        url = VALUES(url),
        folder = VALUES(folder),
        installed_date = NOW()
");

## NAVIGATION ###################################################################

$linkID = 0;
$linkRes = safe_query("
    SELECT linkID FROM navigation_dashboard_links
    WHERE modulname = 'downloads'
    ORDER BY linkID ASC LIMIT 1
");
if ($linkRes && ($linkRow = mysqli_fetch_assoc($linkRes))) {
    $linkID = (int)($linkRow['linkID'] ?? 0);
    safe_query("
        UPDATE navigation_dashboard_links SET
            catID = 13,
            url = 'admincenter.php?site=admin_downloads',
            sort = 1
        WHERE linkID = " . $linkID . "
    ");
} else {
    safe_query("
        INSERT INTO navigation_dashboard_links
            (catID, modulname, url, sort)
        VALUES
            (13, 'downloads', 'admincenter.php?site=admin_downloads', 1)
    ");
    $linkID = (int)mysqli_insert_id($_database);
}

if ($linkID > 0) {
    safe_query("
        INSERT INTO navigation_dashboard_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_link_{$linkID}', 'de', 'Downloads', 'downloads', NOW()),
            ('nav_link_{$linkID}', 'en', 'Downloads', 'downloads', NOW()),
            ('nav_link_{$linkID}', 'it', 'Download', 'downloads', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

$snavID = 0;
$snavRes = safe_query("
    SELECT snavID FROM navigation_website_sub
    WHERE modulname = 'downloads'
    ORDER BY snavID ASC LIMIT 1
");
if ($snavRes && ($snavRow = mysqli_fetch_assoc($snavRes))) {
    $snavID = (int)($snavRow['snavID'] ?? 0);
    safe_query("
        UPDATE navigation_website_sub SET
            mnavID = 5,
            url = 'index.php?site=downloads',
            sort = 1,
            indropdown = 1,
            last_modified = NOW()
        WHERE snavID = " . $snavID . "
    ");
} else {
    safe_query("
        INSERT INTO navigation_website_sub
            (mnavID, modulname, url, sort, indropdown, last_modified)
        VALUES
            (5, 'downloads', 'index.php?site=downloads', 1, 1, NOW())
    ");
    $snavID = (int)mysqli_insert_id($_database);
}

if ($snavID > 0) {
    safe_query("
        INSERT INTO navigation_website_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_sub_{$snavID}', 'de', 'Downloads', 'downloads', NOW()),
            ('nav_sub_{$snavID}', 'en', 'Downloads', 'downloads', NOW()),
            ('nav_sub_{$snavID}', 'it', 'Download', 'downloads', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

safe_query("
    INSERT IGNORE INTO user_role_admin_navi_rights
        (id, roleID, type, modulname)
    VALUES
        ('', 1, 'link', 'downloads')
");
