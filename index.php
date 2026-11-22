<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) { redirect(role_home(current_role())); }
page_header('Welcome', ['layout' => 'public']);
$stmt = $pdo->query("SELECT title, description, start_at, end_at, status FROM elections WHERE status IN ('scheduled','open','closed') ORDER BY start_at DESC LIMIT 6");
$elections = $stmt->fetchAll();
?>
<section class="hero">
  <div>
    <p class="eyebrow">General-Purpose Election Platform</p>
    <h1>Secure, organized, and transparent electronic voting.</h1>
    <p>Manage elections, register eligible voters, nominate candidates, and publish verified results through one professional web-based platform.</p>
    <div class="actions">
      <a class="btn" href="/voting_system/login.php">Sign in</a>
      <a class="btn secondary" href="/voting_system/register.php">Register as voter</a>
    </div>
  </div>
  <div class="hero-card">
    <div class="hero-icon">✓</div>
    <h3>One person, one ballot</h3>
    <p>Eligibility checks, participation controls, and database constraints help prevent repeat voting in the same election.</p>
    <h3>Private ballot records</h3>
    <p>Voter participation is stored separately from ballot selections to support ballot privacy.</p>
    <h3>Role-based access</h3>
    <p>Administrators, candidates, and voters each get dedicated workflows and permissions.</p>
  </div>
</section>

<div class="section-title" style="margin-top:28px">
  <div>
    <h2>Election information</h2>
    <p class="muted" style="margin:0">Recent and announced elections.</p>
  </div>
</div>
<div class="cards">
<?php foreach ($elections as $el): ?>
  <article class="card">
    <span class="badge <?php
      echo match($el['status']) { 'open' => 'green', 'scheduled' => 'orange', 'closed' => 'red', default => 'gray' };
    ?>"><?=e(ucfirst($el['status']))?></span>
    <h3 style="margin-top:14px"><?=e($el['title'])?></h3>
    <p class="muted"><?=e($el['description'] ?: 'No description provided.')?></p>
    <small><?=e(date('M j, Y g:i A', strtotime($el['start_at'])))?> – <?=e(date('M j, Y g:i A', strtotime($el['end_at'])))?></small>
  </article>
<?php endforeach; ?>
<?php if (!$elections): ?>
  <div class="card empty-state"><div class="empty-icon">▣</div><p>No elections have been announced yet.</p></div>
<?php endif; ?>
</div>
<?php page_footer(['layout' => 'public']); ?>