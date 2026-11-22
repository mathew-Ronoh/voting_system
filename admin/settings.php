<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_role('admin');
page_header('System Settings', ['subtitle'=>'Basic system configuration and information']);
$dbName = 'voting_system_db';
$phpVersion = PHP_VERSION;
$server = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
?>
<div class="page-stack">
  <div class="panel" style="max-width:900px">
    <div class="panel-header"><div><h2>System information</h2><p>Current environment and application configuration.</p></div></div>
    <div class="table-wrap" style="box-shadow:none;border:0"><table>
      <tbody>
        <tr><th style="width:260px">Application</th><td>Electronic Voting Management System</td></tr>
        <tr><th>Database</th><td><?=e($dbName)?></td></tr>
        <tr><th>PHP version</th><td><?=e($phpVersion)?></td></tr>
        <tr><th>Server</th><td><?=e($server)?></td></tr>
        <tr><th>Session user</th><td><?=e($_SESSION['full_name'] ?? '')?> (<?=e($_SESSION['role'] ?? '')?>)</td></tr>
      </tbody>
    </table></div>
  </div>
  <div class="panel" style="max-width:900px">
    <div class="panel-header"><div><h2>Security reminders</h2><p>Important operational notes for this prototype.</p></div></div>
    <ul style="margin:0;padding-left:18px;color:#475569;line-height:1.8">
      <li>Delete <code>setup_admin.php</code> after initial administrator creation.</li>
      <li>Use HTTPS, strong credentials, and secure hosting before real deployment.</li>
      <li>Review role permissions, backups, and audit logs regularly.</li>
      <li>This is an educational prototype; commission an independent security review before real elections.</li>
    </ul>
  </div>
</div>
<?php page_footer(); ?>