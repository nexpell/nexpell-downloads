<?php

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

## SYSTEM #####################################################################################################################################

safe_query("
    INSERT IGNORE INTO settings_plugins
        (pluginID, modulname, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar)
    VALUES
        ('', 'downloads', 'admin_downloads,admin_download_stats', 1, 'T-Seven', 'https://www.nexpell.de', 'downloads', '', '1.0.3.3', 'includes/plugins/downloads/', 1, 1, 1, 1, 'deactivated')
");

safe_query("
    INSERT IGNORE INTO settings_plugins_lang
        (content_key, language, content, modulname, updated_at)
    VALUES
        ('plugin_name_downloads', 'de', 'Download', 'downloads', NOW()),
        ('plugin_name_downloads', 'en', 'Download', 'downloads', NOW()),
        ('plugin_name_downloads', 'it', 'Download', 'downloads', NOW()),
        ('plugin_info_downloads', 'de', 'Mit diesem Plugin koennt ihr eure Downloads anzeigen lassen.', 'downloads', NOW()),
        ('plugin_info_downloads', 'en', 'With this plugin you can display your downloads.', 'downloads', NOW()),
        ('plugin_info_downloads', 'it', 'Con questo plugin e possibile mostrare i download sul sito web.', 'downloads', NOW())
    ON DUPLICATE KEY UPDATE
        content = VALUES(content),
        modulname = VALUES(modulname),
        updated_at = VALUES(updated_at)
");

safe_query("
    INSERT IGNORE INTO settings_plugins_installed
        (name, modulname, description, version, author, url, folder, installed_date)
    VALUES
        ('Download', 'downloads', 'With this plugin you can display your downloads.', '1.0.3.3', 'T-Seven', 'https://www.nexpell.de', 'downloads', NOW())
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        version = VALUES(version),
        author = VALUES(author),
        url = VALUES(url),
        folder = VALUES(folder),
        installed_date = NOW()
");

## NAVIGATION #####################################################################################################################################

$linkID = 0;
$linkRes = safe_query("
    SELECT linkID FROM navigation_dashboard_links
    WHERE modulname = 'downloads' AND url = 'admincenter.php?site=admin_downloads'
    ORDER BY linkID ASC LIMIT 1
");
if ($linkRes && ($linkRow = mysqli_fetch_assoc($linkRes))) {
    $linkID = (int) ($linkRow['linkID'] ?? 0);
} else {
    safe_query("
        INSERT IGNORE INTO navigation_dashboard_links
            (catID, modulname, url, sort)
        VALUES
            (13, 'downloads', 'admincenter.php?site=admin_downloads', 1)
    ");
    $linkID = (int) mysqli_insert_id($_database);
}

if ($linkID > 0) {
    safe_query("
        INSERT IGNORE INTO navigation_dashboard_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_link_{$linkID}', 'de', 'Download', 'downloads', NOW()),
            ('nav_link_{$linkID}', 'en', 'Download', 'downloads', NOW()),
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
    WHERE modulname = 'downloads' AND url = 'index.php?site=downloads'
    ORDER BY snavID ASC LIMIT 1
");
if ($snavRes && ($snavRow = mysqli_fetch_assoc($snavRes))) {
    $snavID = (int) ($snavRow['snavID'] ?? 0);
} else {
    safe_query("
        INSERT IGNORE INTO navigation_website_sub
            (mnavID, modulname, url, sort, indropdown, last_modified)
        VALUES
            (5, 'downloads', 'index.php?site=downloads', 1, 1, NOW())
    ");
    $snavID = (int) mysqli_insert_id($_database);
}

if ($snavID > 0) {
    safe_query("
        INSERT IGNORE INTO navigation_website_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_sub_{$snavID}', 'de', 'Download', 'downloads', NOW()),
            ('nav_sub_{$snavID}', 'en', 'Download', 'downloads', NOW()),
            ('nav_sub_{$snavID}', 'it', 'Download', 'downloads', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'downloads')
");
?>
