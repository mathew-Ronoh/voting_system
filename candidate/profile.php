<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $bio = trim($_POST['biography'] ?? '');
    $edu = trim($_POST['education'] ?? '');
    $exp = trim($_POST['experience'] ?? '');
    $ach = trim($_POST['achievements'] ?? '');
    $vision = trim($_POST['vision'] ?? '');
    $mission = trim($_POST['mission'] ?? '');
    $manifesto = trim($_POST['manifesto'] ?? '');
    $slogan = trim($_POST['slogan'] ?? '');
    $campaign = trim($_POST['campaign_info'] ?? '');
    $publicContact = trim($_POST['public_contact'] ?? '');
    if ($name === '') { flash('Full name is required.', 'error'); redirect('/voting_system/candidate/profile.php'); }
    try {
        $pdo->beginTransaction();
        if ($password !== '') {
            if (strlen($password) < 10) { throw new RuntimeException('Password must be at least 10 characters.'); }
            $pdo->prepare("UPDATE users SET full_name=?,phone=?,password_hash=? WHERE user_id=?")->execute([$name,$phone?:null,password_hash($password,PASSWORD_DEFAULT),$uid]);
        } else {
            $pdo->prepare("UPDATE users SET full_name=?,phone=? WHERE user_id=?")->execute([$name,$phone?:null,$uid]);
        }

        // photo upload
        $photoPath = null;
        if (!empty($_FILES['profile_image']['name']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
            $f = $_FILES['profile_image'];
            if ($f['size'] > 2*1024*1024) throw new RuntimeException('Profile image must be 2MB or smaller.');
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            $ext = match($mime){ 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp', default=>null };
            if(!$ext) throw new RuntimeException('Profile image must be JPG, PNG, or WEBP.');
            $fname = 'cand_'.$uid.'_'.bin2hex(random_bytes(6)).'.'.$ext;
            $dest = __DIR__.'/../assets/uploads/candidates/'.$fname;
            if(!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('Could not save profile image.');
            $photoPath = '/voting_system/assets/uploads/candidates/'.$fname;
        }

        $sql = "UPDATE candidates SET biography=?,education=?,experience=?,achievements=?,vision=?,mission=?,manifesto=?,slogan=?,campaign_info=?,public_contact=?";
        $params = [$bio?:null,$edu?:null,$exp?:null,$ach?:null,$vision?:null,$mission?:null,$manifesto?:null,$slogan?:null,$campaign?:null,$publicContact?:null];
        if ($photoPath) { $sql .= ",profile_image=?"; $params[]=$photoPath; }
        $sql .= " WHERE user_id=?"; $params[]=$uid;
        $pdo->prepare($sql)->execute($params);
        $pdo->commit();
        $_SESSION['full_name'] = $name;
        log_action($pdo,'candidate_profile_updated','User #'.$uid);
        flash('Profile updated.');
    } catch (Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        flash($e instanceof RuntimeException ? $e->getMessage() : 'Could not update profile.', 'error');
    }
    redirect('/voting_system/candidate/profile.php');
}

$s = $pdo->prepare("SELECT u.*, c.* FROM users u LEFT JOIN candidates c ON c.user_id=u.user_id WHERE u.user_id=?");
$s->execute([$uid]); $u = $s->fetch();
page_header('My Profile', ['subtitle'=>'Manage your candidate profile and public information']);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Candidate Profile Management</h2><p>Update information visible to voters on your public candidate details page. Private credentials are never shown publicly.</p></div><span class="badge green">Active</span></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <div class="grid-2">
        <label>Full name<input name="full_name" required value="<?=e($u['full_name']??'')?>"></label>
        <label>Phone (private)<input name="phone" value="<?=e($u['phone']??'')?>"></label>
      </div>
      <div class="grid-2">
        <label>Email (read-only)<input type="email" value="<?=e($u['email']??'')?>" disabled></label>
        <label>Candidate number<input value="<?=e($u['candidate_number'] ?: 'CND-'.str_pad((string)($u['candidate_id']??0),4,'0',STR_PAD_LEFT))?>" disabled></label>
      </div>
      <label>Profile photograph (JPG/PNG/WEBP, max 2MB)<input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"></label>
      <?php if(!empty($u['profile_image'])): ?><div class="muted" style="font-size:13px">Current: <a href="<?=e($u['profile_image'])?>" target="_blank">View image</a></div><?php endif; ?>
      <label>Short biography<textarea name="biography"><?=e($u['biography']??'')?></textarea></label>
      <div class="grid-2">
        <label>Educational background<textarea name="education"><?=e($u['education']??'')?></textarea></label>
        <label>Leadership/professional experience<textarea name="experience"><?=e($u['experience']??'')?></textarea></label>
      </div>
      <label>Achievements<textarea name="achievements"><?=e($u['achievements']??'')?></textarea></label>
      <div class="grid-2">
        <label>Vision<textarea name="vision"><?=e($u['vision']??'')?></textarea></label>
        <label>Mission<textarea name="mission"><?=e($u['mission']??'')?></textarea></label>
      </div>
      <label>Manifesto / key priorities<textarea name="manifesto" style="min-height:140px"><?=e($u['manifesto']??'')?></textarea></label>
      <div class="grid-2">
        <label>Campaign slogan<input name="slogan" value="<?=e($u['slogan']??'')?>"></label>
        <label>Public contact (optional, approved for public display)<input name="public_contact" value="<?=e($u['public_contact']??'')?>"></label>
      </div>
      <label>Campaign information<textarea name="campaign_info"><?=e($u['campaign_info']??'')?></textarea></label>
      <label>New password (optional)<input type="password" name="password" minlength="10" placeholder="Leave blank to keep current password"></label>
      <div><button class="btn">Save profile</button></div>
    </form>
  </div>
</div>
<?php page_footer(); ?>