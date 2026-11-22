<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_role('admin');
page_header('Reports & Analytics', ['subtitle'=>'Election and system summary reports']);
$totalVoters=(int)$pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$approved=(int)$pdo->query("SELECT COUNT(*) FROM voters WHERE approval_status='approved'")->fetchColumn();
$pending=(int)$pdo->query("SELECT COUNT(*) FROM voters WHERE approval_status='pending'")->fetchColumn();
$candidates=(int)$pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$elections=(int)$pdo->query("SELECT COUNT(*) FROM elections")->fetchColumn();
$open=(int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='open'")->fetchColumn();
$closed=(int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='closed'")->fetchColumn();
$published=(int)$pdo->query("SELECT COUNT(*) FROM elections WHERE results_published=1")->fetchColumn();
$ballots=(int)$pdo->query("SELECT COUNT(*) FROM ballots")->fetchColumn();
$byStatus=$pdo->query("SELECT status,COUNT(*) c FROM elections GROUP BY status")->fetchAll();
?>
<div class="page-stack">
  <div class="stats-grid cols-6">
    <div class="stat-card"><div><div class="stat-label">Voters</div><div class="stat-value"><?=$totalVoters?></div></div><div class="stat-icon blue">👥</div></div>
    <div class="stat-card"><div><div class="stat-label">Approved</div><div class="stat-value"><?=$approved?></div></div><div class="stat-icon green">✓</div></div>
    <div class="stat-card"><div><div class="stat-label">Pending</div><div class="stat-value"><?=$pending?></div></div><div class="stat-icon orange">!</div></div>
    <div class="stat-card"><div><div class="stat-label">Candidates</div><div class="stat-value"><?=$candidates?></div></div><div class="stat-icon navy">◉</div></div>
    <div class="stat-card"><div><div class="stat-label">Elections</div><div class="stat-value"><?=$elections?></div></div><div class="stat-icon blue">▣</div></div>
    <div class="stat-card"><div><div class="stat-label">Ballots Cast</div><div class="stat-value"><?=$ballots?></div></div><div class="stat-icon green">✉</div></div>
  </div>
  <div class="grid-2">
    <div class="panel"><div class="panel-header"><div><h2>Election status summary</h2><p>Distribution by status.</p></div></div>
      <div class="table-wrap" style="box-shadow:none;border:0"><table><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>
        <?php foreach($byStatus as $r): ?><tr><td><span class="badge <?= match($r['status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','draft'=>'gray','published'=>'blue',default=>'gray'} ?>"><?=e(ucfirst($r['status']))?></span></td><td><?=$r['c']?></td></tr><?php endforeach; ?>
        <?php if(!$byStatus): ?><tr><td colspan="2" class="muted">No data.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <div class="panel"><div class="panel-header"><div><h2>Results publishing</h2><p>Publication overview.</p></div></div>
      <div style="display:grid;gap:12px">
        <div class="card" style="padding:16px"><strong>Open elections:</strong> <?=$open?></div>
        <div class="card" style="padding:16px"><strong>Closed elections:</strong> <?=$closed?></div>
        <div class="card" style="padding:16px"><strong>Published results:</strong> <?=$published?></div>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>