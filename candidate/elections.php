<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$uid=current_user_id();
$s=$pdo->prepare("SELECT e.*, p.name AS position_name, c.manifesto FROM candidates c
  LEFT JOIN election_candidates ec ON ec.candidate_id=c.candidate_id
  LEFT JOIN elections e ON e.election_id=ec.election_id
  LEFT JOIN positions p ON p.position_id=ec.position_id
  WHERE c.user_id=? ORDER BY e.start_at DESC");
$s->execute([$uid]); $rows=$s->fetchAll();
page_header('My Elections', ['subtitle'=>'Your assigned elections and positions']);
?>
<div class="page-stack"><div class="panel"><div class="panel-header"><div><h2>Assigned elections</h2><p>Elections and positions you are assigned to.</p></div></div></div>
<div class="cards">
<?php foreach($rows as $r): if(empty($r['election_id'])) continue; ?>
  <article class="card">
    <span class="badge <?= match($r['status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','draft'=>'gray',default=>'gray'} ?>"><?=e(ucfirst($r['status']))?></span>
    <h3 style="margin-top:14px"><?=e($r['title'])?></h3>
    <p><strong>Position:</strong> <?=e($r['position_name'] ?? 'Not assigned')?></p>
    <p class="muted"><?=e($r['description'] ?: '')?></p>
    <small><?=e(date('M j, Y g:i A', strtotime($r['start_at'])))?> – <?=e(date('M j, Y g:i A', strtotime($r['end_at'])))?></small>
  </article>
<?php endforeach; ?>
<?php if(!array_filter($rows, fn($r)=>!empty($r['election_id']))): ?><div class="card empty-state"><div class="empty-icon">▣</div><p>No election assignments yet.</p></div><?php endif; ?>
</div></div>
<?php page_footer(); ?>