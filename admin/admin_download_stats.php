<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
global $languageService;

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('downloads');

if (isset($_database)) {
    if ($res = $_database->query("SELECT COUNT(*) AS c FROM plugins_downloads_logs")) {
        $row = $res->fetch_assoc();
        $totalDownloads = (int)($row['c'] ?? 0);
    }

    if ($res = $_database->query("SELECT COUNT(DISTINCT userID) AS c FROM plugins_downloads_logs")) {
        $row = $res->fetch_assoc();
        $uniqueUsers = (int)($row['c'] ?? 0);
    }

    if ($res = $_database->query("SELECT COUNT(*) AS c FROM plugins_downloads_logs WHERE downloaded_at >= (NOW() - INTERVAL 30 DAY)")) {
        $row = $res->fetch_assoc();
        $downloads30d = (int)($row['c'] ?? 0);
    }
}

// Top 10 (chart + list)
$queryTop10 = "
    SELECT d.id, d.title, d.file, COUNT(l.logID) AS download_count
    FROM plugins_downloads_logs l
    JOIN plugins_downloads d ON d.id = l.fileID
    GROUP BY l.fileID
    ORDER BY download_count DESC
    LIMIT 10
";
$resultTop10 = $_database->query($queryTop10);

$top10Data = [];
while ($row = $resultTop10->fetch_assoc()) {
    $top10Data[] = [
        'title' => (string)$row['title'],
        'file'  => (string)$row['file'],
        'count' => (int)$row['download_count'],
    ];
}

// Recent 50
$downloads_per_page = 7; // Anzahl an Einträge je Pagination-Seite
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

// Pagination letzte 50 Einträge
$totalRecent = isset($totalDownloads) ? min((int)$totalDownloads, 50) : 0;
$total_pages = ($totalRecent > 0) ? (int)ceil($totalRecent / $downloads_per_page) : 1;
$page = min($page, $total_pages);
$offset = ($page - 1) * $downloads_per_page;

// URL-Builder
if (!function_exists('nx_build_url_with_page')) {
    function nx_build_url_with_page(int $p): string {
        $qs = $_GET;
        $qs['page'] = $p;
        return 'admincenter.php?' . http_build_query($qs);
    }
}

$queryRecent = "
    SELECT r.logID, r.userID, r.username, r.title, r.file, r.downloaded_at
    FROM (
        SELECT l.logID, u.userID AS userID, u.username, d.title, d.file, l.downloaded_at
        FROM plugins_downloads_logs l
        JOIN users u ON u.userID = l.userID
        JOIN plugins_downloads d ON d.id = l.fileID
        ORDER BY l.downloaded_at DESC
        LIMIT 50
    ) AS r
    ORDER BY r.downloaded_at DESC
    LIMIT {$offset}, {$downloads_per_page}
";
$resultRecent = $_database->query($queryRecent);

