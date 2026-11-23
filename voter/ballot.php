<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('voter');
$eid = (int)($_GET['election_id'] ?? $_POST['election_id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM elections WHERE election_id=? AND status='open' AND NOW() BETWEEN start_at AND end_at");
$s->execute([$eid]); $election = $s->fetch();
if (!$election) { http_response_code(404); exit('Election is not currently open.'); }

$voter = get_voter($pdo, current_user_id());
$voterId = (int)($voter['voter_id'] ?? 0);
if (!$voterId) { flash('Voter record not found.', 'error'); redirect('/voting_system/voter/dashboard.php'); }
if (($voter['approval_status'] ?? '') !== 'approved') { flash('Your voter account is not approved.', 'error'); redirect('/voting_system/voter/dashboard.php'); }
if (!is_voter_eligible_for_election($pdo,$eid,$voterId)) { flash('You are not eligible to vote in this election.', 'error'); redirect('/voting_system/voter/election.php?election_id='.$eid); }

$s = $pdo->prepare("SELECT participation_id FROM voter_participation WHERE election_id=? AND voter_id=?");
$s->execute([$eid, $voterId]);
if ($s->fetch()) { flash('You have already submitted a ballot for this election.', 'error'); redirect('/voting_system/voter/confirmation.php?election_id=' . $eid); }

$rows = $pdo->prepare("SELECT p.position_id,p.name AS position_name,p.max_winners,p.max_selections,p.number_of_seats,p.category,p.description,p.eligibility_requirements,
    c.candidate_id,u.full_name,ca.manifesto,ca.candidate_number,ca.profile_image
  FROM positions p
  JOIN election_candidates ec ON ec.position_id=p.position_id AND ec.election_id=p.election_id
  JOIN candidates c ON c.candidate_id=ec.candidate_id
  JOIN users u ON u.user_id=c.user_id
  JOIN candidates ca ON ca.candidate_id=c.candidate_id
  WHERE p.election_id=? AND p.status='active' AND u.status='active'
  ORDER BY p.created_at,p.position_id,u.full_name");
$rows->execute([$eid]);
$grouped = [];
foreach ($rows->fetchAll() as $r) {
    $pid = (int)$r['position_id'];
    $grouped[$pid]['name'] = $r['position_name'];
    $grouped[$pid]['description'] = $r['description'];
    $grouped[$pid]['max_winners'] = max(1, (int)($r['number_of_seats'] ?: $r['max_winners'] ?: $r['max_selections'] ?: 1));
    $grouped[$pid]['category'] = $r['category'];
    $grouped[$pid]['eligibility'] = $r['eligibility_requirements'];
    $grouped[$pid]['candidates'][] = $r;
}
// include active positions with no candidates? block voting - must be configured
$emptyPos = $pdo->prepare("SELECT p.position_id,p.name FROM positions p WHERE p.election_id=? AND p.status='active' AND NOT EXISTS (SELECT 1 FROM election_candidates ec JOIN candidates c ON c.candidate_id=ec.candidate_id JOIN users u ON u.user_id=c.user_id WHERE ec.position_id=p.position_id AND u.status='active')");
$emptyPos->execute([$eid]); $emptyPositions = $emptyPos->fetchAll();

$action = $_POST['action'] ?? '';
$choicesRaw = $_POST['choice'] ?? [];
if (!is_array($choicesRaw)) $choicesRaw = [];
$review = false;
$reviewSelections = [];

function normalize_choices(array $choicesRaw, array $grouped): array {
    $out = [];
    foreach ($grouped as $pid => $g) {
        $raw = $choicesRaw[$pid] ?? $choicesRaw[(string)$pid] ?? [];
        if (!is_array($raw)) $raw = [$raw];
        $ids = [];
        foreach ($raw as $v) { $v = (int)$v; if ($v > 0) $ids[] = $v; }
        $out[$pid] = array_values(array_unique($ids));
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($emptyPositions) { flash('Voting cannot proceed because some positions have no approved candidates.', 'error'); redirect('/voting_system/voter/ballot.php?election_id='.$eid); }
    if (!$grouped) { flash('No active positions/candidates are configured for this election.', 'error'); redirect('/voting_system/voter/ballot.php?election_id='.$eid); }
    $choices = normalize_choices($choicesRaw, $grouped);

    $valid = true; $err = 'Invalid candidate selection.';
    foreach ($grouped as $pid => $group) {
        $sel = $choices[$pid] ?? [];
        $max = (int)$group['max_winners'];
        if (count($sel) < 1 || count($sel) > $max) { $valid=false; $err='Select at least 1 and no more than '.$max.' candidate(s) for "'.$group['name'].'".'; break; }
        $allowed = [];
        foreach ($group['candidates'] as $c) $allowed[(int)$c['candidate_id']] = $c;
        $reviewSelections[$pid] = ['position'=>$group['name'],'candidates'=>[],'ids'=>$sel];
        foreach ($sel as $cid) {
            if (!isset($allowed[$cid])) { $valid=false; $err='Invalid candidate selection.'; break 2; }
            $reviewSelections[$pid]['candidates'][] = $allowed[$cid]['full_name'];
        }
    }
    if (!$valid) { flash($err, 'error'); redirect('/voting_system/voter/ballot.php?election_id='.$eid); }

    if ($action === 'review') {
        $review = true;
    } elseif ($action === 'confirm') {
        try {
            $pdo->beginTransaction();
            // recheck participation and eligibility inside tx
            $chk = $pdo->prepare("SELECT COUNT(*) FROM voter_participation WHERE election_id=? AND voter_id=? FOR UPDATE");
            $chk->execute([$eid,$voterId]);
            if ((int)$chk->fetchColumn() > 0) throw new RuntimeException('Already voted.');
            if (!is_voter_eligible_for_election($pdo,$eid,$voterId)) throw new RuntimeException('Not eligible.');

            $ref = confirmation_reference();
            $pdo->prepare("INSERT INTO voter_participation(election_id,voter_id,submitted_at,confirmation_reference) VALUES(?,?,NOW(),?)")->execute([$eid,$voterId,$ref]);
            $pdo->prepare("INSERT INTO ballots(election_id,submitted_at) VALUES(?,NOW())")->execute([$eid]);
            $bid = (int)$pdo->lastInsertId();
            $check = $pdo->prepare("SELECT COUNT(*) FROM election_candidates ec JOIN candidates c ON c.candidate_id=ec.candidate_id JOIN users u ON u.user_id=c.user_id JOIN positions p ON p.position_id=ec.position_id WHERE ec.election_id=? AND ec.position_id=? AND ec.candidate_id=? AND p.status='active' AND u.status='active'");
            $ins = $pdo->prepare("INSERT INTO votes(ballot_id,election_id,position_id,candidate_id) VALUES(?,?,?,?)");
            foreach ($grouped as $pid => $group) {
                $sel = $choices[$pid] ?? [];
                foreach ($sel as $cid) {
                    $check->execute([$eid,$pid,$cid]);
                    if ((int)$check->fetchColumn() !== 1) throw new RuntimeException('Invalid candidate.');
                    $ins->execute([$bid,$eid,$pid,$cid]);
                }
            }
            $pdo->commit();
            log_action($pdo, 'ballot_submitted', 'Voter submitted ballot for election #'.$eid);
            notify_user($pdo, current_user_id(), 'Vote submitted', 'Your vote for "'.$election['title'].'" was recorded. Reference: '.$ref);
            flash('Your ballot has been submitted successfully.');
            redirect('/voting_system/voter/confirmation.php?election_id='.$eid);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Your ballot was not accepted. You may already have voted or the selection was invalid.', 'error');
            redirect('/voting_system/voter/ballot.php?election_id='.$eid);
        }
    }
}

$normalized = normalize_choices($choicesRaw, $grouped);
page_header($review ? 'Review Ballot' : 'Election Ballot', ['subtitle' => $election['title']]);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2><?=e($election['title'])?></h2>
        <p><?= $review ? 'Review your selections before final submission.' : 'Select candidate(s) for each position according to the allowed number of winners. You will review before submitting.' ?></p>
      </div>
      <span class="badge green">Open</span>
    </div>
    <?php if($emptyPositions): ?><div class="alert error" style="margin-bottom:0">Voting is unavailable because these active positions have no approved candidates: <?=e(implode(', ', array_column($emptyPositions,'name')))?></div>
    <?php elseif(!$review): ?><div class="alert info" style="margin-bottom:0"><strong>Ballot instructions:</strong> You have <?=count($grouped)?> position(s) to vote for. Select the required candidate(s) for each position, then review and confirm. Your participation is recorded separately from ballot selections and cannot be changed after submission.</div><?php endif; ?>
  </div>

  <?php if ($review): ?>
    <div class="panel">
      <div class="panel-header"><div><h2>Review your ballot</h2><p>Confirm these selections. After submission, you cannot change your vote.</p></div></div>
      <div class="table-wrap" style="box-shadow:none;border:0"><table>
        <thead><tr><th>Position</th><th>Your selection(s)</th></tr></thead>
        <tbody>
          <?php foreach ($reviewSelections as $pid => $sel): ?>
            <tr><td><strong><?=e($sel['position'])?></strong></td><td><?=e(implode(', ', $sel['candidates']))?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <form method="post" style="margin-top:20px">
        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
        <input type="hidden" name="election_id" value="<?=$eid?>">
        <input type="hidden" name="action" value="confirm">
        <?php foreach ($normalized as $pid => $ids): foreach ($ids as $cid): ?>
          <input type="hidden" name="choice[<?= (int)$pid ?>][]" value="<?= (int)$cid ?>">
        <?php endforeach; endforeach; ?>
        <div class="actions">
          <button class="btn" type="submit">Confirm and Submit Vote</button>
          <a class="btn secondary" href="/voting_system/voter/ballot.php?election_id=<?=$eid?>">Go back</a>
        </div>
      </form>
    </div>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="election_id" value="<?=$eid?>">
      <input type="hidden" name="action" value="review">
      <?php foreach ($grouped as $pid => $g): $max=(int)$g['max_winners']; $isMulti=$max>1; ?>
        <section class="panel" style="margin-bottom:20px">
          <div class="panel-header"><div><h2><?=e($g['name'])?></h2><p><?= $isMulti ? 'Select up to '.$max.' candidate(s) for this position ('.$max.' seat'.($max>1?'s':'').').' : 'Select one candidate for this position (1 seat).' ?></p></div><div style="display:flex;gap:6px;flex-wrap:wrap"><?php if(!empty($g['category'])): ?><span class="badge gray"><?=e($g['category'])?></span><?php endif; ?><?php if($isMulti): ?><span class="badge orange"><?=$max?> seats</span><?php endif; ?></div></div>
          <?php if(!empty($g['description'])): ?><p class="muted" style="margin-top:-8px;margin-bottom:8px"><?=e($g['description'])?></p><?php endif; ?>
          <?php if(!empty($g['eligibility'])): ?><p class="muted" style="margin-top:0;margin-bottom:14px;font-size:13px"><strong>Eligibility:</strong> <?=e($g['eligibility'])?></p><?php endif; ?>
          <div style="display:grid;gap:12px">
            <?php foreach ($g['candidates'] as $c): $checked=in_array((int)$c['candidate_id'], $normalized[$pid] ?? [], true); ?>
              <label style="display:flex;gap:14px;align-items:flex-start;padding:16px;border:1px solid var(--border);border-radius:12px;cursor:pointer;background:var(--surface)">
                <input type="<?=$isMulti?'checkbox':'radio'?>" name="choice[<?=$pid?>]<?=$isMulti?'[]':''?>" value="<?=$c['candidate_id']?>" <?=$checked?'checked':''?> <?=$isMulti?'':'required'?> style="width:18px;height:18px;margin-top:2px;flex:0 0 18px">
                <?php if(!empty($c['profile_image'])): ?><img src="<?=e($c['profile_image'])?>" alt="" style="width:44px;height:44px;border-radius:12px;object-fit:cover;flex:0 0 44px"><?php else: ?><div class="avatar" style="width:44px;height:44px;border-radius:12px;font-size:14px;flex:0 0 44px"><?=e(initials($c['full_name']))?></div><?php endif; ?>
                <div>
                  <strong style="display:block;font-size:15px;color:var(--text)"><?=e($c['full_name'])?></strong>
                  <div class="muted" style="font-size:12px"><?=e($c['candidate_number'] ?: 'CND-'.str_pad((string)$c['candidate_id'],4,'0',STR_PAD_LEFT))?></div>
                  <div class="muted" style="font-size:14px;margin-top:4px"><?=e(mb_strimwidth((string)($c['manifesto'] ?: 'No manifesto provided.'),0,160,'…'))?></div>
                  <a href="candidate.php?candidate_id=<?=$c['candidate_id']?>&election_id=<?=$eid?>" target="_blank" style="font-size:13px">View full profile</a>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
      <?php if (!$grouped): ?>
        <div class="panel empty-state"><div class="empty-icon">▣</div><p>No active positions/candidates are configured for this election yet.</p></div>
      <?php else: ?>
        <div class="actions"><button class="btn" type="submit" <?= $emptyPositions?'disabled':'' ?>>Review Ballot</button><a class="btn secondary" href="/voting_system/voter/election.php?election_id=<?=$eid?>">Cancel</a></div>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
