<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
$pid = (int)($_GET['position_id'] ?? 0);
$eid = (int)($_GET['election_id'] ?? 0);
$s = $pdo->prepare("SELECT p.*, e.title AS election_title, e.status AS election_status, e.start_at, e.end_at, e.results_published
    FROM positions p JOIN elections e ON e.election_id=p.election_id
    WHERE p.position_id=? AND p.election_id=? AND p.status='active'");
$s->execute([$pid,$eid]); $pos = $s->fetch();
if (!$pos) { http_response_code(404); exit('Position not found.'); }
if (!in_array($pos['election_status'], ['scheduled','open','closed','published'], true)) { http_response_code(404); exit('Position not available.'); }

$voter = get_voter($pdo, current_user_id());
$voterId = (int)($voter['voter_id'] ?? 0);
$eligible = $voterId ? is_voter_eligible_for_election($pdo,$eid,$voterId) : false;
$ps = $pdo->prepare("SELECT participation_id FROM voter_participation WHERE election_id=? AND voter_id=?");
$ps->execute([$eid,$voterId]); $voted = (bool)$ps->fetch();

$cands = $pdo->prepare("SELECT c.candidate_id, c.candidate_number, c.profile_image, c.biography, c.vision, c.mission, c.manifesto, c.slogan, u.full_name
    FROM election_candidates ec
    JOIN candidates c ON c.candidate_id=ec.candidate_id
    JOIN users u ON u.user_id=c.user_id
    WHERE ec.position_id=? AND ec.election_id=? AND u.status='active'
    ORDER BY u.full_name");
$cands->execute([$pid,$eid]); $rows = $cands->fetchAll();

page_header($pos['name'], ['subtitle' => $pos['election_title']]);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2><?=e($pos['name'])?></h2>
        <p><?=e($pos['description'] ?: 'Review approved candidates for this position.')?></p>
      </div>
      <span class="badge <?= match($pos['election_status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','published'=>'blue',default=>'gray'} ?>"><?=e(ucfirst($pos['election_status']))?></span>
    </div>
    <div class="grid-2">
      <div><strong>Election:</strong> <div class="muted"><?=e($pos['election_title'])?></div></div>
      <div><strong>Candidates:</strong> <div class="muted"><?=count($rows)?> approved</div></div>
    </div>
    <div class="actions" style="margin-top:16px">
      <a class="btn secondary small" href="election.php?election_id=<?=$eid?>">Back to election</a>
      <?php if($pos['election_status']==='open' && !$voted && $eligible): ?>
        <a class="btn small" href="ballot.php?election_id=<?=$eid?>">Open Ballot</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="cards">
    <?php foreach($rows as $c): ?>
      <article class="card">
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
          <?php if(!empty($c['profile_image'])): ?><img src="<?=e($c['profile_image'])?>" alt="" style="width:52px;height:52px;border-radius:14px;object-fit:cover"><?php else: ?><div class="avatar" style="width:52px;height:52px;border-radius:14px"><?=e(initials($c['full_name']))?></div><?php endif; ?>
          <div>
            <strong style="display:block"><?=e($c['full_name'])?></strong>
            <div class="muted" style="font-size:12px"><?=e($c['candidate_number'] ?: 'CND-'.str_pad((string)$c['candidate_id'],4,'0',STR_PAD_LEFT))?></div>
          </div>
        </div>
        <?php if(!empty($c['biography'])): ?><p class="muted" style="font-size:13px;margin-bottom:8px"><strong>Bio:</strong> <?=e(mb_strimwidth($c['biography'],0,140,'…'))?></p><?php endif; ?>
        <?php if(!empty($c['vision'])): ?><p class="muted" style="font-size:13px;margin-bottom:8px"><strong>Vision:</strong> <?=e(mb_strimwidth($c['vision'],0,120,'…'))?></p><?php endif; ?>
        <?php if(!empty($c['mission'])): ?><p class="muted" style="font-size:13px;margin-bottom:8px"><strong>Mission:</strong> <?=e(mb_strimwidth($c['mission'],0,120,'…'))?></p><?php endif; ?>
        <p class="muted" style="font-size:13px;margin-bottom:14px"><strong>Manifesto:</strong> <?=e(mb_strimwidth((string)($c['manifesto'] ?: 'No manifesto provided.'),0,140,'…'))?></p>
        <a class="btn secondary small" href="candidate.php?candidate_id=<?=$c['candidate_id']?>&election_id=<?=$eid?>">View Full Profile</a>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if(!$rows): ?><div class="panel empty-state"><div class="empty-icon">▣</div><p>No approved candidates assigned to this position yet.</p></div><?php endif; ?>
</div>
<?php page_footer(); ?>
