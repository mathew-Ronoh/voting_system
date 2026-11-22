<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
page_header('Administrator Dashboard', ['subtitle' => 'Welcome back, Administrator']);

$counts = [
    'total_voters' => (int)$pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn(),
    'approved_voters' => (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE approval_status='approved'")->fetchColumn(),
    'pending_approvals' => (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE approval_status='pending'")->fetchColumn(),
    'total_candidates' => (int)$pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn(),
    'active_elections' => (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='open'")->fetchColumn(),
    'completed_elections' => (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status IN ('closed','published') OR (status='closed')")->fetchColumn(),
];
$upcoming = $pdo->query("SELECT election_id,title,start_at,end_at,status FROM elections WHERE status IN ('draft','scheduled','open') ORDER BY start_at ASC LIMIT 5")->fetchAll();
$recent = $pdo->query("SELECT action,details,created_at FROM audit_logs ORDER BY created_at DESC LIMIT 6")->fetchAll();
?>
<div class="page-stack">
  <div class="stats-grid cols-6">
    <div class="stat-card"><div><div class="stat-label">Total Voters</div><div class="stat-value"><?= $counts['total_voters'] ?></div><div class="stat-meta">Registered voter accounts</div></div><div class="stat-icon blue">👥</div></div>
    <div class="stat-card"><div><div class="stat-label">Approved Voters</div><div class="stat-value"><?= $counts['approved_voters'] ?></div><div class="stat-meta">Eligible to vote</div></div><div class="stat-icon green">✓</div></div>
    <div class="stat-card"><div><div class="stat-label">Pending Approvals</div><div class="stat-value"><?= $counts['pending_approvals'] ?></div><div class="stat-meta">Awaiting review</div></div><div class="stat-icon orange">!</div></div>
    <div class="stat-card"><div><div class="stat-label">Candidates</div><div class="stat-value"><?= $counts['total_candidates'] ?></div><div class="stat-meta">Registered candidates</div></div><div class="stat-icon navy">◉</div></div>
    <div class="stat-card"><div><div class="stat-label">Active Elections</div><div class="stat-value"><?= $counts['active_elections'] ?></div><div class="stat-meta">Currently open</div></div><div class="stat-icon green">◷</div></div>
    <div class="stat-card"><div><div class="stat-label">Completed</div><div class="stat-value"><?= $counts['completed_elections'] ?></div><div class="stat-meta">Closed elections</div></div><div class="stat-icon blue">▣</div></div>
  </div>

  <div class="panel">
    <div class="panel-header">
      <div><h2>Quick actions</h2><p>Access common management tasks.</p></div>
    </div>
    <div class="actions">
      <a class="btn" href="elections.php">Create Election</a>
      <a class="btn secondary" href="candidates.php">Add Candidate</a>
      <a class="btn secondary" href="voters.php">Manage Voters</a>
      <a class="btn secondary" href="voters.php">Review Approvals</a>
      <a class="btn secondary" href="results.php">View Results</a>
    </div>
  </div>

  <div class="grid-2">
    <div class="panel">
      <div class="panel-header"><div><h2>Upcoming / Active Elections</h2><p>Monitor election schedules.</p></div><a class="btn secondary small" href="elections.php">Manage</a></div>
      <div class="table-wrap" style="box-shadow:none;border:0"><table>
        <thead><tr><th>Election</th><th>Start</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($upcoming as $e): ?>
          <tr>
            <td><strong><?=e($e['title'])?></strong></td>
            <td><?=e(date('M j, Y g:i A', strtotime($e['start_at'])))?></td>
            <td><span class="badge <?= match($e['status']){'open'=>'green','scheduled'=>'orange','draft'=>'gray','closed'=>'red',default=>'gray'} ?>"><?=e(ucfirst($e['status']))?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$upcoming): ?><tr><td colspan="3" class="muted">No upcoming elections.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

    <div class="panel">
      <div class="panel-header"><div><h2>Recent Activity</h2><p>Latest administrative and system actions.</p></div></div>
      <div class="table-wrap" style="box-shadow:none;border:0"><table>
        <thead><tr><th>Action</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><strong><?=e($r['action'])?></strong><div class="muted" style="font-size:12px"><?=e($r['details'] ?? '')?></div></td>
            <td><?=e(date('M j, Y g:i A', strtotime($r['created_at'])))?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="2" class="muted">No audit activity recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Management areas</h2><p>Core election administration modules.</p></div></div>
    <div class="cards">
      <a class="card link-card" href="elections.php"><h3>Election Management</h3><p>Create elections, schedules, and lifecycle status.</p></a>
      <a class="card link-card" href="positions.php"><h3>Position Management</h3><p>Define offices and positions for each election.</p></a>
      <a class="card link-card" href="candidates.php"><h3>Candidate Management</h3><p>Register candidates and assign them to positions.</p></a>
      <a class="card link-card" href="voters.php"><h3>Voter Approvals</h3><p>Review registrations and approve eligible voters.</p></a>
      <a class="card link-card" href="results.php"><h3>Results & Publishing</h3><p>Close elections and publish verified results.</p></a>
    </div>
  </div>
</div>
<?php page_footer(); ?>