<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter','candidate','admin');
$eid = (int)($_GET['election_id'] ?? 0);
if ($eid <= 0) {
    page_header('Election Results', ['subtitle' => 'Published election results']);
    $rows = $pdo->query("SELECT election_id,title,category,end_at,status,results_published FROM elections WHERE status='closed' AND results_published=1 ORDER BY end_at DESC")->fetchAll();
    ?>
    <div class="page-stack">
      <div class="panel"><div class="panel-header"><div><h2>Published results</h2><p>Results are visible only after an election closes and is published by an administrator.</p></div></div></div>
      <div class="cards">
        <?php foreach($rows as $r): ?>
          <article class="card">
            <span class="badge green">Published</span>
            <h3 style="margin-top:14px"><?=e($r['title'])?></h3>
            <p class="muted"><?=e($r['category'] ?: 'General election')?> · Ended <?=e(date('M j, Y g:i A', strtotime($r['end_at'])))?></p>
            <a class="btn secondary small" href="results.php?election_id=<?=$r['election_id']?>">View Results</a>
          </article>
        <?php endforeach; ?>
        <?php if(!$rows): ?><div class="card empty-state"><div class="empty-icon">▣</div><p>No published results yet.</p></div><?php endif; ?>
      </div>
    </div>
    <?php page_footer(); exit;
}

$s = $pdo->prepare("SELECT election_id,title FROM elections WHERE election_id=? AND status='closed' AND results_published=1");
$s->execute([$eid]); $el = $s->fetch();
if (!$el) { http_response_code(403); exit('Results have not been published.'); }
page_header('Published Results', ['subtitle' => $el['title']]);
$s = $pdo->prepare("SELECT p.position_id,p.name AS position_name,u.full_name,COUNT(v.vote_id) AS total_votes
  FROM positions p
  JOIN election_candidates ec ON ec.position_id=p.position_id
  JOIN candidates c ON c.candidate_id=ec.candidate_id
  JOIN users u ON u.user_id=c.user_id
  LEFT JOIN votes v ON v.candidate_id=c.candidate_id AND v.position_id=p.position_id AND v.election_id=p.election_id
  WHERE p.election_id=?
  GROUP BY p.position_id,p.name,c.candidate_id,u.full_name
  ORDER BY p.position_id,total_votes DESC,u.full_name");
$s->execute([$eid]); $rows = $s->fetchAll();
$byPos = [];
foreach ($rows as $r) { $byPos[$r['position_id']]['name'] = $r['position_name']; $byPos[$r['position_id']]['rows'][] = $r; }
?>
<div class="page-stack">
  <div class="panel"><div class="panel-header"><div><h2><?=e($el['title'])?></h2><p>Official published election results.</p></div><span class="badge green">Published</span></div></div>
  <?php foreach ($byPos as $pos): $max = max(array_map(fn($r)=>(int)$r['total_votes'], $pos['rows'])) ?: 1; $totalPos = array_sum(array_map(fn($r)=>(int)$r['total_votes'], $pos['rows'])) ?: 1; ?>
    <div class="panel">
      <div class="panel-header"><div><h2><?=e($pos['name'])?></h2><p>Vote counts and distribution.</p></div></div>
      <div class="table-wrap" style="box-shadow:none;border:0"><table>
        <thead><tr><th>Candidate</th><th>Votes</th><th>Share</th><th style="width:35%">Distribution</th></tr></thead>
        <tbody>
        <?php foreach ($pos['rows'] as $i=>$r): $votes=(int)$r['total_votes']; $pct = ($votes/$totalPos)*100; ?>
          <tr>
            <td><strong><?=e($r['full_name'])?></strong> <?php if($i===0 && $votes>0): ?><span class="badge green">Leading</span><?php endif; ?></td>
            <td><?=$votes?></td>
            <td><?=number_format($pct,1)?>%</td>
            <td><div style="background:#e5eaf2;border-radius:999px;height:10px;overflow:hidden"><div style="width:<?=$pct?>%;height:100%;background:linear-gradient(90deg,#2563eb,#3b82f6)"></div></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; ?>
  <?php if(!$byPos): ?><div class="panel empty-state"><div class="empty-icon">▣</div><p>No result data available.</p></div><?php endif; ?>
</div>
<?php page_footer(); ?>