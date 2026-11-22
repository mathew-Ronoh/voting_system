<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
page_header('Candidate Dashboard', ['subtitle' => 'Welcome to your candidate dashboard']);

$uid = current_user_id();
$s = $pdo->prepare("SELECT c.candidate_id,c.manifesto,c.profile_image,p.name AS position_name,e.election_id,e.title,e.status,e.start_at,e.end_at,e.results_published,u.full_name,u.email
    FROM candidates c
    JOIN users u ON u.user_id=c.user_id
    LEFT JOIN election_candidates ec ON ec.candidate_id=c.candidate_id
    LEFT JOIN positions p ON p.position_id=ec.position_id
    LEFT JOIN elections e ON e.election_id=ec.election_id
    WHERE c.user_id=?");
$s->execute([$uid]);
$rows = $s->fetchAll();
$profile = $rows[0] ?? null;
$assigned = count(array_filter($rows, fn($r) => !empty($r['election_id'])));
$manifestoStatus = (!empty($profile['manifesto'])) ? 'Submitted' : 'Pending';
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header">
      <div style="display:flex;gap:16px;align-items:center">
        <div class="avatar" style="width:64px;height:64px;font-size:20px;border-radius:18px"><?=e(initials($profile['full_name'] ?? ($_SESSION['full_name'] ?? 'C')))?></div>
        <div>
          <h2 style="margin-bottom:2px"><?=e($profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Candidate'))?></h2>
          <p><?=e($profile['email'] ?? '')?> · Candidate account</p>
        </div>
      </div>
      <span class="badge green">Active</span>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><div><div class="stat-label">Assigned Elections</div><div class="stat-value"><?=$assigned?></div><div class="stat-meta">Election assignments</div></div><div class="stat-icon blue">◷</div></div>
    <div class="stat-card"><div><div class="stat-label">Assigned Positions</div><div class="stat-value"><?=$assigned?></div><div class="stat-meta">Position assignments</div></div><div class="stat-icon navy">◉</div></div>
    <div class="stat-card"><div><div class="stat-label">Manifesto</div><div class="stat-value" style="font-size:22px"><?=e($manifestoStatus)?></div><div class="stat-meta">Profile manifesto status</div></div><div class="stat-icon <?= $manifestoStatus==='Submitted'?'green':'orange' ?>">✎</div></div>
    <div class="stat-card"><div><div class="stat-label">Account</div><div class="stat-value" style="font-size:22px">Active</div><div class="stat-meta">Candidate access</div></div><div class="stat-icon green">✓</div></div>
  </div>

  <div class="section-title"><div><h2>My Elections & Positions</h2><p class="muted" style="margin:0">Assignments and election information.</p></div></div>
  <div class="cards">
    <?php foreach ($rows as $r): if (empty($r['election_id'])) continue; ?>
      <article class="card">
        <span class="badge <?= match($r['status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','draft'=>'gray',default=>'gray'} ?>"><?=e(ucfirst($r['status'] ?? 'Unknown'))?></span>
        <h3 style="margin-top:14px"><?=e($r['title'] ?? 'Election')?></h3>
        <p><strong>Position:</strong> <?=e($r['position_name'] ?? 'Not assigned')?></p>
        <p class="muted"><strong>Manifesto:</strong> <?=e($r['manifesto'] ?: 'No manifesto submitted yet.')?></p>
        <?php if (!empty($r['start_at'])): ?><small><?=e(date('M j, Y g:i A', strtotime($r['start_at'])))?> – <?=e(date('M j, Y g:i A', strtotime($r['end_at'])))?></small><?php endif; ?>
        <div class="actions" style="margin-top:14px">
          <?php if (!empty($r['results_published'])): ?>
            <a class="btn secondary small" href="/voting_system/voter/results.php?election_id=<?=$r['election_id']?>">View Published Results</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$assigned): ?>
      <div class="card empty-state"><div class="empty-icon">◉</div><p>No election assignment yet. Contact the administrator.</p></div>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>