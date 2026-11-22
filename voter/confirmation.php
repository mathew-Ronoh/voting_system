<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
$eid = (int)($_GET['election_id'] ?? 0);
$voter = get_voter($pdo, current_user_id());
$voterId = (int)($voter['voter_id'] ?? 0);
$s = $pdo->prepare("SELECT e.title, e.election_id, vp.submitted_at, vp.confirmation_reference
  FROM voter_participation vp JOIN elections e ON e.election_id=vp.election_id
  WHERE vp.election_id=? AND vp.voter_id=? LIMIT 1");
$s->execute([$eid, $voterId]);
$rec = $s->fetch();
if (!$rec) { flash('No voting confirmation found for this election.', 'error'); redirect('/voting_system/voter/dashboard.php'); }
page_header('Vote Confirmation', ['subtitle' => 'Your ballot was recorded']);
?>
<div class="page-stack">
  <div class="panel" style="max-width:760px">
    <div style="text-align:center;padding:16px 0 8px">
      <div style="width:72px;height:72px;border-radius:50%;background:var(--green-bg);color:var(--green);display:grid;place-items:center;font-size:34px;margin:0 auto 16px">✓</div>
      <h1 style="margin-bottom:8px">Your vote has been successfully recorded</h1>
      <p class="muted">Thank you for participating. Your ballot selections are private and are not displayed on this receipt.</p>
    </div>
    <div class="table-wrap" style="box-shadow:none;margin-top:24px"><table>
      <tbody>
        <tr><th style="width:220px">Election</th><td><?=e($rec['title'])?></td></tr>
        <tr><th>Submission date & time</th><td><?=e(date('M j, Y g:i A', strtotime($rec['submitted_at'])))?></td></tr>
        <tr><th>Confirmation reference</th><td><strong><?=e($rec['confirmation_reference'] ?: 'N/A')?></strong></td></tr>
        <tr><th>Status</th><td><span class="badge green">Recorded</span></td></tr>
      </tbody>
    </table></div>
    <div class="actions" style="margin-top:24px;justify-content:center">
      <a class="btn" href="/voting_system/voter/dashboard.php">Back to Dashboard</a>
      <a class="btn secondary" href="/voting_system/voter/history.php">View Voting History</a>
    </div>
  </div>
</div>
<?php page_footer(); ?>