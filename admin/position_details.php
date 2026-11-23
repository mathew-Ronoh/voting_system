<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$pid = (int)($_GET['position_id'] ?? $_POST['position_id'] ?? 0);
$s = $pdo->prepare("SELECT p.*, e.title AS election_title, e.status AS election_status, e.start_at, e.end_at, e.category, e.allow_multi_position
    FROM positions p JOIN elections e ON e.election_id=p.election_id WHERE p.position_id=?");
$s->execute([$pid]); $pos = $s->fetch();
if (!$pos) { http_response_code(404); exit('Position not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'assign') {
        $candidateId = (int)($_POST['candidate_id'] ?? 0);
        // validate candidate is approved/active
        $c = $pdo->prepare("SELECT c.candidate_id, u.status, u.full_name, c.user_id FROM candidates c JOIN users u ON u.user_id=c.user_id WHERE c.candidate_id=?");
        $c->execute([$candidateId]); $cand = $c->fetch();
        if (!$cand) { flash('Candidate not found.', 'error'); redirect('/voting_system/admin/position_details.php?position_id='.$pid); }
        if ($cand['status'] !== 'active') { flash('Only approved/active candidates can be assigned.', 'error'); redirect('/voting_system/admin/position_details.php?position_id='.$pid); }

        // prevent duplicate in same election/position
        $dup = $pdo->prepare("SELECT COUNT(*) FROM election_candidates WHERE election_id=? AND position_id=? AND candidate_id=?");
        $dup->execute([$pos['election_id'],$pid,$candidateId]);
        if ((int)$dup->fetchColumn() > 0) { flash('Candidate is already assigned to this position.', 'error'); redirect('/voting_system/admin/position_details.php?position_id='.$pid); }

        // multi-position rule
        if (!(int)$pos['allow_multi_position']) {
            $other = $pdo->prepare("SELECT p.name FROM election_candidates ec JOIN positions p ON p.position_id=ec.position_id WHERE ec.election_id=? AND ec.candidate_id=? LIMIT 1");
            $other->execute([$pos['election_id'],$candidateId]); $otherPos = $other->fetchColumn();
            if ($otherPos) { flash('This candidate is already assigned to "'.$otherPos.'" in this election. Multi-position contesting is not allowed.', 'error'); redirect('/voting_system/admin/position_details.php?position_id='.$pid); }
        }

        try {
            $ins = $pdo->prepare("INSERT INTO election_candidates(election_id,position_id,candidate_id) VALUES(?,?,?)");
            $ins->execute([$pos['election_id'],$pid,$candidateId]);
            log_action($pdo,'candidate_assigned','Candidate #'.$candidateId.' assigned to position #'.$pid);
            notify_user($pdo,(int)$cand['user_id'],'Position assignment','You have been assigned to position "'.$pos['name'].'" in "'.$pos['election_title'].'".');
            flash('Candidate assigned successfully.');
        } catch (Throwable $e) {
            flash('Could not assign candidate. The assignment may already exist or be invalid.', 'error');
        }
        redirect('/voting_system/admin/position_details.php?position_id='.$pid);
    }

    if ($action === 'remove') {
        $ecId = (int)($_POST['election_candidate_id'] ?? 0);
        $row = $pdo->prepare("SELECT ec.*, c.user_id, u.full_name FROM election_candidates ec JOIN candidates c ON c.candidate_id=ec.candidate_id JOIN users u ON u.user_id=c.user_id WHERE ec.election_candidate_id=? AND ec.position_id=?");
        $row->execute([$ecId,$pid]); $as = $row->fetch();
        if (!$as) { flash('Assignment not found.', 'error'); redirect('/voting_system/admin/position_details.php?position_id='.$pid); }
        $vc = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id=? AND position_id=? AND candidate_id=?");
        $vc->execute([$as['election_id'],$as['position_id'],$as['candidate_id']]);
        if ((int)$vc->fetchColumn() > 0) {
            flash('Cannot remove assignment because votes have already been recorded for this candidate in this position.', 'error');
            redirect('/voting_system/admin/position_details.php?position_id='.$pid);
        }
        $pdo->prepare("DELETE FROM election_candidates WHERE election_candidate_id=?")->execute([$ecId]);
        log_action($pdo,'candidate_unassigned','Candidate #'.$as['candidate_id'].' removed from position #'.$pid);
        notify_user($pdo,(int)$as['user_id'],'Position assignment removed','Your assignment to position "'.$pos['name'].'" in "'.$pos['election_title'].'" was removed.');
        flash('Candidate assignment removed.');
        redirect('/voting_system/admin/position_details.php?position_id='.$pid);
    }
}

$qCand = trim($_GET['q'] ?? '');
$assigned = $pdo->prepare("SELECT ec.election_candidate_id, c.candidate_id, c.candidate_number, c.profile_image, c.manifesto, u.full_name, u.email, u.status AS account_status
    FROM election_candidates ec
    JOIN candidates c ON c.candidate_id=ec.candidate_id
    JOIN users u ON u.user_id=c.user_id
    WHERE ec.position_id=? ORDER BY u.full_name");
$assigned->execute([$pid]); $assignedRows = $assigned->fetchAll();

$candSql = "SELECT c.candidate_id, c.candidate_number, u.full_name, u.email, u.status
    FROM candidates c JOIN users u ON u.user_id=c.user_id
    WHERE u.status='active' AND c.candidate_id NOT IN (SELECT candidate_id FROM election_candidates WHERE position_id=?)";
$candParams = [$pid];
if ($qCand !== '') { $candSql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR c.candidate_number LIKE ?)"; $like='%'.$qCand.'%'; array_push($candParams,$like,$like,$like); }
$candSql .= " ORDER BY u.full_name LIMIT 50";
$candSt = $pdo->prepare($candSql); $candSt->execute($candParams); $available = $candSt->fetchAll();

page_header('Position Details', ['subtitle' => $pos['name']]);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2><?=e($pos['name'])?></h2>
        <p><?=e($pos['description'] ?: 'No description provided.')?></p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <span class="badge <?= $pos['status']==='active'?'green':'red' ?>"><?=e(ucfirst($pos['status']))?></span>
        <span class="badge <?= match($pos['election_status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','draft'=>'gray','published'=>'blue',default=>'gray'} ?>"><?=e(ucfirst($pos['election_status']))?></span>
      </div>
    </div>
    <div class="grid-2">
      <div><strong>Associated election:</strong> <div class="muted"><?=e($pos['election_title'])?></div></div>
      <div><strong>Category:</strong> <div class="muted"><?=e($pos['category'] ?: 'General')?></div></div>
      <div><strong>Election start:</strong> <div class="muted"><?=e(date('M j, Y g:i A', strtotime($pos['start_at'])))?></div></div>
      <div><strong>Election closing:</strong> <div class="muted"><?=e(date('M j, Y g:i A', strtotime($pos['end_at'])))?></div></div>
      <div><strong>Number of seats:</strong> <div class="muted"><?= (int)($pos['number_of_seats'] ?? $pos['max_winners'] ?? 1) ?></div></div>
      <div><strong>Candidates assigned:</strong> <div class="muted"><?= count($assignedRows) ?></div></div>
    </div>
    <?php if(!empty($pos['eligibility_requirements'])): ?><div style="margin-top:16px"><strong>Eligibility requirements:</strong><div class="muted" style="white-space:pre-line;margin-top:6px"><?=e($pos['eligibility_requirements'])?></div></div><?php endif; ?>
    <?php if(!empty($pos['responsibilities'])): ?><div style="margin-top:16px"><strong>Responsibilities:</strong><div class="muted" style="white-space:pre-line;margin-top:6px"><?=e($pos['responsibilities'])?></div></div><?php endif; ?>
    <div class="actions" style="margin-top:18px">
      <a class="btn secondary small" href="positions.php?edit=<?=$pid?>">Edit position</a>
      <a class="btn secondary small" href="positions.php">Back to positions</a>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Assigned candidates</h2><p>Candidates contesting for this position.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table>
        <thead><tr><th>Candidate</th><th>Candidate No.</th><th>Status</th><th>Manifesto</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($assignedRows as $c): ?>
          <tr>
            <td>
              <div style="display:flex;gap:10px;align-items:center">
                <?php if(!empty($c['profile_image'])): ?><img src="<?=e($c['profile_image'])?>" alt="" style="width:38px;height:38px;border-radius:10px;object-fit:cover"><?php else: ?><div class="avatar" style="width:38px;height:38px;border-radius:10px;font-size:12px"><?=e(initials($c['full_name']))?></div><?php endif; ?>
                <div><strong><?=e($c['full_name'])?></strong><div class="muted" style="font-size:12px"><?=e($c['email'])?></div></div>
              </div>
            </td>
            <td><?=e($c['candidate_number'] ?: 'CND-'.str_pad((string)$c['candidate_id'],4,'0',STR_PAD_LEFT))?></td>
            <td><span class="badge <?= $c['account_status']==='active'?'green':($c['account_status']==='pending'?'orange':'red') ?>"><?=e(ucfirst($c['account_status']))?></span></td>
            <td style="max-width:260px;white-space:normal;font-size:13px;color:#475569"><?=e(mb_strimwidth((string)($c['manifesto']??''),0,80,'…')) ?: '—'?></td>
            <td>
              <div class="table-actions">
                <a class="btn secondary small" href="/voting_system/voter/candidate.php?candidate_id=<?=$c['candidate_id']?>&election_id=<?=$pos['election_id']?>" target="_blank">View profile</a>
                <form class="inline" method="post" onsubmit="return confirm('Remove this candidate assignment?')">
                  <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="position_id" value="<?=$pid?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="election_candidate_id" value="<?=$c['election_candidate_id']?>">
                  <button class="btn danger small" type="submit">Remove</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$assignedRows): ?><tr><td colspan="5" class="muted">No candidates assigned yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel" id="assign-candidate">
    <div class="panel-header"><div><h2>Assign candidate</h2><p>Assign an approved/active candidate to this position. Duplicate and invalid assignments are blocked.</p></div></div>
    <form method="get" style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap">
      <input type="hidden" name="position_id" value="<?=$pid?>">
      <input name="q" value="<?=e($qCand)?>" placeholder="Search candidate name, email, or candidate number" style="flex:1;min-width:240px">
      <button class="btn small" type="submit">Search</button>
    </form>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table>
        <thead><tr><th>Candidate</th><th>Number</th><th>Email</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($available as $c): ?>
          <tr>
            <td><strong><?=e($c['full_name'])?></strong></td>
            <td><?=e($c['candidate_number'] ?: 'CND-'.str_pad((string)$c['candidate_id'],4,'0',STR_PAD_LEFT))?></td>
            <td><?=e($c['email'])?></td>
            <td><span class="badge green"><?=e(ucfirst($c['status']))?></span></td>
            <td>
              <form class="inline" method="post">
                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                <input type="hidden" name="position_id" value="<?=$pid?>">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="candidate_id" value="<?=$c['candidate_id']?>">
                <button class="btn small" type="submit">Assign</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$available): ?><tr><td colspan="5" class="muted">No eligible candidates found. Candidates must be active and not already assigned to this position.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_footer(); ?>
