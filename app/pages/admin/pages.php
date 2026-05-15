<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pid = (int)($_POST['page_id'] ?? 0);
        db()->prepare("DELETE FROM pages WHERE id=?")->execute([$pid]);
        flash('success','Page deleted.');
    } elseif ($action === 'toggle') {
        $pid = (int)($_POST['page_id'] ?? 0);
        db()->prepare("UPDATE pages SET is_active=1-is_active,updated_at=? WHERE id=?")->execute([now_ts(),$pid]);
    }
    redirect('/admin/pages');
}

$pages = db()->query("SELECT * FROM pages ORDER BY sort_order ASC, id ASC")->fetchAll();

admin_page_start('CMS Pages');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Pages (CMS)</h4>
    <p class="text-muted small mb-0"><?= count($pages) ?> pages</p>
  </div>
  <a href="<?= url('admin/pages/new') ?>" class="btn btn-primary fw-600">
    <i class="bi bi-plus-circle me-1"></i>New Page
  </a>
</div>

<div class="card border-0 shadow-sm rounded-xl">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 small">
  <thead class="table-light">
    <tr>
      <th class="ps-4">Title</th>
      <th>Slug</th>
      <th>Header</th>
      <th>Footer</th>
      <th>Order</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($pages as $pg): ?>
  <tr>
    <td class="ps-4 fw-600"><?= e($pg['title']) ?></td>
    <td class="font-monospace text-muted small">/page/<?= e($pg['slug']) ?></td>
    <td><?= $pg['show_in_header'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
    <td><?= $pg['show_in_footer'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
    <td class="text-muted"><?= $pg['sort_order'] ?></td>
    <td>
      <span class="badge <?= $pg['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
        <?= $pg['is_active'] ? 'Active' : 'Hidden' ?>
      </span>
    </td>
    <td>
      <div class="d-flex gap-1">
        <a href="<?= url('admin/pages/'.$pg['id']) ?>" class="btn btn-sm btn-outline-primary fw-600">
          <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="<?= url('page/'.$pg['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-eye"></i>
        </a>
        <form method="POST" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action"  value="toggle">
          <input type="hidden" name="page_id" value="<?= $pg['id'] ?>">
          <button class="btn btn-sm <?= $pg['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
            <i class="bi bi-<?= $pg['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
          </button>
        </form>
        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this page?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action"  value="delete">
          <input type="hidden" name="page_id" value="<?= $pg['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$pages): ?>
  <tr><td colspan="7" class="text-center py-5 text-muted">No pages yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php admin_page_end(); ?>
