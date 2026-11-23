<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_role('admin');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf(); $action=$_POST['action'] ?? 'create';
    if($action==='create'){
        $name=trim($_POST['full_name']??''); $email=strtolower(trim($_POST['email']??'')); $password=$_POST['password']??''; $pid=(int)($_POST['position_id']??0); $manifesto=trim($_POST['manifesto']??'');
        if($name && filter_var($email,FILTER_VALIDATE_EMAIL) && strlen($password)>=10 && $pid){
            try{
                $pdo->beginTransaction();
                $s=$pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,status) VALUES(?,?,?,'candidate','active')");
                $s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                $uid=(int)$pdo->lastInsertId();
                $s=$pdo->prepare("INSERT INTO candidates(user_id,manifesto) VALUES(?,?)");
                $s->execute([$uid,$manifesto]); $cid=(int)$pdo->lastInsertId();
                $s=$pdo->prepare("INSERT INTO election_candidates(election_id,position_id,candidate_id) SELECT election_id,position_id,? FROM positions WHERE position_id=?");
                $s->execute([$cid,$pid]);
                $pdo->commit(); log_action($pdo,'candidate_registered',$name.' assigned to position #'.$pid); $newUid=(int)$pdo->query("SELECT user_id FROM candidates WHERE candidate_id=".$cid)->fetchColumn(); if($newUid) notify_user($pdo,$newUid,'Candidate account created','Your candidate account has been created and assigned.'); flash('Candidate registered and assigned.');
            } catch(Throwable $e){
                if($pdo->inTransaction())$pdo->rollBack();
                flash('Could not register candidate. Check that email is unique and position exists.','error');
            }
        } else flash('Complete all fields. Password must be at least 10 characters.','error');
    } elseif($action==='suspend' || $action==='activate_account'){
        $cid=(int)($_POST['candidate_id']??0);
        $status = $action==='suspend' ? 'suspended' : 'active';
        $pdo->prepare("UPDATE users u JOIN candidates c ON c.user_id=u.user_id SET u.status=? WHERE c.candidate_id=?")->execute([$status,$cid]);
        log_action($pdo,'candidate_'.$status,'Candidate #'.$cid);
        flash('Candidate account '.$status.'.');
    } elseif($action==='assign_existing'){
        $candidateId=(int)($_POST['candidate_id']??0); $pid=(int)($_POST['position_id']??0);
        $c=$pdo->prepare("SELECT c.candidate_id,u.status,u.full_name,c.user_id FROM candidates c JOIN users u ON u.user_id=c.user_id WHERE c.candidate_id=?");
        $c->execute([$candidateId]); $cand=$c->fetch();
        $p=$pdo->prepare("SELECT p.position_id,p.election_id,p.name,e.title,e.allow_multi_position FROM positions p JOIN elections e ON e.election_id=p.election_id WHERE p.position_id=?");
        $p->execute([$pid]); $pos=$p->fetch();
        if(!$cand || !$pos){ flash('Candidate or position not found.','error'); }
        elseif($cand['status']!=='active'){ flash('Only approved/active candidates can be assigned.','error'); }
        else {
            $dup=$pdo->prepare("SELECT COUNT(*) FROM election_candidates WHERE election_id=? AND position_id=? AND candidate_id=?");
            $dup->execute([$pos['election_id'],$pid,$candidateId]);
            if((int)$dup->fetchColumn()>0){ flash('Candidate already assigned to this position.','error'); }
            else {
                if(!(int)$pos['allow_multi_position']){
                    $other=$pdo->prepare("SELECT p2.name FROM election_candidates ec JOIN positions p2 ON p2.position_id=ec.position_id WHERE ec.election_id=? AND ec.candidate_id=? LIMIT 1");
                    $other->execute([$pos['election_id'],$candidateId]); $otherPos=$other->fetchColumn();
                    if($otherPos){ flash('Candidate already assigned to "'.$otherPos.'" in this election. Multi-position not allowed.','error'); redirect('/voting_system/admin/candidates.php'); }
                }
                try {
                    $pdo->prepare("INSERT INTO election_candidates(election_id,position_id,candidate_id) VALUES(?,?,?)")->execute([$pos['election_id'],$pid,$candidateId]);
                    log_action($pdo,'candidate_assigned','Candidate #'.$candidateId.' assigned to position #'.$pid);
                    notify_user($pdo,(int)$cand['user_id'],'Position assignment','You have been assigned to "'.$pos['name'].'" in "'.$pos['title'].'".');
                    flash('Candidate assigned successfully.');
                } catch(Throwable $e){ flash('Could not assign candidate. Assignment may be invalid or duplicate.','error'); }
            }
        }
    } elseif($action==='app_approve' || $action==='app_reject'){
        $appId=(int)($_POST['application_id']??0);
        $app=$pdo->prepare("SELECT ca.*, u.user_id, u.full_name, p.name AS pos_name, e.title AS el_title, e.allow_multi_position FROM candidate_applications ca JOIN candidates c ON c.candidate_id=ca.candidate_id JOIN users u ON u.user_id=c.user_id JOIN positions p ON p.position_id=ca.position_id JOIN elections e ON e.election_id=ca.election_id WHERE ca.application_id=?");
        $app->execute([$appId]); $app=$app->fetch();
        if(!$app){ flash('Application not found.','error'); }
        elseif($action==='app_reject'){
            $pdo->prepare("UPDATE candidate_applications SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE application_id=?")->execute([current_user_id(),$appId]);
            log_action($pdo,'candidate_application_rejected','Application #'.$appId);
            notify_user($pdo,(int)$app['user_id'],'Application rejected','Your application for '.$app['pos_name'].' in '.$app['el_title'].' was rejected.');
            flash('Application rejected.');
        } else {
            $dup=$pdo->prepare("SELECT COUNT(*) FROM election_candidates WHERE election_id=? AND position_id=? AND candidate_id=?");
            $dup->execute([$app['election_id'],$app['position_id'],$app['candidate_id']]);
            if(!(int)$app['allow_multi_position']){
                $other=$pdo->prepare("SELECT p2.name FROM election_candidates ec JOIN positions p2 ON p2.position_id=ec.position_id WHERE ec.election_id=? AND ec.candidate_id=? AND ec.position_id<>? LIMIT 1");
                $other->execute([$app['election_id'],$app['candidate_id'],$app['position_id']]); $otherPos=$other->fetchColumn();
                if($otherPos){ flash('Cannot approve: candidate already assigned to "'.$otherPos.'" and multi-position is not allowed.','error'); redirect('/voting_system/admin/candidates.php'); }
            }
            try {
                $pdo->beginTransaction();
                if((int)$dup->fetchColumn()===0){
                    $pdo->prepare("INSERT INTO election_candidates(election_id,position_id,candidate_id) VALUES(?,?,?)")->execute([$app['election_id'],$app['position_id'],$app['candidate_id']]);
                }
                $pdo->prepare("UPDATE candidate_applications SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE application_id=?")->execute([current_user_id(),$appId]);
                $pdo->commit();
                log_action($pdo,'candidate_application_approved','Application #'.$appId);
                notify_user($pdo,(int)$app['user_id'],'Application approved','Your application for '.$app['pos_name'].' in '.$app['el_title'].' was approved.');
                flash('Application approved and candidate assigned.');
            } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); flash('Could not approve application.','error'); }
        }
    }
    redirect('/voting_system/admin/candidates.php');
}
page_header('Candidate Management', ['subtitle' => 'Register and assign candidates']);
$positions=$pdo->query("SELECT p.position_id,p.name,e.title,e.election_id FROM positions p JOIN elections e ON e.election_id=p.election_id ORDER BY e.created_at DESC,p.name")->fetchAll();
$allCandidates=$pdo->query("SELECT c.candidate_id,u.full_name,u.email FROM candidates c JOIN users u ON u.user_id=c.user_id WHERE u.status='active' ORDER BY u.full_name")->fetchAll();
$applications=$pdo->query("SELECT ca.application_id,ca.status,ca.created_at,u.full_name,u.email,p.name AS position_name,e.title AS election_title FROM candidate_applications ca JOIN candidates c ON c.candidate_id=ca.candidate_id JOIN users u ON u.user_id=c.user_id JOIN positions p ON p.position_id=ca.position_id JOIN elections e ON e.election_id=ca.election_id ORDER BY ca.status='pending' DESC, ca.created_at DESC LIMIT 100")->fetchAll();
$rows=$pdo->query("SELECT c.candidate_id,u.full_name,u.email,u.status AS account_status,p.name AS position_name,e.title FROM candidates c JOIN users u ON u.user_id=c.user_id LEFT JOIN election_candidates ec ON ec.candidate_id=c.candidate_id LEFT JOIN positions p ON p.position_id=ec.position_id LEFT JOIN elections e ON e.election_id=ec.election_id ORDER BY c.candidate_id DESC")->fetchAll();
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Register candidate</h2><p>Create a candidate account and assign to an election position.</p></div></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="create">
      <div class="grid-2">
        <label>Candidate full name<input name="full_name" required></label>
        <label>Email<input type="email" name="email" required></label>
      </div>
      <div class="grid-2">
        <label>Initial password<input type="password" name="password" minlength="10" required></label>
        <label>Election position
          <select name="position_id" required>
            <option value="">Choose position</option>
            <?php foreach($positions as $p): ?><option value="<?=$p['position_id']?>"><?=e($p['title'].' — '.$p['name'])?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>Manifesto<textarea name="manifesto" placeholder="Candidate manifesto or profile summary"></textarea></label>
      <div><button class="btn">Register candidate</button></div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Assign existing candidate</h2><p>Choose an approved candidate and assign them to an election position.</p></div></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="assign_existing">
      <div class="grid-2">
        <label>Candidate
          <select name="candidate_id" required>
            <option value="">Choose candidate</option>
            <?php foreach($allCandidates as $c): ?><option value="<?=$c['candidate_id']?>"><?=e($c['full_name'].' ('.$c['email'].')')?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Election position
          <select name="position_id" required>
            <option value="">Choose position</option>
            <?php foreach($positions as $p): ?><option value="<?=$p['position_id']?>"><?=e($p['title'].' — '.$p['name'])?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <div><button class="btn">Assign candidate</button></div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Candidate Applications</h2><p>Review and approve/reject position applications.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0"><table><thead><tr><th>Candidate</th><th>Election</th><th>Position</th><th>Status</th><th>Applied</th><th>Action</th></tr></thead><tbody>
    <?php foreach($applications as $a): ?>
      <tr>
        <td><strong><?=e($a['full_name'])?></strong><div class="muted" style="font-size:12px"><?=e($a['email'])?></div></td>
        <td><?=e($a['election_title'])?></td><td><?=e($a['position_name'])?></td>
        <td><span class="badge <?= $a['status']==='approved'?'green':($a['status']==='pending'?'orange':'red') ?>"><?=e(ucfirst($a['status']))?></span></td>
        <td><small><?=e(date('M j, Y', strtotime($a['created_at'])))?></small></td>
        <td><?php if($a['status']==='pending'): ?><form class="inline" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="application_id" value="<?=$a['application_id']?>"><button class="btn small" name="action" value="app_approve">Approve</button> <button class="btn danger small" name="action" value="app_reject">Reject</button></form><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$applications): ?><tr><td colspan="6" class="muted">No applications yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Registered candidates</h2><p>All candidate accounts and assignments.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table><thead><tr><th>Candidate</th><th>Email</th><th>Election</th><th>Position</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><strong><?=e($r['full_name'])?></strong></td><td><?=e($r['email'])?></td><td><?=e($r['title'] ?? '—')?></td><td><?=e($r['position_name'] ?? '—')?></td>
            <td><span class="badge <?= match($r['account_status']){'active'=>'green','suspended'=>'red','pending'=>'orange',default=>'gray'} ?>"><?=e(ucfirst($r['account_status']))?></span></td>
            <td><form class="inline" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="candidate_id" value="<?=$r['candidate_id']?>"><?php if($r['account_status']==='active'): ?><button class="btn danger small" name="action" value="suspend">Suspend</button><?php elseif($r['account_status']==='suspended'): ?><button class="btn small" name="action" value="activate_account">Activate</button><?php else: ?><span class="muted">—</span><?php endif; ?></form></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="6" class="muted">No candidates registered yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<?php page_footer(); ?>