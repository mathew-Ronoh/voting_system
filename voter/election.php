<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
$eid = (int)($_GET['election_id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM elections WHERE election_id=? AND status IN ('scheduled','open','closed','published')");
$s->execute([$eid]); $el = $s->fetch();
if (!$el) { http_response_code(404); exit('Election not found.'); }
$voter = get_voter($pdo, current_user_id());
$voterId = (int)($voter['voter_id'] ?? 0);
$ps = $pdo->prepare("SELECT participation_id, confirmation_reference, submitted_at FROM voter_participation WHERE election_id=? AND voter_id=?");
$ps->execute([$eid, $voterId]); $part = $ps->fetch();
$eligible = $voterId ? is_voter_eligible_for_election($pdo,$eid,$voterId) : false;
$voted = !empty($part);
$status = $el['status'];

$posSt = $pdo->prepare("SELECT p.position_id, p.name, p.description, p.max_winners, p.status,
    (SELECT COUNT(*) FROM election_candidates ec JOIN candidates c ON c.candidate_id=ec.candidate_id JOIN users u ON u.user_id=c.user_id WHERE ec.position_id=p.position_id AND u.status='active') AS candidate_count
    FROM positions p WHERE p.election_id=? AND p.status='active' ORDER BY p.created_at, p.position_id");
$posSt->execute([$eid]); $positions = $posSt->fetchAll();

page_header('Election Details', ['subtitle' => $el['title']]);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2><?=e($el['title'])?></h2>
        <p><?=e($el['description'] ?: 'No description provided.')?></p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <span class="badge <?= match($status){'open'=>'green','scheduled'=>'orange','closed'=>'red','published'=>'blue',default=>'gray'} ?>"><?=e(ucfirst($status))?></span>
        <?php if($voted): ?><span class="badge green">Voted</span><?php endif; ?>
      </div>
    </div>
    <div class="grid-2">
      <div><strong>Category:</strong> <span class="muted"><?=e($el['category'] ?: 'General')?></span></div>
      <div><strong>Eligibility:</strong> <span class="muted"><?= $eligible ? 'Eligible to vote' : 'Not eligible for this election' ?></span></div>
      <div><strong>Start:</strong> <span class="muted"><?=e(date('M j, Y g:i A', strtotime($el['start_at'])))?></span></div>
      <div><strong>End:</strong> <span class="muted"><?=e(date('M j, Y g:i A', strtotime($el['end_at'])))?></span></div>
    </div>
    <div class="actions" style="margin-top:20px">
      <?php if($status==='open' && !$voted && $eligible): ?>
        <a class="btn" href="ballot.php?election_id=<?=$eid?>">Proceed to Vote</a>
      <?php elseif($voted): ?>
        <a class="btn secondary" href="confirmation.php?election_id=<?=$eid?>">View Confirmation</a>
      <?php endif; ?>
      <?php if(in_array($status,['closed','published'],true) && (int)$el['results_published']===1): ?>
        <a class="btn secondary" href="results.php?election_id=<?=$eid?>">View Published Results</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Available Positions</h2><p>Positions open in this election. View candidates and compare before voting.</p></div></div>
    <div class="cards">
      <?php foreach($positions as $pos): ?>
        <article class="card">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:10px">
            <div>
              <h3 style="margin:0 0 4px;font-size:18px"><?=e($pos['name'])?></h3>
              <div class="muted" style="font-size:13px"><?=e($el['title'])?></div>
            </div>
            <span class="badge green">Active</span>
          </div>
          <p class="muted" style="margin-bottom:14px"><?=e(mb_strimwidth((string)($pos['description'] ?: 'No description provided.'),0,120,'…'))?></p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
            <span class="badge gray"><?= (int)$pos['candidate_count'] ?> approved candidate<?= ((int)$pos['candidate_count']===1?'':'s') ?></span>
            <span class="badge gray">Max winners: <?= (int)($pos['max_winners'] ?: 1) ?></span>
          </div>
          <a class="btn secondary small" href="position.php?position_id=<?=$pos['position_id']?>&election_id=<?=$eid?>">View Candidates</a>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if(!$positions): ?><div class="empty-state" style="padding:20px 0"><div class="empty-icon">▣</div><p>No active positions configured for this election yet.</p></div><?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
