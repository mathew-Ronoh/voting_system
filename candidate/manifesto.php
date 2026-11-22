<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$uid=current_user_id();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $manifesto=trim($_POST['manifesto']??'');
    $s=$pdo->prepare("UPDATE candidates SET manifesto=? WHERE user_id=?");
    $s->execute([$manifesto,$uid]);
    log_action($pdo,'manifesto_updated','User #'.$uid); notify_user($pdo,$uid,'Manifesto updated','Your candidate manifesto was updated.'); flash('Manifesto updated.');
    redirect('/voting_system/candidate/manifesto.php');
}
$s=$pdo->prepare("SELECT manifesto FROM candidates WHERE user_id=?"); $s->execute([$uid]); $c=$s->fetch();
page_header('My Manifesto', ['subtitle'=>'Manage your candidate manifesto']);
?>
<div class="page-stack"><div class="panel" style="max-width:860px">
  <div class="panel-header"><div><h2>Candidate manifesto</h2><p>Write or update your manifesto. It will be visible to voters in election details.</p></div></div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <label>Manifesto<textarea name="manifesto" style="min-height:220px" placeholder="Describe your goals, qualifications, and plans..."><?=e($c['manifesto']??'')?></textarea></label>
    <div><button class="btn">Save manifesto</button></div>
  </form>
</div></div>
<?php page_footer(); ?>