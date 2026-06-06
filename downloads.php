<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $_database, $languageService, $tpl;

if (!function_exists('downloads_has_access')) {
    function downloads_has_access($accessRolesJson): bool
    {
        if (empty($accessRolesJson)) {
            return true;
        }

        $roles = json_decode($accessRolesJson, true);
        if (!is_array($roles)) {
            return true;
        }

        if ($roles === [] || count($roles) === 0) {
            return true;
        }

        return AccessControl::hasAnyRole($roles);
    }
}

if (!function_exists('downloads_file_extension')) {
    function downloads_file_extension(?string $filename): string
    {
        $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
        return $ext !== '' ? $ext : 'file';
    }
}

if (!function_exists('downloads_file_size_label')) {
    function downloads_file_size_label(?string $filename): string
    {
        $fullpath = downloads_resolve_file_path($filename);

        if ($fullpath === null || !is_file($fullpath)) {
            return '-';
        }

        $bytes = filesize($fullpath);
        if ($bytes === false) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float)$bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1, ',', '.') . ' ' . $units[$unit];
    }
}

if (!function_exists('downloads_resolve_file_path')) {
    function downloads_resolve_file_path(?string $filename): ?string
    {
        $file = basename((string)$filename);
        if ($file === '') {
            return null;
        }

        $candidates = [
            __DIR__ . '/files/' . $file,
            __DIR__ . '/' . $file,
        ];

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_file($resolved)) {
                return $resolved;
            }
        }

        return null;
    }
}

if (!function_exists('downloads_excerpt')) {
    function downloads_excerpt(?string $text, int $maxLength = 140): string
    {
        $plain = trim(strip_tags((string)$text));
        if ($plain === '') {
            return '';
        }

        if (mb_strlen($plain) <= $maxLength) {
            return $plain;
        }

        return mb_substr($plain, 0, $maxLength - 1) . '…';
    }
}

if (!function_exists('downloads_meta_value')) {
    function downloads_meta_value(?string $value): string
    {
        return !empty($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : '-';
    }
}

if (!function_exists('downloads_total_size_bytes')) {
    function downloads_total_size_bytes(array $rows): int
    {
        $total = 0;

        foreach ($rows as $row) {
            $fullpath = downloads_resolve_file_path((string)($row['file'] ?? ''));
            if ($fullpath !== null && is_file($fullpath)) {
                $size = filesize($fullpath);
                if ($size !== false) {
                    $total += (int)$size;
                }
            }
        }

        return $total;
    }
}

if (!function_exists('downloads_format_bytes')) {
    function downloads_format_bytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float)$bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1, ',', '.') . ' ' . $units[$unit];
    }
}

if (!function_exists('downloads_category_accent')) {
    function downloads_category_accent(int $index): array
    {
        $accents = [
            ['class' => 'blue', 'icon' => 'bi-journal-text'],
            ['class' => 'violet', 'icon' => 'bi-download'],
            ['class' => 'red', 'icon' => 'bi-palette-fill'],
            ['class' => 'green', 'icon' => 'bi-folder2-open'],
            ['class' => 'orange', 'icon' => 'bi-hdd-stack'],
            ['class' => 'teal', 'icon' => 'bi-archive']
        ];

        return $accents[$index % count($accents)];
    }
}

if (!function_exists('downloads_render_list_item')) {
    function downloads_render_list_item(array $row, string $meta): void
    {
        $title = htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8');
        $detailLink = SeoUrlHandler::convertToSeoUrl('index.php?site=downloads&action=detail&id=' . (int)$row['id']);
        ?>
        <a class="downloads-mini-item" href="<?= $detailLink ?>">
            <div class="downloads-mini-icon"><i class="bi bi-file-earmark-arrow-down"></i></div>
            <div class="downloads-mini-copy">
                <strong><?= $title ?></strong>
                <span><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <i class="bi bi-chevron-right downloads-mini-arrow"></i>
        </a>
        <?php
    }
}

