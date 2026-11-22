<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_role('admin');

function sync_election_status(PDO $pdo, int $id): void {
    $s=$pdo->prepare("SELECT start_at,end_at,status,results_published FROM elections WHERE election_id=?"); $s->execute([$id]); $e=$s->fetch();
    if(!$e) return;
    if(in_array($e['status'],['draft','cancelled','published'],true)) return;
    $now=time(); $start=strtotime($e['start_at']); $end=strtotime($e['end_at']);
    $new = $now < $start ? 'scheduled' : ($now <= $end ? 'open' : 'closed');
    if($new!==$e['status']) $pdo->prepare("UPDATE elections SET status=? WHERE election_id=?")->execute([$new,$id]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action'] ?? 'create';
    $id=(int)($_POST['election_id'] ?? 0);
    $title=trim($_POST['title']??''); $desc=trim($_POST['description']??''); $category=trim($_POST['category']??'');
    $start=$_POST['start_at']??''; $end=$_POST['end_at']??'';
    if($action==='eligibility_save' && $id){
        $ids = $_POST['voter_ids'] ?? [];
        if(!is_array($ids)) $ids=[];
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM election_eligibility WHERE election_id=?")->execute([$id]);
        $ins=$pdo->prepare("INSERT IGNORE INTO election_eligibility(election_id,voter_id) VALUES(?,?)");
        foreach($ids as $vid){ $vid=(int)$vid; if($vid>0) $ins->execute([$id,$vid]); }
        $pdo->commit();
        log_action($pdo,'election_eligibility_updated','Election #'.$id.' eligible voters set: '.count($ids));
        flash('Election eligibility rules updated.');
        redirect('/voting_system/admin/elections.php?edit='.$id);
    } elseif($action==='eligibility_clear' && $id){
        $pdo->prepare("DELETE FROM election_eligibility WHERE election_id=?")->execute([$id]);
        log_action($pdo,'election_eligibility_cleared','Election #'.$id.' now allows all approved voters');
        flash('Eligibility rules cleared. All approved voters are now eligible.');
        redirect('/voting_system/admin/elections.php?edit='.$id);
    } elseif($action==='create'){
        $allowMulti = isset($_POST['allow_multi_position']) ? 1 : 0;
        if($title && $start && $end && strtotime($end)>strtotime($start)){
            $s=$pdo->prepare("INSERT INTO elections(title,description,category,start_at,end_at,status,created_by,allow_multi_position) VALUES(?,?,?,?,?,'draft',?,?)");
            $s->execute([$title,$desc,$category?:null,$start,$end,current_user_id(),$allowMulti]);
            $newId=(int)$pdo->lastInsertId();
            log_action($pdo,'election_created',$title);
            foreach($pdo->query("SELECT user_id FROM users WHERE role IN ('voter','candidate') AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $uid){ notify_user($pdo,(int)$uid,'New election created','Election "'.$title.'" has been created.'); }
            flash('Election created as draft.');
        } else flash('Provide a title and valid start/end dates.','error');
    } elseif($action==='update' && $id){
        $allowMulti = isset($_POST['allow_multi_position']) ? 1 : 0;
        if($title && $start && $end && strtotime($end)>strtotime($start)){
            $s=$pdo->prepare("UPDATE elections SET title=?,description=?,category=?,start_at=?,end_at=?,allow_multi_position=? WHERE election_id=?");
            $s->execute([$title,$desc,$category?:null,$start,$end,$allowMulti,$id]);
            sync_election_status($pdo,$id);
            log_action($pdo,'election_updated','Election #'.$id.' '.$title);
            flash('Election updated.');
        } else flash('Provide a title and valid start/end dates.','error');
    } elseif($action==='activate' && $id){
        $s=$pdo->prepare("UPDATE elections SET status=CASE WHEN NOW()<start_at THEN 'scheduled' WHEN NOW() BETWEEN start_at AND end_at THEN 'open' ELSE 'closed' END WHERE election_id=? AND status='draft'");
        $s->execute([$id]);
        $t=$pdo->prepare("SELECT title FROM elections WHERE election_id=?"); $t->execute([$id]); $et=$t->fetchColumn()?:'Election';
        log_action($pdo,'election_activated','Election #'.$id);
        foreach($pdo->query("SELECT user_id FROM users WHERE role IN ('voter','candidate') AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $uid){ notify_user($pdo,(int)$uid,'Election activated','"'.$et.'" has been activated.'); }
        flash('Election activated.');
    } elseif($action==='close' && $id){
        $pdo->prepare("UPDATE elections SET status='closed' WHERE election_id=? AND end_at<=NOW() AND status IN ('open','scheduled')")->execute([$id]);
        $t=$pdo->prepare("SELECT title FROM elections WHERE election_id=?"); $t->execute([$id]); $et=$t->fetchColumn()?:'Election';
        log_action($pdo,'election_closed','Election #'.$id);
        foreach($pdo->query("SELECT user_id FROM users WHERE role IN ('voter','candidate') AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $uid){ notify_user($pdo,(int)$uid,'Election closed','"'.$et.'" has been closed.'); }
        flash('Election closed (if end time has passed).');
    } elseif($action==='publish' && $id){
        $s=$pdo->prepare("UPDATE elections SET status='closed', results_published=1 WHERE election_id=? AND end_at<=NOW()");
        $s->execute([$id]);
        if($s->rowCount()===0){
            $s2=$pdo->prepare("UPDATE elections SET results_published=1, status=CASE WHEN status='closed' THEN 'closed' ELSE status END WHERE election_id=? AND end_at<=NOW()");
            $s2->execute([$id]);
        }
        // notify voters/candidates
        $titleRow=$pdo->prepare("SELECT title FROM elections WHERE election_id=?"); $titleRow->execute([$id]); $et=$titleRow->fetchColumn();
        $uids=$pdo->prepare("SELECT user_id FROM users WHERE role IN ('voter','candidate') AND status='active'");
        $uids->execute(); foreach($uids->fetchAll(PDO::FETCH_COLUMN) as $uid){ notify_user($pdo,(int)$uid,'Results published','Results for "'.($et?:'an election').'" have been published.'); }
        log_action($pdo,'results_published','Election #'.$id);
        flash('Results published (if end time has passed).');
    } elseif($action==='cancel' && $id){
        $pdo->prepare("UPDATE elections SET status='cancelled' WHERE election_id=? AND status IN ('draft','scheduled')")->execute([$id]);
        $t=$pdo->prepare("SELECT title FROM elections WHERE election_id=?"); $t->execute([$id]); $et=$t->fetchColumn()?:'Election';
        log_action($pdo,'election_cancelled','Election #'.$id);
        foreach($pdo->query("SELECT user_id FROM users WHERE role IN ('voter','candidate') AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $uid){ notify_user($pdo,(int)$uid,'Election cancelled','"'.$et.'" has been cancelled.'); }
        flash('Election cancelled (draft/scheduled only).');
    }
    redirect('/voting_system/admin/elections.php'.($id && $action==='update' ? '' : ''));
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
$eligVoters=[]; $currentElig=[];
if ($editId) {
    $s=$pdo->prepare("SELECT * FROM elections WHERE election_id=?"); $s->execute([$editId]); $edit=$s->fetch();
    $eligVoters=$pdo->query("SELECT v.voter_id,u.full_name,u.email,v.voter_reference FROM voters v JOIN users u ON u.user_id=v.user_id WHERE v.approval_status='approved' ORDER BY u.full_name")->fetchAll();
    $ce=$pdo->prepare("SELECT voter_id FROM election_eligibility WHERE election_id=?"); $ce->execute([$editId]); $currentElig=array_map('intval',$ce->fetchAll(PDO::FETCH_COLUMN));
}
$rows=$pdo->query("SELECT * FROM elections ORDER BY created_at DESC")->fetchAll();
page_header('Election Management', ['subtitle' => 'Create, edit, and manage election lifecycle']);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2><?= $edit ? 'Edit election' : 'Create election' ?></h2><p><?= $edit ? 'Update election details and schedule.' : 'Set up a new election. New elections start as drafts.' ?></p></div><?php if($edit): ?><a class="btn secondary small" href="elections.php">New election</a><?php endif; ?></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if($edit): ?><input type="hidden" name="election_id" value="<?=$edit['election_id']?>"><?php endif; ?>
      <div class="grid-2">
        <label>Election title<input name="title" required value="<?=e($edit['title'] ?? '')?>" placeholder="e.g. Student Council Election 2026"></label>
        <label>Category<input name="category" value="<?=e($edit['category'] ?? '')?>" placeholder="e.g. University, SACCO, Organization"></label>
      </div>
      <label>Description<textarea name="description" placeholder="Brief description of the election"><?=e($edit['description'] ?? '')?></textarea></label>
      <div class="grid-2">
        <label>Start date and time<input type="datetime-local" name="start_at" required value="<?=e($edit ? date('Y-m-d\TH:i', strtotime($edit['start_at'])) : '')?>"></label>
        <label>End date and time<input type="datetime-local" name="end_at" required value="<?=e($edit ? date('Y-m-d\TH:i', strtotime($edit['end_at'])) : '')?>"></label>
      </div>
      <label style="display:flex;gap:10px;align-items:center;margin:10px 0 18px">
        <input type="checkbox" name="allow_multi_position" value="1" <?= !empty($edit['allow_multi_position'])?'checked':'' ?> style="width:16px;height:16px">
        <span>Allow a candidate to contest multiple positions in this election (only when explicitly permitted)</span>
      </label>
      <div><button class="btn"><?= $edit ? 'Save changes' : 'Create draft election' ?></button></div>
    </form>
  </div>

  <?php if($edit): ?>
  <div class="panel">
    <div class="panel-header"><div><h2>Voter eligibility rules</h2><p>By default, all approved voters are eligible. If you select specific voters, only they can vote in this election.</p></div>
      <form class="inline" method="post" onsubmit="return confirm('Clear custom eligibility rules? All approved voters will become eligible.')">
        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="eligibility_clear"><input type="hidden" name="election_id" value="<?=$edit['election_id']?>">
        <button class="btn secondary small" type="submit">Clear rules (all approved)</button>
      </form>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="eligibility_save"><input type="hidden" name="election_id" value="<?=$edit['election_id']?>">
      <div class="table-wrap" style="box-shadow:none;border:0;max-height:360px;overflow:auto"><table>
        <thead><tr><th style="width:60px">Eligible</th><th>Name</th><th>Email</th><th>Reference</th></tr></thead>
        <tbody>
        <?php foreach($eligVoters as $v): $checked=in_array((int)$v['voter_id'],$currentElig,true); ?>
          <tr>
            <td><input type="checkbox" name="voter_ids[]" value="<?=$v['voter_id']?>" <?= $checked?'checked':'' ?> style="width:16px;height:16px"></td>
            <td><?=e($v['full_name'])?></td><td><?=e($v['email'])?></td><td><?=e($v['voter_reference'])?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$eligVoters): ?><tr><td colspan="4" class="muted">No approved voters found.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <div class="actions" style="margin-top:14px"><button class="btn small">Save eligibility rules</button><?php if($currentElig): ?><span class="muted" style="font-size:13px"><?=count($currentElig)?> voter(s) selected.</span><?php else: ?><span class="muted" style="font-size:13px">No custom rule set (all approved voters eligible).</span><?php endif; ?></div>
    </form>
  </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-header"><div><h2>Existing elections</h2><p>Manage lifecycle: activate, close, publish, cancel.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table>
        <thead><tr><th>Title</th><th>Category</th><th>Schedule</th><th>Status</th><th>Results</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><strong><?=e($r['title'])?></strong><div class="muted" style="font-size:12px"><?=e($r['description'] ?? '')?></div></td>
            <td><?=e($r['category'] ?: '—')?></td>
            <td><small><?=e(date('M j, Y g:i A', strtotime($r['start_at'])))?><br><?=e(date('M j, Y g:i A', strtotime($r['end_at'])))?></small></td>
            <td><span class="badge <?= match($r['status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','draft'=>'gray','published'=>'blue','cancelled'=>'red',default=>'gray'} ?>"><?=e(ucfirst($r['status']))?></span></td>
            <td><span class="badge <?= $r['results_published']?'green':'orange' ?>"><?= $r['results_published']?'Published':'Hidden' ?></span></td>
            <td>
              <div class="table-actions">
                <a class="btn secondary small" href="?edit=<?=$r['election_id']?>">Edit</a>
                <a class="btn secondary small" href="positions.php?election_id=<?=$r['election_id']?>">Positions</a>
                <?php if($r['status']==='draft'): ?>
                  <form class="inline" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="election_id" value="<?=$r['election_id']?>"><button class="btn small">Activate</button></form>
                <?php endif; ?>
                <?php if(in_array($r['status'],['open','scheduled'],true)): ?>
                  <form class="inline" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="close"><input type="hidden" name="election_id" value="<?=$r['election_id']?>"><button class="btn secondary small">Close</button></form>
                <?php endif; ?>
                <?php if(!$r['results_published']): ?>
                  <form class="inline" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="election_id" value="<?=$r['election_id']?>"><button class="btn small">Publish</button></form>
                <?php endif; ?>
                <?php if(in_array($r['status'],['draft','scheduled'],true)): ?>
                  <form class="inline" method="post" onsubmit="return confirm('Cancel this election?')"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="election_id" value="<?=$r['election_id']?>"><button class="btn danger small">Cancel</button></form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="6" class="muted">No elections created yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_footer(); ?>