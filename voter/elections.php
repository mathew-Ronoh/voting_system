<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
$voter = get_voter($pdo, current_user_id());
$voterId = (int)($voter['voter_id'] ?? 0);
$s = $pdo->prepare("SELECT e.*, vp.participation_id FROM elections e
  LEFT JOIN voter_participation vp ON vp.election_id=e.election_id AND vp.voter_id=?
  WHERE e.status IN ('scheduled','open','closed') ORDER BY CASE e.status WHEN 'open' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END, e.start_at DESC");
$s->execute([$voterId]); $rows = $s->fetchAll();
page_header('Available Elections', ['subtitle' => 'Browse elections and your eligibility']);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Elections</h2><p>Open, upcoming, and closed elections available to you.</p></div></div>
  </div>
  <div class="cards">
    <?php foreach ($rows as $r): $voted=!empty($r['participation_id']); $status=$r['status']; ?>
      <article class="card">
        <div style="display:flex;gap:8px;flex-wrap:wrap"><span class="badge <?= match($status){'open'=>'green','scheduled'=>'orange','closed'=>'red',default=>'gray'} ?>"><?=e(ucfirst($status))?></span><?php if($voted): ?><span class="badge green">Voted</span><?php endif; ?></div>
        <h3 style="margin-top:14px"><?=e($r['title'])?></h3>
        <p class="muted"><?=e($r['description'] ?: 'No description provided.')?></p>
        <p style="font-size:13px;color:#475569"><strong>Category:</strong> <?=e($r['category'] ?: 'General')?><br><strong>Start:</strong> <?=e(date('M j, Y g:i A', strtotime($r['start_at'])))?><br><strong>End:</strong> <?=e(date('M j, Y g:i A', strtotime($r['end_at'])))?></p>
        <?php $eligible = is_voter_eligible_for_election($pdo,(int)$r['election_id'],$voterId); ?>
        <div class="actions">
          <a class="btn secondary small" href="election.php?election_id=<?=$r['election_id']?>">View Election</a>
          <?php if($status==='open' && !$voted && $eligible): ?><a class="btn small" href="ballot.php?election_id=<?=$r['election_id']?>">Vote Now</a><?php endif; ?>
          <?php if($status==='open' && !$voted && !$eligible): ?><span class="muted" style="font-size:13px">Not eligible</span><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if(!$rows): ?><div class="card empty-state"><div class="empty-icon">▣</div><p>No elections available.</p></div><?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>