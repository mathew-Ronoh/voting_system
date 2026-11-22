<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) { redirect(role_home(current_role())); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $reg = trim($_POST['voter_reference'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $reg === '' || strlen($password) < 10) {
        flash('Enter all required details. Password must be at least 10 characters.', 'error');
    } elseif ($password !== $confirm) {
        flash('Passwords do not match.', 'error');
    } else {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $s = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,status) VALUES(?,?,?,'voter','pending')");
            $s->execute([$name, $email, $hash]);
            $uid = (int)$pdo->lastInsertId();
            $s = $pdo->prepare("INSERT INTO voters(user_id,voter_reference,approval_status) VALUES(?,?,'pending')");
            $s->execute([$uid, $reg]);
            $pdo->commit();
            log_action($pdo,'voter_registered',$email);
            foreach($pdo->query("SELECT user_id FROM users WHERE role='admin' AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $aid){ notify_user($pdo,(int)$aid,'New voter registration',$name.' ('.$email.') registered and awaits approval.'); }
            flash('Registration submitted. An administrator must approve your account before you can vote.');
            redirect('/voting_system/login.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Registration could not be completed. The email or voter reference may already exist.', 'error');
        }
    }
}
page_header('Voter registration', ['layout' => 'public']);
?>
<div class="auth-card card" style="margin:0 auto;max-width:620px">
  <div class="auth-header">
    <div class="auth-logo">EV</div>
    <h1>Voter registration</h1>
    <p>Create your account. An administrator will review and approve it before you can vote.</p>
  </div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <div class="form-row">
      <label>Full name
        <input name="full_name" required maxlength="150" placeholder="Full name">
      </label>
      <label>National ID / Voter reference
        <input name="voter_reference" required maxlength="100" placeholder="ID or membership number">
      </label>
    </div>
    <label>Email address
      <input type="email" name="email" required maxlength="190" autocomplete="email" placeholder="you@example.com">
    </label>
    <div class="form-row">
      <label>Password
        <input type="password" name="password" required minlength="10" autocomplete="new-password" placeholder="At least 10 characters">
      </label>
      <label>Confirm password
        <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password" placeholder="Re-enter password">
      </label>
    </div>
    <p class="form-help">Self-registered voters have pending approval status until an administrator approves the account.</p>
    <button class="btn block" type="submit">Submit registration</button>
  </form>
  <div class="form-footer">Already have an account? <a href="/voting_system/login.php">Sign in</a></div>
</div>
<?php page_footer(['layout' => 'public']); ?>