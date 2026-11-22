<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) { redirect(role_home(current_role())); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $s = $pdo->prepare("SELECT user_id,full_name,email,password_hash,role,status FROM users WHERE email=? LIMIT 1");
    $s->execute([$email]); $u = $s->fetch();
    if ($u && password_verify($password, $u['password_hash']) && $u['status'] === 'active') {
        if ($u['role'] === 'voter') {
            $v = $pdo->prepare("SELECT approval_status FROM voters WHERE user_id=?");
            $v->execute([$u['user_id']]);
            if ($v->fetchColumn() !== 'approved') { flash('Your voter account is awaiting administrator approval.', 'error'); redirect('/voting_system/login.php'); }
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['user_id'];
        $_SESSION['full_name'] = $u['full_name'];
        $_SESSION['role'] = $u['role'];
        redirect(role_home($u['role']));
    }
    flash('Invalid login details or inactive account.', 'error');
}
page_header('Sign in', ['layout' => 'public']);
?>
<div class="auth-card card" style="margin:0 auto">
  <div class="auth-header">
    <div class="auth-logo">EV</div>
    <h1>Welcome back</h1>
    <p>Sign in to access your election dashboard.</p>
  </div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <label>Email address
      <input type="email" name="email" autocomplete="email" required placeholder="you@example.com">
    </label>
    <label>Password
      <div style="position:relative">
        <input type="password" name="password" id="password" autocomplete="current-password" required placeholder="Enter your password" style="padding-right:44px">
        <button type="button" id="togglePw" aria-label="Show password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:none;color:#64748b;cursor:pointer;padding:6px;font-size:12px;font-weight:600">Show</button>
      </div>
    </label>
    <div class="field-inline">
      <label class="checkbox"><input type="checkbox" name="remember"> <span>Remember me</span></label>
      <span class="muted" style="font-size:13px">Forgot password? Contact admin.</span>
    </div>
    <button class="btn block" type="submit">Sign in</button>
  </form>
  <div class="form-footer">Voter account not registered? <a href="/voting_system/register.php">Register here</a></div>
</div>
<script>
(function(){var p=document.getElementById('password'),b=document.getElementById('togglePw');if(!p||!b)return;b.addEventListener('click',function(){var s=p.type==='password';p.type=s?'text':'password';b.textContent=s?'Hide':'Show';});})();
</script>
<?php page_footer(['layout' => 'public']); ?>