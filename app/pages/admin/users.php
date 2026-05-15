<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_moderator();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $uid = (int)($_POST['user_id'] ?? 0);
    $act = $_POST['action'] ?? '';

    if ($uid && $act === 'toggle_block') {
        $u = db()->prepare("SELECT status,is_admin,name FROM users WHERE id=? LIMIT 1"); $u->execute([$uid]); $u=$u->fetch();
        if ($u && !$u['is_admin']) {
            $ns = $u['status']==='blocked'?'active':'blocked';
            db()->prepare("UPDATE users SET status=?,updated_at=? WHERE id=?")->execute([$ns,now_ts(),$uid]);
            audit('user_'.($ns==='blocked'?'blocked':'unblocked'), 'User ID '.$uid.': '.($u['name']??''), null, 'user', $uid);
            flash('success','User '.($ns==='blocked'?'blocked':'unblocked').'.');
        }
    } elseif ($uid && $act === 'set_role' && is_admin()) {
        $role = in_array($_POST['role']??'',['user','moderator'],true)?$_POST['role']:'user';
        db()->prepare("UPDATE users SET role=?,updated_at=? WHERE id=? AND is_admin=0")->execute([$role,now_ts(),$uid]);
        audit('user_role_changed','Role set to '.$role.' for user ID '.$uid, null,'user',$uid);
        flash('success','Role updated.');
    } elseif ($uid && $act === 'adjust_balance' && is_admin()) {
        $amt  = (float)($_POST['amount']??0);
        $type = $_POST['bal_type']??'credit';
        $note = strip_tags(trim($_POST['note']??'Admin adjustment'));
        if ($amt>0) {
            if ($type==='credit') {
                db()->prepare("UPDATE users SET wallet_balance=wallet_balance+?,total_earned=total_earned+?,updated_at=? WHERE id=?")->execute([$amt,$amt,now_ts(),$uid]);
            } else {
                db()->prepare("UPDATE users SET wallet_balance=GREATEST(0,wallet_balance-?),updated_at=? WHERE id=?")->execute([$amt,now_ts(),$uid]);
            }
            db()->prepare("INSERT INTO transactions(user_id,type,amount,fee,net_amount,currency,status,note,reference,created_at,updated_at) VALUES(?,?,?,0,?,?,?,?,?,?,?)")
                ->execute([$uid,'adjustment',$amt,$amt,setting('currency_code','GHS'),'completed',$note,gen_ref('ADJ'),now_ts(),now_ts()]);
            audit('balance_adjusted','Amount: '.$amt.' ('.$type.') for user ID '.$uid,null,'user',$uid);
            flash('success','Balance adjusted.');
        }
    } elseif ($uid && $act === 'resend_verification') {
        $u=db()->prepare("SELECT * FROM users WHERE id=? LIMIT 1"); $u->execute([$uid]); $u=$u->fetch();
        if ($u) { send_verification_email($u); flash('info','Verification email sent.'); }
    }
    redirect('/admin/users');
}

