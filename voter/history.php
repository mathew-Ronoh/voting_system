<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
$voter = get_voter($pdo, current_user_id());
$voterId = (int)($voter['voter_id'] ?? 0);
$s = $pdo->prepare("SELECT e.title, e.status, e.results_published, vp.submitted_at, vp.confirmation_reference, vp.election_id
  FROM voter_participation vp JOIN elections e ON e.election_id=vp.election_id
  WHERE vp.voter_id=? ORDER BY vp.submitted_at DESC");
$s->execute([$voterId]);
$rows = $s->fetchAll();
page_header('My Voting History', ['subtitle' => 'Your election participation records']);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Voting history</h2><p>Your participation is recorded here. Ballot selections are never displayed.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0"><table>
      <thead><tr><th>Election</th><th>Participation date</th><th>Status</th><th>Confirmation reference</th><th>Results</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?=e($r['title'])?></strong></td>
          <td><?=e(date('M j, Y g:i A', strtotime($r['submitted_at'])))?></td>
          <td><span class="badge green">Voted</span></td>
          <td><?=e($r['confirmation_reference'] ?: '—')?></td>
          <td><?php if ($r['status']==='closed' && (int)$r['results_published']===1): ?><a class="btn secondary small" href="results.php?election_id=<?=$r['election_id']?>">View</a><?php else: ?><span class="muted">Not published</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted">You have not participated in any elections yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php page_footer(); ?>