echo '<div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100 mt-4">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-download"></i> <span>' . $languageService->get('title_top10') . '</span>
                        <small class="text-muted">' . $languageService->get('subtitle_ranking') . '</small>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chart-container" style="position:relative; width:100%; min-height:420px;">
                        <div id="top10Chart"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100 mt-4">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-calendar-date"></i> <span>' . $languageService->get('title_last50') . '</span>
                        <small class="text-muted">' . $languageService->get('subtitle_newest') . '</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:80px;">#</th>
                                    <th>' . $languageService->get('user') . '</th>
                                    <th>' . $languageService->get('th_dl_title') . '</th>
                                    <th>' . $languageService->get('th_filename') . '</th>
                                    <th style="width:190px;">' . $languageService->get('date') . '</th>
                                </tr>
                            </thead>
                            <tbody>';
                                while ($row = $resultRecent->fetch_assoc()) {
                                    echo '<tr>
                                            <td>'.(int)$row['logID'].'</td>
                                            <td><a href="index.php?site=profile&userID=' . (int)$row['userID'] . '" class="fw-semibold" target="_blank">' . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . '</a></td>
                                            <td>'.htmlspecialchars($row['title']).'</td>
                                            <td class="text-truncate" style="max-width: 420px;">'.htmlspecialchars($row['file']).'</td>
                                            <td>'.htmlspecialchars($row['downloaded_at']).'</td>
                                        </tr>';
                                }
                            echo '</tbody>
                        </table>
                    </div>';

                    // Pagination
                    if ($total_pages > 1) {
                        echo '<nav aria-label="Seiten-Navigation" class="mt-3">'
                           . '  <ul class="pagination justify-content-center">';

                        // Prev
                        echo '    <li class="page-item ' . (($page <= 1) ? 'disabled' : '') . '">';
                        if ($page <= 1) {
                            echo '      <span class="page-link" aria-disabled="true" aria-label="' . htmlspecialchars($languageService->get('previous'), ENT_QUOTES, 'UTF-8') . '">' 
                               . '        <i class="bi bi-chevron-left" aria-hidden="true"></i>'
                               . '      </span>';
                        } else {
                            echo '      <a class="page-link" href="' . htmlspecialchars(nx_build_url_with_page($page - 1), ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($languageService->get('previous'), ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($languageService->get('previous'), ENT_QUOTES, 'UTF-8') . '">'
                               . '        <i class="bi bi-chevron-left" aria-hidden="true"></i>'
                               . '      </a>';
                        }
                        echo "</li>";

                        // Pages
                        for ($i = 1; $i <= $total_pages; $i++) {
                            echo '    <li class="page-item ' . (($i == $page) ? 'active' : '') . '">';
                            if ($i == $page) {
                                echo '      <span class="page-link" aria-current="page">' . $i . '</span>';
                            } else {
                                echo '      <a class="page-link" href="' . htmlspecialchars(nx_build_url_with_page($i), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
                            }
                            echo "</li>";
                        }

                        // Next
                        echo '    <li class="page-item ' . (($page >= $total_pages) ? 'disabled' : '') . '">';
                        if ($page >= $total_pages) {
                            echo '      <span class="page-link" aria-disabled="true" aria-label="' . htmlspecialchars($languageService->get('next'), ENT_QUOTES, 'UTF-8') . '">' 
                               . '        <i class="bi bi-chevron-right" aria-hidden="true"></i>'
                               . '      </span>';
                        } else {
                            echo '      <a class="page-link" href="' . htmlspecialchars(nx_build_url_with_page($page + 1), ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($languageService->get('next'), ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($languageService->get('next'), ENT_QUOTES, 'UTF-8') . '">'
                               . '        <i class="bi bi-chevron-right" aria-hidden="true"></i>'
                               . '      </a>';
                        }
                        echo "</li>";

                        echo "  </ul></nav>";
                    }

                    echo '
                </div>
            </div>
        </div>
    </div>';
?>

<script>
  window.translations = {
    downloads: <?= json_encode($languageService->get('downloads')) ?>,
    file: <?= json_encode($languageService->get('label_file')) ?>,
    chartNoData: <?= json_encode($languageService->get('chart_no_data')) ?>
  };
    const top10Data = <?php echo json_encode($top10Data); ?>;

    const labels = top10Data.map(i => i.title);
    const counts = top10Data.map(i => i.count);

    function cssVar(name, fallback = '') {
        const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    const AC_PRIMARY   = cssVar('--ac-primary', '#fe821d');
    const AC_SECONDARY = cssVar('--ac-secondary', '#6B7280');

    const dynamicHeight = Math.max(420, top10Data.length * 40);

    const options = {
        chart: {
            type: 'bar',
            height: dynamicHeight,
            toolbar: { show: false },
            fontFamily: 'inherit',
            animations: { enabled: true, easing: 'easeout', speed: 900 }
        },
        series: [{ name: 'Downloads', data: counts }],
        colors: [AC_PRIMARY],
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '60%',
                borderRadius: 6,
                distributed: false
            }
        },
        stroke: { show: false },
        dataLabels: { enabled: false },
        grid: { show: false },
        xaxis: {
            categories: labels,
            labels: {
                style: { colors: AC_SECONDARY },
                formatter: (v) => String(Math.round(v))
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: { style: { colors: AC_SECONDARY, fontWeight: 600 } }
        },
        tooltip: {
        y: {
            formatter: function (val, opts) {
            const idx = opts.dataPointIndex;
            const file = top10Data[idx] ? top10Data[idx].file : '';

            return `${val} ${window.translations.downloads} (${window.translations.file}: ${file})`;
            }
        }
        },
        legend: { show: false }
    };

    function renderTop10Chart() {
        const el = document.querySelector('#top10Chart');
        if (!el) return;

        if (!top10Data || top10Data.length === 0) {
        el.innerHTML =
            `<div class="text-muted p-3">${window.translations.chartNoData}</div>`;
        return;
        }

        if (typeof ApexCharts === 'undefined') {
            setTimeout(renderTop10Chart, 50);
            return;
        }

        el.innerHTML = '';
        const chart = new ApexCharts(el, options);
        chart.render();
    }

    window.addEventListener('load', renderTop10Chart);
</script>