$search = trim($_GET['q']??''); $status=$_GET['status']??''; $role=$_GET['role']??'';
$page=max(1,(int)($_GET['page']??1)); $per=20; $off=($page-1)*$per;
$where='WHERE is_admin=0'; $params=[];
if ($search) { $where.=' AND (name LIKE ? OR email LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }
if ($status)  { $where.=' AND status=?';  $params[]=$status; }
if ($role)    { $where.=' AND role=?';    $params[]=$role; }
$total=db()->prepare("SELECT COUNT(*) FROM users $where"); $total->execute($params); $total=(int)$total->fetchColumn();
$params[]=$per; $params[]=$off;
$users=db()->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$users->execute($params); $users=$users->fetchAll();

admin_page_start('Users');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Users</h4><p class="text-muted small mb-0"><?= number_format($total) ?> registered users</p></div>
</div>
<div class="card border-0 shadow-sm rounded-xl p-3 mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4"><input type="text" name="q" class="form-control form-control-sm" placeholder="Name or email…" value="<?= e($search) ?>"></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><option value="active" <?= $status==='active'?'selected':'' ?>>Active</option><option value="blocked" <?= $status==='blocked'?'selected':'' ?>>Blocked</option></select></div>
    <div class="col-md-2"><select name="role" class="form-select form-select-sm"><option value="">All Roles</option><option value="user" <?= $role==='user'?'selected':'' ?>>User</option><option value="moderator" <?= $role==='moderator'?'selected':'' ?>>Moderator</option></select></div>
    <div class="col-auto"><button class="btn btn-primary btn-sm fw-600">Filter</button> <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary btn-sm">Reset</a></div>
  </form>
</div>

<div class="card border-0 shadow-sm rounded-xl">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 small">
<thead class="table-light">
  <tr><th class="ps-4">User</th><th>Balance</th><th>Earned</th><th>Tasks</th><th>Role</th><th>Verified</th><th>Status</th><th>Joined</th><th></th></tr>
</thead>
<tbody>
<?php foreach($users as $u): ?>
<tr>
  <td class="ps-4">
    <div class="d-flex align-items-center gap-2">
      <img src="<?= e(avatar_url($u['avatar']??null,$u['name'])) ?>" width="30" height="30" class="rounded-circle">
      <div><div class="fw-600"><?= e($u['name']) ?></div><div class="text-muted" style="font-size:.7rem"><?= e($u['email']) ?></div></div>
    </div>
  </td>
  <td class="fw-700 text-success"><?= money((float)$u['wallet_balance']) ?></td>
  <td><?= money((float)$u['total_earned']) ?></td>
  <td><?= number_format($u['total_tasks_completed']) ?></td>
  <td>
    <span class="badge <?= ($u['role']??'')==='moderator'?'bg-warning text-dark':'bg-secondary' ?>">
      <?= ucfirst($u['role']??'user') ?>
    </span>
  </td>
  <td>
    <?php if ($u['email_verified_at']): ?>
    <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
    <?php else: ?>
    <span class="text-warning"><i class="bi bi-exclamation-circle-fill"></i></span>
    <?php endif; ?>
  </td>
  <td><span class="badge <?= $u['status']==='active'?'bg-success':'bg-danger' ?>"><?= ucfirst($u['status']) ?></span></td>
  <td class="text-muted"><?= date('M d, Y',strtotime($u['created_at'])) ?></td>
  <td>
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0">
        <li><a class="dropdown-item small py-2" href="<?= url('admin/users/'.$u['id']) ?>"><i class="bi bi-eye me-2"></i>View</a></li>
        <?php if (is_admin()): ?>
        <li>
          <form method="POST"><input type="hidden" name="action" value="toggle_block"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><?= csrf_field() ?>
          <button class="dropdown-item small py-2 <?= $u['status']==='blocked'?'text-success':'text-warning' ?>"><i class="bi bi-<?= $u['status']==='blocked'?'unlock':'lock' ?> me-2"></i><?= $u['status']==='blocked'?'Unblock':'Block' ?></button></form>
        </li>
        <li>
          <button class="dropdown-item small py-2" data-bs-toggle="modal" data-bs-target="#roleModal" data-uid="<?= $u['id'] ?>" data-name="<?= e($u['name']) ?>" data-role="<?= e($u['role']??'user') ?>">
            <i class="bi bi-shield me-2"></i>Set Role
          </button>
        </li>
        <li>
          <button class="dropdown-item small py-2" data-bs-toggle="modal" data-bs-target="#balModal" data-uid="<?= $u['id'] ?>" data-name="<?= e($u['name']) ?>">
            <i class="bi bi-wallet2 me-2"></i>Adjust Balance
          </button>
        </li>
        <?php if (!$u['email_verified_at']): ?>
        <li>
          <form method="POST"><input type="hidden" name="action" value="resend_verification"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><?= csrf_field() ?>
          <button class="dropdown-item small py-2 text-info"><i class="bi bi-envelope me-2"></i>Resend Verification</button></form>
        </li>
        <?php endif; ?>
        <?php endif; ?>
      </ul>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="9" class="text-center py-5 text-muted">No users found.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php if ($total>$per): ?><div class="p-3"><?= paginate($total,$per,$page,url('admin/users?'.http_build_query(['q'=>$search,'status'=>$status,'role'=>$role]))) ?></div><?php endif; ?>
</div>

<!-- Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0"><h6 class="fw-bold">Set User Role</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="set_role"><input type="hidden" name="user_id" id="rUid">
  <div class="modal-body">
    <p class="small text-muted">User: <strong id="rName"></strong></p>
    <div class="d-grid gap-2">
      <label class="border rounded-3 p-2 d-flex align-items-center gap-2 cursor-pointer"><input type="radio" name="role" value="user"> <span class="small fw-600">Regular User</span></label>
      <label class="border rounded-3 p-2 d-flex align-items-center gap-2 cursor-pointer"><input type="radio" name="role" value="moderator"> <span class="small fw-600">Moderator</span><span class="badge bg-warning text-dark ms-auto" style="font-size:.6rem">Can review campaigns &amp; payments</span></label>
    </div>
  </div>
  <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary fw-600">Save Role</button></div>
  </form>
</div></div></div>

<!-- Balance Modal -->
<div class="modal fade" id="balModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0"><h6 class="fw-bold">Adjust Balance</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="adjust_balance"><input type="hidden" name="user_id" id="bUid">
  <div class="modal-body">
    <p class="small text-muted mb-3">User: <strong id="bName"></strong></p>
    <div class="mb-3"><label class="form-label small fw-600">Type</label><select name="bal_type" class="form-select form-select-sm"><option value="credit">+ Credit</option><option value="debit">- Debit</option></select></div>
    <div class="mb-3"><label class="form-label small fw-600">Amount (USD)</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required></div>
    <div><label class="form-label small fw-600">Note</label><input type="text" name="note" class="form-control" value="Admin adjustment" required></div>
  </div>
  <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary fw-600">Apply</button></div>
  </form>
</div></div></div>

<script>
document.getElementById('roleModal')?.addEventListener('show.bs.modal',e=>{const b=e.relatedTarget; document.getElementById('rUid').value=b.dataset.uid; document.getElementById('rName').textContent=b.dataset.name; document.querySelectorAll('#roleModal input[type=radio]').forEach(r=>r.checked=r.value===b.dataset.role); });
document.getElementById('balModal')?.addEventListener('show.bs.modal',e=>{const b=e.relatedTarget; document.getElementById('bUid').value=b.dataset.uid; document.getElementById('bName').textContent=b.dataset.name; });
</script>
<?php admin_page_end(); ?>
