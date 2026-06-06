<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
global $languageService;

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen

if (!function_exists('downloads_admin_column_exists')) {
    function downloads_admin_column_exists(string $table, string $column): bool
    {
        $res = safe_query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . escape($column) . "'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

AccessControl::checkAdminAccess('downloads');

// Kategorien auslesen
$resultCats = safe_query("SELECT * FROM plugins_downloads_categories ORDER BY title ASC");
$cats = [];
while ($row = mysqli_fetch_array($resultCats, MYSQLI_ASSOC)) {
    $cats[$row['categoryID']] = $row['title'];
}

// Rollen auslesen
$role_result = safe_query("SELECT role_name, modulname FROM user_roles WHERE is_active = 1 ORDER BY role_name ASC");
$allRoles = [];
while ($role = mysqli_fetch_array($role_result, MYSQLI_ASSOC)) {
    $allRoles[] = $role;
}

// Aktion und ID
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Kategorien-Formular
$catAction = $_GET['cataction'] ?? '';
$catID = isset($_GET['catid']) ? (int)$_GET['catid'] : 0;
$catErrors = [];
$catSuccess = '';
$catTitle = '';
$catDescription = '';

if ($action === 'addcategory') {
    $catAction = 'add';
} elseif ($action === 'editcategory') {
    $catAction = 'edit';
}

if ($catAction === 'delete' && $catID > 0) {
    safe_query("DELETE FROM plugins_downloads_categories WHERE categoryID=$catID");
    nx_audit_delete('admin_downloads', (string)$catID, (string)$catID, 'admincenter.php?site=admin_downloads&action=categories');
    nx_alert('success', 'alert_deleted', false);
    $catAction = '';
}

if ($catAction === 'edit' && $catID > 0) {
    $res = safe_query("SELECT * FROM plugins_downloads_categories WHERE categoryID=$catID");
    if ($res && $res->num_rows) {
        $row = $res->fetch_assoc();
        $catTitle = $row['title'];
        $catDescription = $row['description'];
    } else {
        nx_alert('danger', 'alert_not_found', false);
        $catAction = '';
    }
}

if (in_array($catAction, ['add','edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $catTitleInput = trim($_POST['cat_title'] ?? '');
    $catDescriptionInput = trim($_POST['cat_description'] ?? '');

    if ($catTitleInput === '') {
        nx_alert('danger', 'alert_missing_required', false);
    } else {
        $catTitleEscaped = $catTitleInput;
        $catDescriptionEscaped = $catDescriptionInput;

        if ($catAction === 'add') {
            safe_query("INSERT INTO plugins_downloads_categories (title, description) VALUES ('$catTitleEscaped', '$catDescriptionEscaped')");
            $newId = (int)($_database->insert_id ?? 0);
            nx_audit_create('admin_downloads', (string)$newId, $catTitleEscaped, 'admincenter.php?site=admin_downloads&action=categories');
        } else {
            safe_query("UPDATE plugins_downloads_categories SET title='$catTitleEscaped', description='$catDescriptionEscaped' WHERE categoryID=$catID");
            nx_audit_update('admin_downloads', (string)$catID, true, $catTitleEscaped, 'admincenter.php?site=admin_downloads&action=categories');
        }

        nx_redirect('admincenter.php?site=admin_downloads&action=categories', 'success', 'alert_saved', false);
    }
}

// Initialwerte Download
$dl = [
    'categoryID' => '',
    'title' => '',
    'description' => '',
    'file' => '',
    'access_roles' => json_encode([]),
];

if ($action === 'delete' && $id > 0) {
    $res = safe_query("SELECT file, title FROM plugins_downloads WHERE id = $id");
    if ($res && $res->num_rows) {
        $row = $res->fetch_assoc();
        $fileToDelete = __DIR__ . '/../files/' . $row['file'];
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }
        safe_query("DELETE FROM plugins_downloads WHERE id = $id");
        nx_audit_delete('admin_downloads', (string)$id, $row['title'] ?? (string)$id, 'admincenter.php?site=admin_downloads');
        nx_alert('success', 'alert_deleted', false);
    } else {
        nx_alert('danger', 'alert_not_found', false);
    }
    $action = '';
}

if ($action === 'edit' && $id > 0) {
    $res = safe_query("SELECT * FROM plugins_downloads WHERE id = $id");
    if ($res && $res->num_rows) {
        $dl = $res->fetch_assoc();
    } else {
        nx_alert('danger', 'alert_not_found', false);
        $action = '';
    }
}

// Formularverarbeitung add/edit
if (in_array($action, ['add', 'edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryID    = (int)($_POST['categoryID'] ?? 0);
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $currentUserID = (int)($_SESSION['userID'] ?? 0);

    // Rollen normalisieren
    $selectedRoles = $_POST['access_roles'] ?? [];
    if (!is_array($selectedRoles)) $selectedRoles = [];
    $selectedRoles = array_values(array_unique(array_map('trim', $selectedRoles)));

    if ($categoryID <= 0) {
        nx_alert('danger', 'alert_missing_required', false);
        $errors[] = 'Bitte eine Kategorie wählen.';
    }
    if ($title === '') {
        nx_alert('danger', 'alert_missing_required', false);
        $errors[] = 'Bitte einen Titel angeben.';
    }

    $uploadDir = __DIR__ . '/../files/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = $dl['file'];

    if ($action === 'add' || (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE)) {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            nx_alert('danger', 'alert_upload_failed', false);
        } else {
            $allowedExts = ['exe','zip','pdf','jpg','png'];
            $uploadedFilename = basename($_FILES['file']['name']);
            $ext = strtolower(pathinfo($uploadedFilename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts, true)) {
                nx_alert('danger', 'alert_upload_error', false);
            } else {
                if ($action === 'edit' && $filename && file_exists($uploadDir . $filename)) {
                    unlink($uploadDir . $filename);
                }

                $originalName = pathinfo($uploadedFilename, PATHINFO_FILENAME);
                $safeName = preg_replace('/[^a-zA-Z0-9-_]/', '', $originalName);
                $filename = 'dl_' . $safeName . '_' . uniqid() . '.' . $ext;

                $dest = $uploadDir . $filename;

                if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                    nx_alert('danger', 'alert_upload_failed', false);
                }
            }
        }
    }

    if (empty($errors)) {
        $rolesJson = json_encode($selectedRoles, JSON_UNESCAPED_UNICODE);
        $esc_title = $title;
        $esc_description = $description;
        $esc_filename = $filename;
        $hasUserIdColumn = downloads_admin_column_exists('plugins_downloads', 'userID');

        if ($action === 'add') {
            if ($hasUserIdColumn) {
                safe_query("
                    INSERT INTO plugins_downloads
                    (categoryID, userID, title, description, file, access_roles, downloads, uploaded_at)
                    VALUES
                    ('$categoryID', '$currentUserID', '$esc_title', '$esc_description', '$esc_filename', '$rolesJson', 0, NOW())"
                );
            } else {
                safe_query("
                    INSERT INTO plugins_downloads
                    (categoryID, title, description, file, access_roles, downloads, uploaded_at)
                    VALUES
                    ('$categoryID', '$esc_title', '$esc_description', '$esc_filename', '$rolesJson', 0, NOW())"
                );
            }
            $newId = (int)($_database->insert_id ?? 0);
            nx_audit_create('admin_downloads', (string)$newId, $esc_title, 'admincenter.php?site=admin_downloads');
        } else {
            $updateFields = [
                "categoryID='$categoryID'",
                "title='$esc_title'",
                "description='$esc_description'",
                "file='$esc_filename'",
                "access_roles='$rolesJson'",
                "updated_at = NOW()",
            ];
            if ($hasUserIdColumn) {
                $updateFields[] = "userID = IF(userID = 0, '$currentUserID', userID)";
            }
            safe_query("
                UPDATE plugins_downloads SET
                " . implode(",\n                ", $updateFields) . "
                WHERE id=$id
            ");
            nx_audit_update('admin_downloads', (string)$id, true, $esc_title, 'admincenter.php?site=admin_downloads');
        }

        nx_redirect('admincenter.php?site=admin_downloads', 'success', 'alert_saved', false);
    }
}

// Liste Downloads
$resultDownloads = safe_query("
    SELECT d.*, c.title AS category_title
    FROM plugins_downloads d
    LEFT JOIN plugins_downloads_categories c ON d.categoryID = c.categoryID
    ORDER BY d.uploaded_at DESC
");
$downloads = [];
while ($row = mysqli_fetch_array($resultDownloads, MYSQLI_ASSOC)) {
    $downloads[] = $row;
}

// Liste Kategorien (neu laden)
$resultCats = safe_query("SELECT * FROM plugins_downloads_categories ORDER BY title ASC");
$cats = [];
while ($row = mysqli_fetch_array($resultCats, MYSQLI_ASSOC)) {
    $cats[$row['categoryID']] = $row['title'];
}

// Download-Zähler aus der Log-Tabelle abrufen
$resultLogCounts = safe_query("
    SELECT fileID, COUNT(*) AS download_count
    FROM plugins_downloads_logs
    GROUP BY fileID
");

$downloadCounts = [];
while ($row = mysqli_fetch_array($resultLogCounts, MYSQLI_ASSOC)) {
    $downloadCounts[$row['fileID']] = $row['download_count'];
}
?>

<a href="?site=admin_downloads&action=add" class="btn btn-secondary mb-3 mt-2"><?= mb_convert_case($languageService->get('download'),MB_CASE_TITLE,'UTF-8'). ' ' .mb_strtolower($languageService->get('add'),'UTF-8')?></a>
<a href="?site=admin_downloads&action=categories" class="btn btn-secondary mb-3 ms-2 mt-2"><?= $languageService->get('categories') ?></a>
<a href="?site=admin_download_stats" class="btn btn-secondary mb-3 ms-2 mt-2"><?= $languageService->get('btn_dl_statistic') ?></a>

<?php if ($action === 'add' || $action === 'edit'): ?>

  <div class="row g-4">
    <!-- Formular -->
    <div class="col-12 col-lg-8">
      <div class="card shadow-sm h-100 mt-3">
        <div class="card-header">
          <div class="card-title">
            <i class="bi bi-download"></i> <span><?= $languageService->get('title_manage_dl') ?></span>
            <small class="text-muted"><?= $action === 'edit' ? $languageService->get('edit') : $languageService->get('add') ?></small>
          </div>
        </div>

        <div class="card-body">
          <form method="post" enctype="multipart/form-data" id="downloadForm" novalidate>
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label"><?= $languageService->get('category') ?></label>
                <select name="categoryID" class="form-select" required>
                  <option value=""><?= $languageService->get('select_pls_choose') ?></option>
                  <?php foreach ($cats as $catID => $catTitle): ?>
                    <option value="<?= $catID ?>" <?= $dl['categoryID']==$catID?'selected':'' ?>>
                      <?= htmlspecialchars($catTitle) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label"><?= $languageService->get('label_title') ?></label>
                <input type="text" name="title" class="form-control"
                       value="<?= htmlspecialchars($dl['title']) ?>" required>
              </div>

              <div class="col-12">
                <label class="form-label"><?= $languageService->get('description') ?></label>
                <textarea name="description" class="form-control" data-editor="nx_editor" rows="4"><?= htmlspecialchars($dl['description']) ?></textarea>
              </div>

              <div class="col-12">
                <label class="form-label"><?= $action === 'edit' ? $languageService->get('label_edit_file') : $languageService->get('label_file') ?></label>
                <input type="file" name="file" class="form-control"
                       <?= $action==='edit'?'':'required' ?> accept=".zip,.pdf,.jpg,.png">
                <?php if ($action==='edit' && $dl['file']): ?>
                  <div class="form-text"><?= $languageService->get('label_current_file') ?>: <?= htmlspecialchars($dl['file']) ?></div>
                <?php endif; ?>
              </div>

              <div class="col-12">
                <label class="form-label">
                  <?= $languageService->get('label_access') ?>
                </label>

                <div class="border rounded-3 p-3" id="accessRolesWrapper">
                  <?php
                    $selectedRoles = json_decode($dl['access_roles'], true) ?: [];
                    $roleNameToKey = [];
                    foreach ($allRoles as $role) {
                        $roleNameToKey[(string)$role['role_name']] = (string)$role['modulname'];
                    }
                    $selectedRoleKeys = [];
                    foreach ($selectedRoles as $selectedRole) {
                        $selectedRole = (string)$selectedRole;
                        $selectedRoleKeys[] = $roleNameToKey[$selectedRole] ?? $selectedRole;
                    }
                    foreach ($allRoles as $role):
                        $roleValue = (string)$role['modulname'];
                        $roleLabel = (string)$role['role_name'];
                  ?>
                    <div class="form-check form-check-inline">
                      <input
                        class="form-check-input access-role-checkbox"
                        type="checkbox"
                        name="access_roles[]"
                        id="role_<?= htmlspecialchars($roleValue) ?>"
                        value="<?= htmlspecialchars($roleValue) ?>"
                        <?= in_array($roleValue, $selectedRoleKeys, true) ? 'checked' : '' ?>
                      >
                      <label class="form-check-label" for="role_<?= htmlspecialchars($roleValue) ?>">
                        <?= htmlspecialchars($roleLabel) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
                  <div class="invalid-feedback" id="rolesError" data-invalid-feedback="<?= htmlspecialchars($languageService->get('invalid_feedback'),ENT_QUOTES,'UTF-8') ?>"></div>
              </div>

              <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-primary" id="submitBtn" type="submit">
                  <?= $languageService->get('save') ?>
                </button>
              </div>
            </div>
          </form>

        </div>
      </div>
    </div>

    <!-- Sidecard -->
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm mt-3">
        <div class="card-header">
          <div class="card-title">
            <i class="bi bi-info-circle"></i> <span class="fw-semibold"><?= $languageService->get('label_info') ?></span>
          </div>
        </div>
        <div class="card-body">
          <ul class="list-unstyled ps-3">
            <?= $languageService->get('info_datatypes') ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($action==='categories'): ?>
<!-- Kategorien -->
    <div class="col-12">
      <div class="card h-100 shadow-sm mt-3">
        <div class="card-header">
          <div class="card-title">
            <i class="bi bi-bookmarks"></i> <span><?= $languageService->get('categories') ?></span>
          </div>
        </div>
        <div class="card-body">
            <a href="?site=admin_downloads&action=addcategory" class="btn btn-secondary mb-3 ms-2 mt-2"><?= $languageService->get('add_category') ?></a>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th><?= $languageService->get('id') ?></th>
                  <th><?= $languageService->get('label_title') ?></th>
                  <th><?= $languageService->get('actions') ?></th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($cats)): ?>
                <tr><td colspan="3">Keine Kategorien vorhanden.</td></tr>
              <?php else: ?>
                <?php foreach ($cats as $catID => $catTitle): ?>
                  <tr>
                    <td><?= $catID ?></td>
                    <td><?= htmlspecialchars($catTitle) ?></td>
                    <td>
                      <a href="?site=admin_downloads&action=editcategory&catid=<?= $catID ?>" class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto me-2"><i class="bi bi-pencil-square"></i> <?= $languageService->get('edit') ?></a>
                      <?php $deleteUrl = '?site=admin_downloads&cataction=delete&catid=' . intval($catID); ?>
                      <a href="#" class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-delete-url="<?= htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-trash3"></i> <?= $languageService->get('delete') ?></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>




  <?php elseif ($action==='addcategory' || $action==='editcategory'): ?>



    <div class="row g-4">
        <div class="col-12">
          <div class="card shadow-sm mt-3">
            <div class="card-header">
              <div class="card-title">
                <i class="bi bi-bookmarks"></i> <span><?= $catAction === 'edit'
                    ? $languageService->get('category') . ' ' . mb_strtolower($languageService->get('edit'), 'UTF-8')
                    : $languageService->get('category') . ' ' . mb_strtolower($languageService->get('add'), 'UTF-8')
                ?></span>
              </div>
            </div>

            <div class="card-body">
              <form method="post" action="">
                <div class="row g-3">
                  <div class="col-12">
                    <label for="cat_title" class="form-label"><?= $languageService->get('label_title') ?></label>
                    <input type="text" id="cat_title" name="cat_title" class="form-control"
                          value="<?= htmlspecialchars($catTitle) ?>" required>
                  </div>

                  <div class="col-12">
                    <label for="cat_description" class="form-label"><?= $languageService->get('description') ?></label>
                    <textarea id="cat_description" name="cat_description" class="form-control" rows="4"><?= htmlspecialchars($catDescription) ?></textarea>
                  </div>

                  <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="btn btn-primary">
                      <?= $languageService->get('save') ?>
                    </button>
                  </div>
                </div>
              </form>

            </div>
          </div>
        </div>
    <?php else: ?>

  <div class="row g-4">
    <!-- Downloads -->
    <div class="col-12">
      <div class="card h-100 shadow-sm mt-3">
        <div class="card-header">
          <div class="card-title">
            <i class="bi bi-download"></i> <span><?= $languageService->get('downloads') ?></span>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th><?= $languageService->get('id') ?></th>
                  <th><?= $languageService->get('category') ?></th>
                  <th><?= $languageService->get('label_title') ?></th>
                  <th><?= $languageService->get('label_file') ?></th>
                  <th><?= $languageService->get('downloads') ?></th>
                  <th><?= $languageService->get('label_access') ?></th>
                  <th><?= $languageService->get('actions') ?></th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($downloads)): ?>
                <tr><td colspan="7" class="text-center"><?= $languageService->get('no_entries') ?></td></tr>
              <?php else: ?>
                <?php foreach ($downloads as $d): ?>
                  <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= htmlspecialchars($d['category_title']) ?></td>
                    <td><?= htmlspecialchars($d['title']) ?></td>
                    <td><?= htmlspecialchars($d['file']) ?></td>
                    <td><?= $downloadCounts[$d['id']] ?? 0 ?></td>
                    <td>
                      <?php
                        $roles = json_decode($d['access_roles'], true) ?: [];
                        $roleKeyToLabel = [];
                        foreach ($allRoles as $role) {
                            $roleKeyToLabel[(string)$role['modulname']] = (string)$role['role_name'];
                            $roleKeyToLabel[(string)$role['role_name']] = (string)$role['role_name'];
                        }
                        $roleLabels = array_map(static function ($role) use ($roleKeyToLabel) {
                            $role = (string)$role;
                            return $roleKeyToLabel[$role] ?? $role;
                        }, $roles);
                        echo htmlspecialchars(implode(', ', $roleLabels));
                      ?>
                    </td>
                    <td>
                      <a href="?site=admin_downloads&action=edit&id=<?= $d['id'] ?>" class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto me-2"><i class="bi bi-pencil-square"></i> <?= $languageService->get('edit') ?></a>
                      <?php $deleteUrl = '?site=admin_downloads&action=delete&id=' . intval($d['id']); ?>
                      <a href="#" class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-delete-url="<?= htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-trash3"></i> <?= $languageService->get('delete') ?></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    

  </div>
<?php endif; ?>
  </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<script>
(function () {
  const form = document.getElementById('downloadForm');
  if (!form) return;

  const rolesError = document.getElementById('rolesError');
  const checkboxes = Array.from(document.querySelectorAll('.access-role-checkbox'));

  function selectedCount() {
    return checkboxes.filter(cb => cb.checked).length;
  }

  function showError(show) {
    if (!rolesError) return;

    rolesError.style.display = show ? 'block' : 'none';
    rolesError.textContent = show
      ? rolesError.dataset.invalidFeedback
      : '';
  }

  showError(false);

  checkboxes.forEach(cb => cb.addEventListener('change', () => {
    if (selectedCount() > 0) showError(false);
  }));

  form.addEventListener('submit', function (e) {
    if (selectedCount() === 0) {
      e.preventDefault();
      e.stopPropagation();
      showError(true);
      document.getElementById('accessRolesWrapper')?.scrollIntoView({behavior:'smooth', block:'center'});
    }
  });
})();
</script>
<?php endif; ?>
