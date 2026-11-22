<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
$uid = current_user_id();
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?")->execute([$nid,$uid]);
    redirect('/voting_system/voter/notifications.php');
}
if (isset($_GET['read_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    redirect('/voting_system/voter/notifications.php');
}
$s = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$s->execute([$uid]); $rows = $s->fetchAll();
page_header('Notifications', ['subtitle' => 'Your account and election notifications']);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Notifications</h2><p>Updates about registration, elections, and results.</p></div><a class="btn secondary small" href="?read_all=1">Mark all read</a></div>
    <div style="display:grid;gap:12px">
      <?php foreach($rows as $n): ?>
        <div class="card" style="padding:16px;<?= $n['is_read'] ? '' : 'border-color:#bfdbfe;background:#f8fbff' ?>">
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
            <div><strong><?=e($n['title'])?></strong><div class="muted" style="font-size:14px;margin-top:4px"><?=e($n['message'])?></div><small><?=e(date('M j, Y g:i A', strtotime($n['created_at'])))?></small></div>
            <?php if(!$n['is_read']): ?><a class="btn secondary small" href="?read=<?=$n['notification_id']?>">Mark read</a><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if(!$rows): ?><div class="empty-state"><div class="empty-icon">🔔</div><p>No notifications yet.</p></div><?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer(); ?>