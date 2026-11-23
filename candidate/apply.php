<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$uid = current_user_id();
$cand = $pdo->prepare("SELECT c.* FROM candidates c WHERE c.user_id=?"); $cand->execute([$uid]); $cand = $cand->fetch();
if (!$cand) { http_response_code(403); exit('Candidate profile not found.'); }
$candidateId = (int)$cand['candidate_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pid = (int)($_POST['position_id'] ?? 0);
    $pos = $pdo->prepare("SELECT p.*, e.title AS election_title, e.status AS election_status FROM positions p JOIN elections e ON e.election_id=p.election_id WHERE p.position_id=? AND p.status='active'");
    $pos->execute([$pid]); $pos = $pos->fetch();
    if (!$pos) { flash('Position not found or inactive.','error'); redirect('/voting_system/candidate/apply.php'); }
    if (in_array($pos['election_status'], ['closed','published','cancelled'], true)) { flash('This election is not accepting applications.','error'); redirect('/voting_system/candidate/apply.php'); }
    try {
        $s = $pdo->prepare("INSERT INTO candidate_applications(candidate_id,election_id,position_id,status) VALUES(?,?,?,'pending')");
        $s->execute([$candidateId, $pos['election_id'], $pid]);
        log_action($pdo,'candidate_applied','Candidate #'.$candidateId.' applied for position #'.$pid);
        foreach($pdo->query("SELECT user_id FROM users WHERE role='admin' AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $aid){ notify_user($pdo,(int)$aid,'Candidate application',($_SESSION['full_name']??'A candidate').' applied for '.$pos['name'].' in '.$pos['election_title'].'.'); }
        flash('Application submitted. Await administrator review.');
    } catch (Throwable $e) {
        flash('You have already applied for this position in this election, or the application could not be submitted.', 'error');
    }
    redirect('/voting_system/candidate/apply.php');
}

$elections = $pdo->query("SELECT election_id,title,status FROM elections WHERE status IN ('draft','scheduled','open') ORDER BY created_at DESC")->fetchAll();
$positions = $pdo->query("SELECT p.*, e.title AS election_title FROM positions p JOIN elections e ON e.election_id=p.election_id WHERE p.status='active' AND e.status IN ('draft','scheduled','open') ORDER BY e.created_at DESC, p.category, p.name")->fetchAll();
$apps = $pdo->prepare("SELECT ca.*, p.name AS position_name, e.title AS election_title FROM candidate_applications ca JOIN positions p ON p.position_id=ca.position_id JOIN elections e ON e.election_id=ca.election_id WHERE ca.candidate_id=? ORDER BY ca.created_at DESC");
$apps->execute([$candidateId]); $apps = $apps->fetchAll();
$appliedKeys = [];
foreach ($apps as $a) { $appliedKeys[$a['election_id'].'-'.$a['position_id']] = $a['status']; }

page_header('Apply for Position', ['subtitle' => 'Apply for active election positions']);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Available positions</h2><p>Select a position to contest. Review description and eligibility before applying.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table>
        <thead><tr><th>Election</th><th>Position</th><th>Category</th><th>Seats</th><th>Eligibility</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($positions as $p): $key=$p['election_id'].'-'.$p['position_id']; $applied=$appliedKeys[$key]??null; ?>
          <tr>
            <td><?=e($p['election_title'])?></td>
            <td><strong><?=e($p['name'])?></strong><div class="muted" style="font-size:12px"><?=e(mb_strimwidth((string)($p['description']??''),0,80,'…'))?></div></td>
            <td><?=e($p['category']?:'—')?></td>
            <td><?= (int)($p['number_of_seats']??1) ?></td>
            <td style="font-size:12px;color:#475569;max-width:260px;white-space:normal"><?=e(mb_strimwidth((string)($p['eligibility_requirements']??'See election rules'),0,90,'…'))?></td>
            <td>
              <?php if($applied): ?>
                <span class="badge <?= $applied==='approved'?'green':($applied==='pending'?'orange':'red') ?>"><?=e(ucfirst($applied))?></span>
              <?php else: ?>
                <form class="inline" method="post" onsubmit="return confirm('Apply for this position?')">
                  <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="position_id" value="<?=$p['position_id']?>">
                  <button class="btn small" type="submit">Apply</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$positions): ?><tr><td colspan="6" class="muted">No active positions available for application.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>My applications</h2><p>Track your position applications.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table><thead><tr><th>Election</th><th>Position</th><th>Status</th><th>Applied</th></tr></thead><tbody>
        <?php foreach($apps as $a): ?>
          <tr><td><?=e($a['election_title'])?></td><td><?=e($a['position_name'])?></td><td><span class="badge <?= $a['status']==='approved'?'green':($a['status']==='pending'?'orange':'red') ?>"><?=e(ucfirst($a['status']))?></span></td><td><small><?=e(date('M j, Y g:i A', strtotime($a['created_at'])))?></small></td></tr>
        <?php endforeach; ?>
        <?php if(!$apps): ?><tr><td colspan="4" class="muted">No applications yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<?php page_footer(); ?>
