<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_role('admin');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf(); $id=(int)($_POST['voter_id']??0); $action=$_POST['action']??'';
    if ($action==='create') {
        $name=trim($_POST['full_name']??''); $email=strtolower(trim($_POST['email']??''));
        $ref=trim($_POST['voter_reference']??''); $password=$_POST['password']??'';
        if ($name && filter_var($email,FILTER_VALIDATE_EMAIL) && $ref && strlen($password)>=10) {
            try {
                $pdo->beginTransaction();
                $s=$pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,status) VALUES(?,?,?,'voter','active')");
                $s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                $uid=(int)$pdo->lastInsertId();
                $s=$pdo->prepare("INSERT INTO voters(user_id,voter_reference,approval_status,approved_by,approved_at) VALUES(?,?,'approved',?,NOW())");
                $s->execute([$uid,$ref,current_user_id()]);
                $pdo->commit(); log_action($pdo,'voter_created',$email); notify_user($pdo,$uid,'Voter account created','An administrator created and approved your voter account.'); flash('Voter account created and approved.');
            } catch(Throwable $ex) {
                if($pdo->inTransaction()) $pdo->rollBack();
                flash('Could not create voter. Check whether the email or voter reference is already used.','error');
            }
        } else flash('Complete all fields. Password must be at least 4 characters.','error');
        redirect('/voting_system/admin/voters.php');
    }
    if ($action==='approve') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE voters SET approval_status='approved',approved_by=?,approved_at=NOW() WHERE voter_id=?")->execute([current_user_id(),$id]);
        $pdo->prepare("UPDATE users u JOIN voters v ON v.user_id=u.user_id SET u.status='active' WHERE v.voter_id=?")->execute([$id]);
        $pdo->commit(); log_action($pdo,'voter_approved','Approved voter_id='.$id); notify_user($pdo,(int)$pdo->query("SELECT user_id FROM voters WHERE voter_id=".(int)$id)->fetchColumn(),'Account approved','Your voter account has been approved.'); flash('Voter approved.');
    } elseif($action==='reject') {
        $pdo->prepare("UPDATE voters SET approval_status='rejected' WHERE voter_id=?")->execute([$id]);
        $pdo->prepare("UPDATE users u JOIN voters v ON v.user_id=u.user_id SET u.status='rejected' WHERE v.voter_id=?")->execute([$id]);
        log_action($pdo,'voter_rejected','Rejected voter_id='.$id); flash('Voter registration rejected.');
    } elseif($action==='suspend') {
        $pdo->prepare("UPDATE users u JOIN voters v ON v.user_id=u.user_id SET u.status='suspended' WHERE v.voter_id=?")->execute([$id]);
        log_action($pdo,'voter_suspended','Suspended voter_id='.$id); flash('Voter account suspended.');
    } elseif($action==='activate_account') {
        $pdo->prepare("UPDATE users u JOIN voters v ON v.user_id=u.user_id SET u.status='active' WHERE v.voter_id=? AND v.approval_status='approved'")->execute([$id]);
        log_action($pdo,'voter_activated','Activated voter_id='.$id); flash('Voter account activated.');
    }
    redirect('/voting_system/admin/voters.php');
}
page_header('Voter Management', ['subtitle' => 'Review registrations and manage voter accounts']);
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter && in_array($statusFilter,['pending','approved','rejected'],true)) {
  $st=$pdo->prepare("SELECT v.voter_id,v.voter_reference,v.approval_status,u.full_name,u.email,u.created_at,u.status AS account_status FROM voters v JOIN users u ON u.user_id=v.user_id WHERE v.approval_status=? ORDER BY u.created_at DESC");
  $st->execute([$statusFilter]); $rows=$st->fetchAll();
} else {
  $rows=$pdo->query("SELECT v.voter_id,v.voter_reference,v.approval_status,u.full_name,u.email,u.created_at,u.status AS account_status FROM voters v JOIN users u ON u.user_id=v.user_id ORDER BY u.created_at DESC")->fetchAll();
}
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Register a voter manually</h2><p>Administrators can create immediately approved voter accounts.</p></div></div>
    <form method="post" class="form-card wide" style="box-shadow:none;border:0;padding:0;max-width:none">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create">
      <div class="form-row">
        <label>Full name<input name="full_name" required></label>
        <label>Email<input type="email" name="email" required></label>
      </div>
      <div class="form-row">
        <label>Voter reference / ID<input name="voter_reference" required></label>
        <label>Temporary password<input type="password" name="password" minlength="4" required></label>
      </div>
      <div><button class="btn">Create voter account</button></div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Registered voters</h2><p>Approve, reject, or review voter registrations.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Reference</th><th>Status</th><th>Registered</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><strong><?=e($r['full_name'])?></strong></td>
            <td><?=e($r['email'])?></td>
            <td><?=e($r['voter_reference'])?></td>
            <td><span class="badge <?= match($r['approval_status']){'approved'=>'green','pending'=>'orange','rejected'=>'red',default=>'gray'} ?>"><?=e(ucfirst($r['approval_status']))?></span> <span class="badge <?= match($r['account_status']){'active'=>'green','pending'=>'orange','suspended'=>'red','rejected'=>'red',default=>'gray'} ?>" style="margin-left:4px"><?=e(ucfirst($r['account_status']))?></span></td>
            <td><?=e(date('M j, Y', strtotime($r['created_at'])))?></td>
            <td>
              <form class="inline" method="post">
                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                <input type="hidden" name="voter_id" value="<?=$r['voter_id']?>">
                <?php if($r['approval_status']==='pending'): ?>
                  <button class="btn small" name="action" value="approve">Approve</button>
                  <button class="btn danger small" name="action" value="reject">Reject</button>
                <?php elseif($r['account_status']==='active'): ?>
                  <button class="btn danger small" name="action" value="suspend">Suspend</button>
                <?php elseif($r['account_status']==='suspended' && $r['approval_status']==='approved'): ?>
                  <button class="btn small" name="action" value="activate_account">Activate</button>
                <?php else: ?><span class="muted">—</span><?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="6" class="muted">No voters registered yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_footer(); ?>