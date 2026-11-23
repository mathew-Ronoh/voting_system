<?php
require_once __DIR__ . '/includes/bootstrap.php';
$count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if ($count > 0) { exit('An administrator already exists. Delete this setup_admin.php file now.'); }
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 4) {
        $s = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,status) VALUES(?,?,?,'admin','active')");
        $s->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $message = 'Administrator created. Delete setup_admin.php immediately, then sign in.';
    } else {
        $message = 'Enter a name, valid email, and password of at least 4 characters.';
    }
}
page_header('Create first administrator', ['layout' => 'public']);
?>
<div class="auth-card card" style="margin:0 auto;max-width:560px">
  <div class="auth-header">
    <div class="auth-logo">EV</div>
    <h1>Initial administrator setup</h1>
    <p>Create the first administrator account for this installation.</p>
  </div>
  <div class="alert warning">Use this page only once on your local/development machine. <strong>Delete this file after creating the administrator.</strong></div>
  <?php if ($message): ?><div class="alert <?= str_starts_with($message, 'Administrator created') ? 'success' : 'error' ?>"><?=e($message)?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <label>Full name<input name="full_name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Password (minimum 4 characters)<input type="password" name="password" minlength="4" required></label>
    <button class="btn block" type="submit">Create administrator</button>
  </form>
</div>
<?php page_footer(['layout' => 'public']); ?>