if (!function_exists('downloads_render_card')) {
    function downloads_render_card(array $row, LanguageService $languageService, string $variant = 'default'): void
    {
        $title = htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8');
        $description = downloads_excerpt($row['description'] ?? '', 155);
        $uploaded = !empty($row['uploaded_at']) ? date('d.m.Y', strtotime((string)$row['uploaded_at'])) : '';
        $updated = !empty($row['updated_at']) ? date('d.m.Y', strtotime((string)$row['updated_at'])) : '';
        $downloads = (int)($row['download_count'] ?? $row['downloads'] ?? 0);
        $extension = strtoupper(downloads_file_extension($row['file'] ?? ''));
        $fileSize = downloads_file_size_label($row['file'] ?? '');
        $detailLink = SeoUrlHandler::convertToSeoUrl('index.php?site=downloads&action=detail&id=' . (int)$row['id']);
        $downloadLink = SeoUrlHandler::convertToSeoUrl('index.php?site=downloads&action=download&id=' . (int)$row['id']);
        $cardClass = $variant === 'category' ? 'downloads-card downloads-card--category card h-100' : 'downloads-card card h-100';
        ?>
        <article class="<?= $cardClass ?>">
            <div class="downloads-card-top">
                <div class="downloads-file-badge">
                    <span class="downloads-file-badge-label"><?= $extension ?></span>
                </div>
                <div class="downloads-card-head">
                    <h3 class="downloads-card-title"><?= $title ?></h3>
                    <p class="downloads-card-subtitle"><?= htmlspecialchars($languageService->get('file_info'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <div class="downloads-card-body">
                <?php if ($description !== ''): ?>
                    <p class="downloads-card-description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <div class="downloads-meta-grid">
                    <div class="downloads-meta-chip">
                        <span class="downloads-meta-label"><?= htmlspecialchars($languageService->get('uploaded_at'), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= downloads_meta_value($uploaded) ?></strong>
                    </div>
                    <div class="downloads-meta-chip">
                        <span class="downloads-meta-label"><?= htmlspecialchars($languageService->get('last_update'), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= downloads_meta_value($updated) ?></strong>
                    </div>
                    <div class="downloads-meta-chip">
                        <span class="downloads-meta-label"><?= htmlspecialchars($languageService->get('downloads'), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= $downloads ?></strong>
                    </div>
                    <div class="downloads-meta-chip">
                        <span class="downloads-meta-label"><?= htmlspecialchars($languageService->get('file_info'), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= htmlspecialchars($fileSize, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>
            </div>

            <div class="downloads-card-actions">
                <a href="<?= $detailLink ?>" class="btn btn-outline-primary">
                    <i class="bi bi-info-circle"></i>
                    <?= htmlspecialchars($languageService->get('details_download'), ENT_QUOTES, 'UTF-8') ?>
                </a>
                <a href="<?= $downloadLink ?>" class="btn btn-primary">
                    <i class="bi bi-download"></i>
                    <?= htmlspecialchars($languageService->get('download_now'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </article>
        <?php
    }
}

if (!function_exists('downloads_render_breadcrumb')) {
    function downloads_render_breadcrumb(array $items): void
    {
        ?>
        <nav aria-label="breadcrumb" class="downloads-breadcrumb-wrap">
            <ol class="breadcrumb downloads-breadcrumb">
                <?php foreach ($items as $index => $item): ?>
                    <?php $isLast = $index === array_key_last($items); ?>
                    <li class="breadcrumb-item<?= $isLast ? ' active' : '' ?>"<?= $isLast ? ' aria-current="page"' : '' ?>>
                        <?php if (!$isLast && !empty($item['href'])): ?>
                            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php
    }
}

if (!function_exists('downloads_render_info_alert')) {
    function downloads_render_info_alert($languageService, string $key, string $fallback = 'Keine Eintraege verfuegbar.'): void
    {
        $message = $fallback;

        if (is_object($languageService) && method_exists($languageService, 'get')) {
            $value = $languageService->get($key);
            if (is_string($value) && $value !== '' && $value !== '[' . $key . ']') {
                $message = $value;
            }
        }

        echo '<div class="alert alert-info m-3" role="alert">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

if (!function_exists('downloads_redirect')) {
    function downloads_redirect(string $target): void
    {
        header('Location: ' . SeoUrlHandler::convertToSeoUrl($target));
        exit;
    }
}

if (!function_exists('downloads_stream_file')) {
    function downloads_stream_file(string $fullpath, string $downloadName): void
    {
        $filesize = filesize($fullpath);
        if ($filesize === false || !is_readable($fullpath)) {
            downloads_redirect('index.php?site=downloads');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('zlib.output_compression', '0');

        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }

        $handle = fopen($fullpath, 'rb');
        if ($handle === false) {
            downloads_redirect('index.php?site=downloads');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addcslashes(basename($downloadName), "\\\"") . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . (string)$filesize);
        header('Cache-Control: private');
        header('Pragma: public');
        header('X-Accel-Buffering: no');

        $chunkSize = 1024 * 1024;
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                break;
            }

            echo $chunk;
            flush();

            if (connection_aborted()) {
                break;
            }
        }

        fclose($handle);
        exit;
    }
}

$action = (string)($_GET['action'] ?? 'list');
if ($action === 'show') {
    $action = 'detail';
} elseif ($action === 'category') {
    $action = 'cat_list';
}

$id = 0;
if ($action === 'cat_list') {
    $id = isset($_GET['categoryID']) ? (int)$_GET['categoryID'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
} elseif ($action === 'download') {
    $id = isset($_GET['downloadID']) ? (int)$_GET['downloadID'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
} else {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
}

if ($action === 'download' && $id > 0) {
    $result = safe_query("SELECT * FROM plugins_downloads WHERE id = $id");
    $dl = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;

    if (!$dl || !downloads_has_access($dl['access_roles'])) {
        downloads_redirect('index.php?site=downloads');
    }

    $file = basename((string)$dl['file']);
    $fullpath = downloads_resolve_file_path($file);
    $allowedDirs = array_filter([
        realpath(__DIR__ . '/files/'),
        realpath(__DIR__),
    ]);

    $isAllowedPath = false;
    if ($fullpath !== null) {
        foreach ($allowedDirs as $allowedDir) {
            $allowedPrefix = rtrim((string)$allowedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strpos($fullpath, $allowedPrefix) === 0) {
                $isAllowedPath = true;
                break;
            }
        }
    }

    if ($fullpath === null || !file_exists($fullpath) || !$isAllowedPath) {
        downloads_redirect('index.php?site=downloads');
    }

    safe_query("UPDATE plugins_downloads SET downloads = downloads + 1 WHERE id = $id");

    if (!empty($_SESSION['userID'])) {
        $userID = (int)$_SESSION['userID'];
        $stmt = $_database->prepare("INSERT INTO plugins_downloads_logs (userID, fileID) VALUES (?, ?)");

        if ($stmt) {
            $stmt->bind_param('ii', $userID, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log('DB-Fehler: ' . $_database->error);
        }
    }

    downloads_stream_file($fullpath, $file);
}

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'Downloads'
];
echo $tpl->loadTemplate("downloads", "head", $data_array, 'plugin');
echo '<link rel="stylesheet" href="/includes/plugins/downloads/css/downloads.css">';

echo '<div class="downloads-page downloads-page--' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';

$downloadsOverviewLink = SeoUrlHandler::convertToSeoUrl('index.php?site=downloads');
$breadcrumbItems = [
    [
        'label' => $languageService->get('downloads'),
        'href' => $action === 'list' ? '' : $downloadsOverviewLink
    ]
];

if ($action === 'cat_list' && $id > 0) {
    $categoryID = $id;
    $catResult = safe_query("SELECT title, description FROM plugins_downloads_categories WHERE categoryID = $categoryID LIMIT 1");
    $catRow = $catResult ? mysqli_fetch_assoc($catResult) : null;

    if (!$catRow) {
        nx_alert('info', 'no_downloads_available', false);
    } else {
        $catTitle = htmlspecialchars((string)$catRow['title'], ENT_QUOTES, 'UTF-8');
        $catDescription = trim((string)($catRow['description'] ?? ''));
        $breadcrumbItems[] = [
            'label' => (string)$catRow['title'],
            'href' => ''
        ];
        downloads_render_breadcrumb($breadcrumbItems);

        $result = safe_query("
            SELECT d.*,
                   (SELECT COUNT(*) FROM plugins_downloads_logs l WHERE l.fileID = d.id) AS download_count
            FROM plugins_downloads d
            WHERE d.categoryID = $categoryID
            ORDER BY d.updated_at DESC, d.uploaded_at DESC
        ");

        $visibleDownloads = [];
        if ($result && mysqli_num_rows($result)) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (!downloads_has_access($row['access_roles'])) {
                    continue;
                }

                $visibleDownloads[] = $row;
            }
        }

        $categoryDownloadCount = 0;
        foreach ($visibleDownloads as $download) {
            $categoryDownloadCount += (int)($download['download_count'] ?? $download['downloads'] ?? 0);
        }

        $categorySizeLabel = downloads_format_bytes(downloads_total_size_bytes($visibleDownloads));
        ?>
        <section class="downloads-category-view card">
            <div class="downloads-category-topbar">
                <a href="<?= $downloadsOverviewLink ?>" class="downloads-backlink">
                    <i class="bi bi-arrow-left"></i>
                    <?= htmlspecialchars($languageService->get('downloads'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>

            <div class="downloads-category-hero">
                <div class="downloads-category-hero-icon">
                    <i class="bi bi-folder2-open"></i>
                </div>
                <div class="downloads-category-hero-copy">
                    <span class="downloads-category-hero-kicker"><?= htmlspecialchars($languageService->get('category'), ENT_QUOTES, 'UTF-8') ?></span>
                    <h2><?= $catTitle ?></h2>
                    <?php if ($catDescription !== ''): ?>
                        <p><?= htmlspecialchars($catDescription, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <p><?= htmlspecialchars($languageService->get('category_overview_text'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="downloads-stat-grid downloads-stat-grid--category">
                <article class="downloads-stat-card">
                    <div class="downloads-stat-icon green"><i class="bi bi-file-earmark-fill"></i></div>
                    <div>
                        <strong><?= count($visibleDownloads) ?></strong>
                        <span><?= htmlspecialchars($languageService->get('files_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </article>
                <article class="downloads-stat-card">
                    <div class="downloads-stat-icon blue"><i class="bi bi-cloud-arrow-down-fill"></i></div>
                    <div>
                        <strong><?= $categoryDownloadCount ?></strong>
                        <span><?= htmlspecialchars($languageService->get('downloads'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </article>
                <article class="downloads-stat-card">
                    <div class="downloads-stat-icon gold"><i class="bi bi-device-hdd-fill"></i></div>
                    <div>
                        <strong><?= htmlspecialchars($categorySizeLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($languageService->get('total_size'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </article>
            </div>

            <section class="downloads-panel downloads-panel--category-list">
                <header class="downloads-panel-head">
                    <h3><i class="bi bi-files"></i> <?= htmlspecialchars($languageService->get('files_in_this_category'), ENT_QUOTES, 'UTF-8') ?></h3>
                </header>
                <?php if (!empty($visibleDownloads)): ?>
                    <div class="downloads-category-files-grid">
                        <?php foreach ($visibleDownloads as $row): ?>
                            <div class="downloads-category-file-col">
                                <?php downloads_render_card($row, $languageService, 'category'); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php downloads_render_info_alert($languageService, 'no_downloads_in_this_category', $languageService->get('no_downloads_in_this_category')); ?>
                <?php endif; ?>
            </section>
        </section>
        <?php
    }
} elseif ($action === 'detail' && $id > 0) {
    $sql = "
        SELECT d.*, c.title AS category_title, c.categoryID,
               (SELECT COUNT(*) FROM plugins_downloads_logs l WHERE l.fileID = d.id) AS download_count
        FROM plugins_downloads d
        LEFT JOIN plugins_downloads_categories c ON d.categoryID = c.categoryID
        WHERE d.id = $id
        LIMIT 1
    ";
    $result = safe_query($sql);

    if (!$result || $result->num_rows === 0) {
        http_response_code(404);
        nx_alert('danger', 'alert_not_found', false);
        echo '</div>';
        return;
    }

    $dl = $result->fetch_assoc();
    if (!downloads_has_access($dl['access_roles'])) {
        nx_alert('danger', 'alert_access_denied', false);
        echo '</div>';
        return;
    }

    $title = htmlspecialchars((string)$dl['title'], ENT_QUOTES, 'UTF-8');
    $desc = (string)($dl['description'] ?? '');
    $uploaded = !empty($dl['uploaded_at']) ? date('d.m.Y', strtotime((string)$dl['uploaded_at'])) : '-';
    $updated = !empty($dl['updated_at']) ? date('d.m.Y', strtotime((string)$dl['updated_at'])) : '-';
    $downloads = (int)$dl['download_count'];
    $downloadLink = SeoUrlHandler::convertToSeoUrl('index.php?site=downloads&action=download&id=' . $id);
    $catTitle = htmlspecialchars((string)($dl['category_title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $catID = (int)($dl['categoryID'] ?? 0);
    $catLink = SeoUrlHandler::convertToSeoUrl('index.php?site=downloads&action=cat_list&id=' . $catID);
    $extension = strtoupper(downloads_file_extension($dl['file'] ?? ''));
    $fileSize = downloads_file_size_label($dl['file'] ?? '');
    $breadcrumbItems[] = [
        'label' => (string)($dl['category_title'] ?? ''),
        'href' => $catLink
    ];
    $breadcrumbItems[] = [
        'label' => (string)$dl['title'],
        'href' => ''
    ];
    downloads_render_breadcrumb($breadcrumbItems);
    ?>

    <section class="downloads-detail card">
        <div class="downloads-detail-main">
            <div class="downloads-detail-badge"><?= $extension ?></div>
            <div class="downloads-detail-copy">
                <span class="downloads-hero-kicker"><?= htmlspecialchars($languageService->get('downloads'), ENT_QUOTES, 'UTF-8') ?></span>
                <h2 class="downloads-detail-title"><?= $title ?></h2>
                <?php if (trim(strip_tags($desc)) !== ''): ?>
                    <div class="downloads-detail-description"><?= $desc ?></div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="downloads-detail-sidebar">
            <div class="downloads-detail-panel">
                <h3><?= htmlspecialchars($languageService->get('file_info'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="downloads-detail-meta">
                    <div><span><?= htmlspecialchars($languageService->get('uploaded_at'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= htmlspecialchars($uploaded, ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div><span><?= htmlspecialchars($languageService->get('last_update'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= htmlspecialchars($updated, ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div><span><?= htmlspecialchars($languageService->get('downloads'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= $downloads ?></strong></div>
                    <div><span><?= htmlspecialchars($languageService->get('file_info'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= htmlspecialchars($fileSize, ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>
                <a href="<?= $downloadLink ?>" class="btn btn-primary w-100 downloads-detail-button">
                    <i class="bi bi-download"></i>
                    <?= htmlspecialchars($languageService->get('download_now'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </aside>
    </section>
    <?php
} else {
    $sql = "
        SELECT
            c.categoryID,
            c.title AS cat_title,
            c.description AS cat_desc,
            d.id,
            d.title,
            d.description,
            d.file,
            d.downloads,
            d.access_roles,
            d.uploaded_at,
            d.updated_at,
            (SELECT COUNT(*) FROM plugins_downloads_logs l WHERE l.fileID = d.id) AS download_count
        FROM plugins_downloads_categories c
        LEFT JOIN plugins_downloads d ON d.categoryID = c.categoryID
        ORDER BY c.title ASC, d.updated_at DESC, d.uploaded_at DESC
    ";

    $result = safe_query($sql);
    if (!$result) {
        nx_alert('danger', 'alert_db_error', false);
        echo '</div>';
        return;
    }

    downloads_render_breadcrumb($breadcrumbItems);

    $allRows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $allRows[] = $row;
    }

    $statsFileCount = 0;
    $statsCategoryCount = 0;
    $statsDownloads = 0;
    $visibleByCategory = [];
    $visibleDownloads = [];

    foreach ($allRows as $row) {
        $categoryId = (int)$row['categoryID'];
        if (!isset($visibleByCategory[$categoryId])) {
            $visibleByCategory[$categoryId] = [
                'categoryID' => $categoryId,
                'title' => (string)$row['cat_title'],
                'description' => (string)($row['cat_desc'] ?? ''),
                'count' => 0
            ];
        }

        if (!empty($row['id']) && downloads_has_access($row['access_roles'])) {
            $statsFileCount++;
            $statsDownloads += (int)($row['download_count'] ?? $row['downloads'] ?? 0);
            $visibleByCategory[$categoryId]['count']++;
            $visibleDownloads[] = $row;
        }
    }

    foreach ($visibleByCategory as $category) {
        if ($category['count'] > 0) {
            $statsCategoryCount++;
        }
    }

    usort($visibleDownloads, static function ($a, $b) {
        $aTime = strtotime((string)($a['updated_at'] ?? $a['uploaded_at'] ?? '')) ?: 0;
        $bTime = strtotime((string)($b['updated_at'] ?? $b['uploaded_at'] ?? '')) ?: 0;
        return $bTime <=> $aTime;
    });
    $latestDownloads = array_slice($visibleDownloads, 0, 5);

    $popularDownloads = $visibleDownloads;
    usort($popularDownloads, static function ($a, $b) {
        $aCount = (int)($a['download_count'] ?? $a['downloads'] ?? 0);
        $bCount = (int)($b['download_count'] ?? $b['downloads'] ?? 0);
        return $bCount <=> $aCount;
    });
    $popularDownloads = array_slice($popularDownloads, 0, 5);

    $totalSizeLabel = downloads_format_bytes(downloads_total_size_bytes($visibleDownloads));
    $hasVisibleDownloads = !empty($visibleDownloads);

    ?>
    <section class="downloads-dashboard card">
        <div class="downloads-dashboard-head">
            <div class="shoutbox-headline">
                <div class="shoutbox-title-wrap">
                    <h1><?= htmlspecialchars($languageService->get('download_area'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p><?= htmlspecialchars($languageService->get('download_area_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="shoutbox-status">
                    <span class="shoutbox-status-dot"></span>
                    Download
                </div>
            </div>
        </div>

        <div class="downloads-stat-grid">
            <article class="downloads-stat-card">
                <div class="downloads-stat-icon gray"><i class="bi bi-file-earmark-fill"></i></div>
                <div>
                    <strong><?= $statsFileCount ?></strong>
                    <span><?= htmlspecialchars($languageService->get('files_label'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </article>
            <article class="downloads-stat-card">
                <div class="downloads-stat-icon green"><i class="bi bi-folder-fill"></i></div>
                <div>
                    <strong><?= $statsCategoryCount ?></strong>
                    <span><?= htmlspecialchars($languageService->get('categories_label'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </article>
            <article class="downloads-stat-card">
                <div class="downloads-stat-icon blue"><i class="bi bi-cloud-arrow-down-fill"></i></div>
                <div>
                    <strong><?= $statsDownloads ?></strong>
                    <span><?= htmlspecialchars($languageService->get('downloads_label'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </article>
            <article class="downloads-stat-card">
                <div class="downloads-stat-icon gold"><i class="bi bi-device-hdd-fill"></i></div>
                <div>
                    <strong><?= htmlspecialchars($totalSizeLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars($languageService->get('total_size'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </article>
        </div>

        <section class="downloads-panel">
            <header class="downloads-panel-head">
                <h3><i class="bi bi-folder2-open"></i> <?= htmlspecialchars($languageService->get('all_categories'), ENT_QUOTES, 'UTF-8') ?></h3>
            </header>
            <?php if ($statsCategoryCount > 0): ?>
                <div class="downloads-category-grid">
                    <?php
                    $categoryIndex = 0;
                    foreach ($visibleByCategory as $category):
                        if ($category['count'] <= 0) {
                            continue;
                        }
                        $accent = downloads_category_accent($categoryIndex++);
                        $categoryLink = SeoUrlHandler::convertToSeoUrl('index.php?site=downloads&action=cat_list&id=' . (int)$category['categoryID']);
                        $categoryTitle = htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8');
                        $categoryDescription = trim((string)$category['description']);
                        ?>
                        <a class="downloads-category-card accent-<?= $accent['class'] ?>" href="<?= $categoryLink ?>">
                            <div class="downloads-category-card-icon"><i class="bi <?= $accent['icon'] ?>"></i></div>
                            <div class="downloads-category-card-copy">
                                <strong><?= $categoryTitle ?></strong>
                                <p><?= htmlspecialchars($categoryDescription !== '' ? downloads_excerpt($categoryDescription, 58) : $languageService->get('files_and_resources'), ENT_QUOTES, 'UTF-8') ?></p>
                                <span><i class="bi bi-file-earmark-fill"></i> <?= (int)$category['count'] ?> <?= htmlspecialchars($languageService->get((int)$category['count'] === 1 ? 'file_singular' : 'file_plural'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <i class="bi bi-chevron-right downloads-category-card-arrow"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php downloads_render_info_alert($languageService, 'no_categories_available', $languageService->get('no_categories_available')); ?>
            <?php endif; ?>
        </section>

        <div class="downloads-lists-grid">
            <section class="downloads-panel">
                <header class="downloads-panel-head">
                    <h3><i class="bi bi-clock-fill"></i> <?= htmlspecialchars($languageService->get('latest_files'), ENT_QUOTES, 'UTF-8') ?></h3>
                </header>
                <div class="downloads-panel-body">
                    <?php if (!empty($latestDownloads)): ?>
                        <?php foreach ($latestDownloads as $download): ?>
                            <?php
                            $meta = !empty($download['updated_at'])
                                ? date('d.m.Y', strtotime((string)$download['updated_at']))
                                : '-';
                            downloads_render_list_item($download, $meta);
                            ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php downloads_render_info_alert($languageService, 'no_downloads_available', $languageService->get('no_downloads_available')); ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="downloads-panel">
                <header class="downloads-panel-head">
                    <h3><i class="bi bi-fire"></i> <?= htmlspecialchars($languageService->get('most_downloaded'), ENT_QUOTES, 'UTF-8') ?></h3>
                </header>
                <div class="downloads-panel-body">
                    <?php if (!empty($popularDownloads)): ?>
                        <?php foreach ($popularDownloads as $download): ?>
                            <?php
                            $meta = ((int)($download['download_count'] ?? $download['downloads'] ?? 0)) . ' ' . $languageService->get('downloads');
                            downloads_render_list_item($download, $meta);
                            ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php downloads_render_info_alert($languageService, 'no_downloads_available', $languageService->get('no_downloads_available')); ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </section>
    <?php
}

echo '</div>';
?>
