<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
page_header('Voter Dashboard', ['subtitle' => 'Welcome back, ' . ($_SESSION['full_name'] ?? 'Voter')]);

$uid = current_user_id();
$vs = $pdo->prepare("SELECT voter_id, approval_status, voter_reference FROM voters WHERE user_id=? LIMIT 1");
$vs->execute([$uid]);
$voter = $vs->fetch();
$voterId = (int)($voter['voter_id'] ?? 0);

$available = (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='open'")->fetchColumn();
$upcoming = (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='scheduled'")->fetchColumn();
$published = (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='closed' AND results_published=1")->fetchColumn();
$participated = 0;
if ($voterId) {
    $ps = $pdo->prepare("SELECT COUNT(*) FROM voter_participation WHERE voter_id=?");
    $ps->execute([$voterId]);
    $participated = (int)$ps->fetchColumn();
}

$es = $pdo->prepare("SELECT e.*, vp.participation_id, vp.submitted_at
    FROM elections e
    LEFT JOIN voter_participation vp ON vp.election_id=e.election_id AND vp.voter_id=?
    WHERE e.status IN ('scheduled','open','closed')
    ORDER BY CASE e.status WHEN 'open' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END, e.start_at DESC");
$es->execute([$voterId]);
$elections = $es->fetchAll();
$posByElection = [];
if ($elections) {
    $ids = array_map(fn($r)=>(int)$r['election_id'], $elections);
    $ph = implode(',', array_fill(0,count($ids),'?'));
    $pqs = $pdo->prepare("SELECT p.election_id,p.position_id,p.name,p.description,
        (SELECT COUNT(*) FROM election_candidates ec JOIN candidates c ON c.candidate_id=ec.candidate_id JOIN users u ON u.user_id=c.user_id WHERE ec.position_id=p.position_id AND u.status='active') AS candidate_count
        FROM positions p WHERE p.election_id IN ($ph) AND p.status='active' ORDER BY p.created_at,p.position_id");
    $pqs->execute($ids);
    foreach ($pqs->fetchAll() as $p) { $posByElection[(int)$p['election_id']][] = $p; }
}
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2 style="margin-bottom:4px">Welcome back, <?=e($_SESSION['full_name'] ?? 'Voter')?>!</h2>
        <p>Stay informed, participate in elections, and make your voice count.</p>
      </div>
      <span class="badge <?= ($voter['approval_status'] ?? '') === 'approved' ? 'green' : 'orange' ?>">
        <?=e(ucfirst($voter['approval_status'] ?? 'unknown'))?> account
      </span>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><div><div class="stat-label">Available Elections</div><div class="stat-value"><?=$available?></div><div class="stat-meta">Open for voting</div></div><div class="stat-icon blue">◷</div></div>
    <div class="stat-card"><div><div class="stat-label">Participated</div><div class="stat-value"><?=$participated?></div><div class="stat-meta">Elections voted in</div></div><div class="stat-icon green">✓</div></div>
    <div class="stat-card"><div><div class="stat-label">Upcoming</div><div class="stat-value"><?=$upcoming?></div><div class="stat-meta">Scheduled elections</div></div><div class="stat-icon orange">◔</div></div>
    <div class="stat-card"><div><div class="stat-label">Published Results</div><div class="stat-value"><?=$published?></div><div class="stat-meta">Results available</div></div><div class="stat-icon navy">▣</div></div>
  </div>

  <div class="section-title">
    <div><h2>Available Elections</h2><p class="muted" style="margin:0">View elections, eligibility, positions, and voting actions.</p></div>
  </div>
  <div class="cards">
    <?php foreach ($elections as $r): 
      $status = $r['status'];
      $voted = !empty($r['participation_id']);
      $badge = match($status){'open'=>'green','scheduled'=>'orange','closed'=>'red',default=>'gray'};
      $eligible = is_voter_eligible_for_election($pdo,(int)$r['election_id'],$voterId);
      $epos = $posByElection[(int)$r['election_id']] ?? [];
    ?>
      <article class="card">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
          <span class="badge <?=$badge?>"><?=e(ucfirst($status))?></span>
          <?php if ($voted): ?><span class="badge green">Voted</span><?php endif; ?>
        </div>
        <h3 style="margin-top:14px"><?=e($r['title'])?></h3>
        <p class="muted"><?=e($r['description'] ?: 'No description provided.')?></p>
        <p style="font-size:13px;color:#475569;margin-bottom:12px">
          <strong>Start:</strong> <?=e(date('M j, Y g:i A', strtotime($r['start_at'])))?><br>
          <strong>End:</strong> <?=e(date('M j, Y g:i A', strtotime($r['end_at'])))?>
        </p>
        <div style="margin-bottom:14px">
          <strong style="font-size:13px;display:block;margin-bottom:8px">Available Positions</strong>
          <?php if($epos): ?>
            <div style="display:grid;gap:8px">
              <?php foreach($epos as $p): ?>
                <div style="border:1px solid var(--border);border-radius:10px;padding:10px;background:#f8fafc">
                  <div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><strong style="font-size:13px"><?=e($p['name'])?></strong><span class="badge gray" style="font-size:11px"><?= (int)$p['candidate_count'] ?> candidates</span></div>
                  <div class="muted" style="font-size:12px;margin:4px 0 8px"><?=e(mb_strimwidth((string)($p['description']?:'No description'),0,70,'…'))?></div>
                  <a class="btn secondary small" href="position.php?position_id=<?=$p['position_id']?>&election_id=<?=$r['election_id']?>" style="padding:5px 10px;font-size:12px">View Candidates</a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?><div class="muted" style="font-size:13px">No active positions yet.</div><?php endif; ?>
        </div>
        <div class="actions">
          <a class="btn secondary small" href="election.php?election_id=<?=$r['election_id']?>">View Election</a>
          <?php if ($status === 'open' && !$voted && $eligible): ?>
            <a class="btn small" href="ballot.php?election_id=<?=$r['election_id']?>">Open Ballot</a>
          <?php elseif ($status === 'open' && !$voted && !$eligible): ?>
            <span class="muted" style="font-size:14px">Not eligible.</span>
          <?php elseif ($voted): ?>
            <a class="btn secondary small" href="confirmation.php?election_id=<?=$r['election_id']?>">Confirmation</a>
            <?php if ($status === 'closed' && (int)$r['results_published'] === 1): ?>
              <a class="btn secondary small" href="results.php?election_id=<?=$r['election_id']?>">Results</a>
            <?php endif; ?>
          <?php elseif ($status === 'closed' && (int)$r['results_published'] === 1): ?>
            <a class="btn secondary small" href="results.php?election_id=<?=$r['election_id']?>">Published Results</a>
          <?php elseif ($status === 'scheduled'): ?>
            <span class="muted" style="font-size:14px">Not open yet.</span>
          <?php else: ?>
            <span class="muted" style="font-size:14px">Voting closed.</span>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$elections): ?>
      <div class="card empty-state"><div class="empty-icon">▣</div><p>No elections available at this time.</p></div>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
