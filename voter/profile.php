<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
$uid = current_user_id();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '') { flash('Full name is required.', 'error'); redirect('/voting_system/voter/profile.php'); }
    try {
        if ($password !== '') {
            if (strlen($password) < 10) { flash('Password must be at least 10 characters.', 'error'); redirect('/voting_system/voter/profile.php'); }
            $s=$pdo->prepare("UPDATE users SET full_name=?,phone=?,username=?,password_hash=? WHERE user_id=?");
            $s->execute([$name,$phone?:null,$username?:null,password_hash($password,PASSWORD_DEFAULT),$uid]);
        } else {
            $s=$pdo->prepare("UPDATE users SET full_name=?,phone=?,username=? WHERE user_id=?");
            $s->execute([$name,$phone?:null,$username?:null,$uid]);
        }
        $_SESSION['full_name'] = $name;
        log_action($pdo,'voter_profile_updated','User #'.$uid); flash('Profile updated.');
    } catch (Throwable $e) { flash('Could not update profile.', 'error'); }
    redirect('/voting_system/voter/profile.php');
}
$s=$pdo->prepare("SELECT u.*, v.voter_reference, v.approval_status FROM users u LEFT JOIN voters v ON v.user_id=u.user_id WHERE u.user_id=?");
$s->execute([$uid]); $u=$s->fetch();
page_header('My Profile', ['subtitle' => 'Manage your account information']);
?>
<div class="page-stack">
  <div class="panel" style="max-width:760px">
    <div class="panel-header"><div><h2>Profile information</h2><p>Update your personal details. Leave password blank to keep it unchanged.</p></div><span class="badge <?= ($u['approval_status']??'')==='approved'?'green':'orange' ?>"><?=e(ucfirst($u['approval_status']??'unknown'))?></span></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <div class="grid-2">
        <label>Full name<input name="full_name" required value="<?=e($u['full_name']??'')?>"></label>
        <label>Username<input name="username" value="<?=e($u['username']??'')?>"></label>
      </div>
      <div class="grid-2">
        <label>Email (read-only)<input type="email" value="<?=e($u['email']??'')?>" disabled></label>
        <label>Phone<input name="phone" value="<?=e($u['phone']??'')?>"></label>
      </div>
      <label>Voter reference / ID (read-only)<input value="<?=e($u['voter_reference']??'')?>" disabled></label>
      <label>New password (optional)<input type="password" name="password" minlength="10" placeholder="Leave blank to keep current password"></label>
      <div><button class="btn">Save changes</button></div>
    </form>
  </div>
</div>
<?php page_footer(); ?>