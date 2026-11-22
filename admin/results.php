<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_role('admin');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf(); $id=(int)($_POST['election_id']??0);
    $s=$pdo->prepare("UPDATE elections SET status='closed' WHERE election_id=? AND end_at<=NOW()");
    $s->execute([$id]);
    if($s->rowCount()){
        $pdo->prepare("UPDATE elections SET results_published=1 WHERE election_id=?")->execute([$id]);
        $t=$pdo->prepare('SELECT title FROM elections WHERE election_id=?'); $t->execute([$id]); $et=$t->fetchColumn(); foreach($pdo->query("SELECT user_id FROM users WHERE role IN ('voter','candidate') AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $uid){ notify_user($pdo,(int)$uid,'Results published','Results for "'.($et?:'an election').'" have been published.'); } log_action($pdo,'results_published','Election #'.$id); flash('Election closed and results published.');
    } else {
        // If already closed but not published, allow publish
        $s2=$pdo->prepare("UPDATE elections SET results_published=1 WHERE election_id=? AND status='closed' AND end_at<=NOW() AND results_published=0");
        $s2->execute([$id]);
        if($s2->rowCount()){ $t=$pdo->prepare('SELECT title FROM elections WHERE election_id=?'); $t->execute([$id]); $et=$t->fetchColumn(); foreach($pdo->query("SELECT user_id FROM users WHERE role IN ('voter','candidate') AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $uid){ notify_user($pdo,(int)$uid,'Results published','Results for "'.($et?:'an election').'" have been published.'); } log_action($pdo,'results_published','Election #'.$id); flash('Results published.'); }
        else flash('Only elections whose scheduled end time has passed can be closed and published.','error');
    }
    redirect('/voting_system/admin/results.php');
}
page_header('Results & Publishing', ['subtitle' => 'Close elections and publish results']);
$els=$pdo->query("SELECT election_id,title,status,end_at,results_published FROM elections ORDER BY start_at DESC")->fetchAll();
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Election results management</h2><p>Results are hidden from voters and candidates until the election has ended and you publish them.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table>
        <thead><tr><th>Election</th><th>End time</th><th>Status</th><th>Results</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($els as $el): ?>
          <tr>
            <td><strong><?=e($el['title'])?></strong></td>
            <td><?=e(date('M j, Y g:i A', strtotime($el['end_at'])))?></td>
            <td><span class="badge <?= match($el['status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','draft'=>'gray',default=>'gray'} ?>"><?=e(ucfirst($el['status']))?></span></td>
            <td><span class="badge <?= $el['results_published']?'green':'orange' ?>"><?= $el['results_published']?'Published':'Hidden' ?></span></td>
            <td>
              <?php if(!$el['results_published']): ?>
                <form method="post" class="inline">
                  <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="election_id" value="<?=$el['election_id']?>">
                  <button class="btn small">Close &amp; Publish</button>
                </form>
              <?php else: ?>
                <a class="btn secondary small" href="/voting_system/voter/results.php?election_id=<?=$el['election_id']?>">View</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$els): ?><tr><td colspan="5" class="muted">No elections found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_footer(); ?>