<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_role('admin');
page_header('Audit Logs', ['subtitle'=>'Administrative and system activity records']);
$rows=$pdo->query("SELECT a.*, u.full_name, u.email FROM audit_logs a LEFT JOIN users u ON u.user_id=a.actor_user_id ORDER BY a.created_at DESC LIMIT 100")->fetchAll();
?>
<div class="page-stack"><div class="panel">
  <div class="panel-header"><div><h2>Audit log</h2><p>Recent system and administrative actions.</p></div></div>
  <div class="table-wrap" style="box-shadow:none;border:0"><table>
    <thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?=e(date('M j, Y g:i A', strtotime($r['created_at'])))?></td>
        <td><?=e($r['full_name'] ?: 'System/Unknown')?><div class="muted" style="font-size:12px"><?=e($r['email'] ?? '')?></div></td>
        <td><strong><?=e($r['action'])?></strong></td>
        <td><?=e($r['details'] ?? '')?></td>
        <td><?=e($r['ip_address'] ?? '')?></td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$rows): ?><tr><td colspan="5" class="muted">No audit log entries yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div></div>
<?php page_footer(); ?>