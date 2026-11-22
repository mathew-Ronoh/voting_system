<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter','candidate','admin');
$candId = (int)($_GET['candidate_id'] ?? 0);
$eid = (int)($_GET['election_id'] ?? 0);

$sql = "SELECT c.*, u.full_name, u.status AS account_status,
        p.position_id, p.name AS position_name,
        e.election_id, e.title AS election_title, e.category, e.start_at, e.end_at, e.status AS election_status, e.results_published
        FROM candidates c
        JOIN users u ON u.user_id=c.user_id
        LEFT JOIN election_candidates ec ON ec.candidate_id=c.candidate_id
        LEFT JOIN positions p ON p.position_id=ec.position_id
        LEFT JOIN elections e ON e.election_id=ec.election_id
        WHERE c.candidate_id=?";
$params = [$candId];
if ($eid > 0) { $sql .= " AND e.election_id=?"; $params[] = $eid; }
$sql .= " LIMIT 1";
$s = $pdo->prepare($sql); $s->execute($params); $c = $s->fetch();
if (!$c) { http_response_code(404); exit('Candidate not found.'); }

$viewerRole = current_role();
$voter = ($viewerRole === 'voter') ? get_voter($pdo, current_user_id()) : null;
$voterId = (int)($voter['voter_id'] ?? 0);
$eligible = ($viewerRole === 'voter' && $voterId && !empty($c['election_id'])) ? is_voter_eligible_for_election($pdo, (int)$c['election_id'], $voterId) : false;
$voted = false;
if ($viewerRole === 'voter' && $voterId && !empty($c['election_id'])) {
    $ps = $pdo->prepare("SELECT participation_id FROM voter_participation WHERE election_id=? AND voter_id=?");
    $ps->execute([(int)$c['election_id'], $voterId]); $voted = (bool)$ps->fetch();
}
$canVote = ($viewerRole === 'voter' && $eligible && !$voted && ($c['election_status'] ?? '') === 'open');

