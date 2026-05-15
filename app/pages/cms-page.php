<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'] ?? ''));
if (!$slug) { redirect('/'); }

$pg = db()->prepare("SELECT * FROM pages WHERE slug=? AND is_active=1 LIMIT 1");
$pg->execute([$slug]); $pg = $pg->fetch();
if (!$pg) { flash('error', 'Page not found.'); redirect('/'); }

page_start(e($pg['title']));
?>
<div class="container py-5" style="max-width:820px">
  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= url() ?>" class="text-decoration-none">Home</a></li>
      <li class="breadcrumb-item active"><?= e($pg['title']) ?></li>
    </ol>
  </nav>
  <h1 class="fw-bold mb-4"><?= e($pg['title']) ?></h1>
  <div class="card border-0 shadow-sm rounded-xl p-4 p-md-5">
    <div class="cms-content">
      <?= $pg['content'] /* Admin-trusted HTML */ ?>
    </div>
  </div>
</div>
<style>
.cms-content h2,.cms-content h3,.cms-content h4{font-weight:700;margin-top:1.5rem;margin-bottom:.75rem}
.cms-content p{color:#374151;line-height:1.75;margin-bottom:1rem}
.cms-content ul,.cms-content ol{padding-left:1.5rem;margin-bottom:1rem;color:#374151}
.cms-content li{margin-bottom:.4rem}
.cms-content a{color:var(--vp-primary)}
</style>
<?php page_end(); ?>