page_header('Candidate Details', ['subtitle' => $c['full_name']]);
function show_block(string $label, ?string $value): void {
    if ($value === null || trim($value) === '') return;
    echo '<div class="panel"><div class="panel-header"><div><h2>'.e($label).'</h2></div></div><div style="color:#475569;line-height:1.8;white-space:pre-line">'.e($value).'</div></div>';
}
?>
<div class="page-stack">
  <div class="panel">
    <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
      <?php if(!empty($c['profile_image'])): ?>
        <img src="<?=e($c['profile_image'])?>" alt="<?=e($c['full_name'])?>" style="width:88px;height:88px;border-radius:22px;object-fit:cover;border:1px solid var(--border)">
      <?php else: ?>
        <div class="avatar" style="width:88px;height:88px;border-radius:22px;font-size:28px"><?=e(initials($c['full_name']))?></div>
      <?php endif; ?>
      <div style="flex:1;min-width:240px">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
          <span class="badge <?= ($c['account_status']==='active')?'green':'orange' ?>"><?=e(ucfirst($c['account_status']))?> Candidate</span>
          <?php if(!empty($c['election_status'])): ?><span class="badge <?= match($c['election_status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','draft'=>'gray',default=>'gray'} ?>"><?=e(ucfirst($c['election_status']))?></span><?php endif; ?>
        </div>
        <h1 style="margin-bottom:6px"><?=e($c['full_name'])?></h1>
        <p class="muted" style="margin:0">Candidate No: <?=e($c['candidate_number'] ?: 'CND-'.str_pad((string)$c['candidate_id'],4,'0',STR_PAD_LEFT))?></p>
      </div>
    </div>
    <div class="grid-2" style="margin-top:24px">
      <div><strong>Position contested:</strong> <div class="muted"><?=e($c['position_name'] ?: 'Not assigned')?></div></div>
      <div><strong>Election:</strong> <div class="muted"><?=e($c['election_title'] ?: 'Not assigned')?></div></div>
      <div><strong>Organization/Category:</strong> <div class="muted"><?=e($c['category'] ?: 'General election')?></div></div>
      <div><strong>Approval status:</strong> <div class="muted"><?=e(ucfirst($c['account_status']))?></div></div>
    </div>
  </div>

  <?php show_block('Biography', $c['biography']); ?>
  <?php if(!empty($c['education']) || !empty($c['experience']) || !empty($c['achievements'])): ?>
  <div class="panel">
    <div class="panel-header"><div><h2>Qualifications & Experience</h2></div></div>
    <?php if(!empty($c['education'])): ?><p><strong>Education:</strong><br><span style="white-space:pre-line;color:#475569"><?=e($c['education'])?></span></p><?php endif; ?>
    <?php if(!empty($c['experience'])): ?><p><strong>Leadership/Professional experience:</strong><br><span style="white-space:pre-line;color:#475569"><?=e($c['experience'])?></span></p><?php endif; ?>
    <?php if(!empty($c['achievements'])): ?><p style="margin-bottom:0"><strong>Achievements:</strong><br><span style="white-space:pre-line;color:#475569"><?=e($c['achievements'])?></span></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if(!empty($c['vision']) || !empty($c['mission']) || !empty($c['manifesto'])): ?>
  <div class="panel">
    <div class="panel-header"><div><h2>Election Manifesto</h2></div></div>
    <?php if(!empty($c['vision'])): ?><p><strong>Vision:</strong><br><span style="white-space:pre-line;color:#475569"><?=e($c['vision'])?></span></p><?php endif; ?>
    <?php if(!empty($c['mission'])): ?><p><strong>Mission:</strong><br><span style="white-space:pre-line;color:#475569"><?=e($c['mission'])?></span></p><?php endif; ?>
    <?php if(!empty($c['manifesto'])): ?><p style="margin-bottom:0"><strong>Key priorities / manifesto:</strong><br><span style="white-space:pre-line;color:#475569"><?=e($c['manifesto'])?></span></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if(!empty($c['slogan']) || !empty($c['campaign_info']) || !empty($c['public_contact'])): ?>
  <div class="panel">
    <div class="panel-header"><div><h2>Campaign Information</h2></div></div>
    <?php if(!empty($c['slogan'])): ?><p><strong>Slogan:</strong> <?=e($c['slogan'])?></p><?php endif; ?>
    <?php if(!empty($c['campaign_info'])): ?><p><strong>Campaign details:</strong><br><span style="white-space:pre-line;color:#475569"><?=e($c['campaign_info'])?></span></p><?php endif; ?>
    <?php if(!empty($c['public_contact'])): ?><p style="margin-bottom:0"><strong>Public contact:</strong> <?=e($c['public_contact'])?></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if(!empty($c['election_id'])): ?>
  <div class="panel">
    <div class="panel-header"><div><h2>Election Information</h2></div></div>
    <div class="grid-2">
      <div><strong>Election:</strong> <div class="muted"><?=e($c['election_title'])?></div></div>
      <div><strong>Position:</strong> <div class="muted"><?=e($c['position_name'])?></div></div>
      <div><strong>Start:</strong> <div class="muted"><?=e(date('M j, Y g:i A', strtotime($c['start_at'])))?></div></div>
      <div><strong>End:</strong> <div class="muted"><?=e(date('M j, Y g:i A', strtotime($c['end_at'])))?></div></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Voting Action</h2><p>Voting is available only for eligible voters during an active election.</p></div></div>
    <?php if($canVote): ?>
      <a class="btn" href="/voting_system/voter/ballot.php?election_id=<?=$c['election_id']?>">Vote for this Candidate</a>
      <p class="muted" style="margin-top:10px;font-size:13px">You will select your candidate on the ballot page. This button opens the ballot for the election.</p>
    <?php elseif($viewerRole==='voter' && $voted): ?>
      <span class="badge green">You have already submitted your ballot for this election.</span>
    <?php elseif($viewerRole==='voter' && !$eligible): ?>
      <span class="badge orange">You are not eligible for this election.</span>
    <?php elseif(($c['election_status'] ?? '')!=='open'): ?>
      <span class="badge gray">Voting is not currently open for this election.</span>
    <?php else: ?>
      <span class="muted">Sign in as an eligible voter to vote.</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php page_footer(); ?>