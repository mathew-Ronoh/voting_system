<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['position_id'] ?? 0);
    $eid = (int)($_POST['election_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $responsibilities = trim($_POST['responsibilities'] ?? '');
    $maxWinners = max(1, (int)($_POST['max_winners'] ?? 1));
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($action === 'create' || $action === 'update') {
        if (!$eid || $name === '') {
            flash('Select an election and enter a position name.', 'error');
            redirect('/voting_system/admin/positions.php' . ($action==='update' && $id ? '?edit='.$id : ''));
        }
        $eChk = $pdo->prepare("SELECT election_id,status FROM elections WHERE election_id=?");
        $eChk->execute([$eid]); $eRow = $eChk->fetch();
        if (!$eRow) { flash('Selected election was not found.', 'error'); redirect('/voting_system/admin/positions.php'); }

        try {
            if ($action === 'create') {
                $s = $pdo->prepare("INSERT INTO positions(election_id,name,description,responsibilities,max_winners,max_selections,status) VALUES(?,?,?,?,?,?,?)");
                $s->execute([$eid,$name,$description?:null,$responsibilities?:null,$maxWinners,$maxWinners,$status]);
                log_action($pdo,'position_created','Position "'.$name.'" in election #'.$eid);
                flash('Position created successfully.');
            } else {
                $own = $pdo->prepare("SELECT position_id FROM positions WHERE position_id=?"); $own->execute([$id]);
                if (!$own->fetch()) { flash('Position not found.', 'error'); redirect('/voting_system/admin/positions.php'); }
                // prevent changing election if candidates/votes exist? allow but be careful; keep simple: allow change only if no votes
                $voteCount = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE position_id=?"); $voteCount->execute([$id]);
                if ((int)$voteCount->fetchColumn() > 0) {
                    // lock election binding and max winners reduction below cast? keep election fixed
                    $cur = $pdo->prepare("SELECT election_id FROM positions WHERE position_id=?"); $cur->execute([$id]); $curEid=(int)$cur->fetchColumn();
                    if ($curEid !== $eid) { flash('Cannot change election for a position that already has votes.', 'error'); redirect('/voting_system/admin/positions.php?edit='.$id); }
                }
                $s = $pdo->prepare("UPDATE positions SET election_id=?,name=?,description=?,responsibilities=?,max_winners=?,max_selections=?,status=? WHERE position_id=?");
                $s->execute([$eid,$name,$description?:null,$responsibilities?:null,$maxWinners,$maxWinners,$status,$id]);
                log_action($pdo,'position_updated','Position #'.$id.' "'.$name.'"');
                flash('Position updated successfully.');
            }
        } catch (Throwable $e) {
            flash('Could not save position. Check for duplicate name in the same election.', 'error');
        }
        redirect('/voting_system/admin/positions.php');
    }

    if ($action === 'toggle' && $id) {
        $s=$pdo->prepare("SELECT status,name FROM positions WHERE position_id=?"); $s->execute([$id]); $p=$s->fetch();
        if ($p) {
            $new = $p['status']==='active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE positions SET status=? WHERE position_id=?")->execute([$new,$id]);
            log_action($pdo,'position_status_changed','Position #'.$id.' '.$new);
            flash('Position '.($new==='active'?'activated':'deactivated').'.');
        }
        redirect('/voting_system/admin/positions.php');
    }

    if ($action === 'delete' && $id) {
        $vc = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE position_id=?"); $vc->execute([$id]);
        $cc = $pdo->prepare("SELECT COUNT(*) FROM election_candidates WHERE position_id=?"); $cc->execute([$id]);
        $votes = (int)$vc->fetchColumn(); $cands = (int)$cc->fetchColumn();
        if ($votes > 0) {
            flash('Cannot delete a position that already has recorded votes.', 'error');
        } elseif ($cands > 0) {
            flash('Cannot delete a position with assigned candidates. Remove assignments first or deactivate the position.', 'error');
        } else {
            $pdo->prepare("DELETE FROM positions WHERE position_id=?")->execute([$id]);
            log_action($pdo,'position_deleted','Position #'.$id);
            flash('Position deleted.');
        }
        redirect('/voting_system/admin/positions.php');
    }
    redirect('/voting_system/admin/positions.php');
}

$edit = null;
if ($editId) {
    $s = $pdo->prepare("SELECT p.*, e.title AS election_title FROM positions p JOIN elections e ON e.election_id=p.election_id WHERE p.position_id=?");
    $s->execute([$editId]); $edit = $s->fetch() ?: null;
}

$elections = $pdo->query("SELECT election_id,title,status,start_at,end_at FROM elections ORDER BY created_at DESC")->fetchAll();
$q = trim($_GET['q'] ?? '');
$filterElection = (int)($_GET['election_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$where = []; $params = [];
if ($q !== '') { $where[] = "(p.name LIKE ? OR p.description LIKE ? OR e.title LIKE ?)"; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
if ($filterElection > 0) { $where[] = "p.election_id=?"; $params[]=$filterElection; }
if (in_array($filterStatus,['active','inactive'],true)) { $where[] = "p.status=?"; $params[]=$filterStatus; }
$sql = "SELECT p.*, e.title AS election_title, e.status AS election_status,
        (SELECT COUNT(*) FROM election_candidates ec WHERE ec.position_id=p.position_id) AS candidate_count
        FROM positions p JOIN elections e ON e.election_id=p.election_id";
if ($where) $sql .= " WHERE ".implode(' AND ',$where);
$sql .= " ORDER BY e.created_at DESC, p.created_at DESC, p.position_id DESC";
$st = $pdo->prepare($sql); $st->execute($params); $rows = $st->fetchAll();

$totals = $pdo->query("SELECT COUNT(*) total,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) active_count,
    SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) inactive_count
    FROM positions")->fetch();
$assignedTotal = (int)$pdo->query("SELECT COUNT(*) FROM election_candidates")->fetchColumn();

page_header('Candidate Positions Management', ['subtitle' => 'Create and manage leadership positions for different elections']);
?>
<div class="page-stack">
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon blue">▣</div><div><div class="stat-value"><?= (int)($totals['total']??0) ?></div><div class="stat-label">Total positions</div></div></div>
    <div class="stat-card"><div class="stat-icon green">●</div><div><div class="stat-value"><?= (int)($totals['active_count']??0) ?></div><div class="stat-label">Active positions</div></div></div>
    <div class="stat-card"><div class="stat-icon orange">◌</div><div><div class="stat-value"><?= (int)($totals['inactive_count']??0) ?></div><div class="stat-label">Inactive positions</div></div></div>
    <div class="stat-card"><div class="stat-icon red">◉</div><div><div class="stat-value"><?= $assignedTotal ?></div><div class="stat-label">Total assigned candidates</div></div></div>
  </div>

  <div class="panel">
    <div class="panel-header">
      <div><h2><?= $edit ? 'Edit position' : 'Add new position' ?></h2><p><?= $edit ? 'Update the position details. Changes are validated on the server.' : 'Create a leadership position for a specific election.' ?></p></div>
      <?php if($edit): ?><a class="btn secondary small" href="positions.php">Cancel edit</a><?php endif; ?>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if($edit): ?><input type="hidden" name="position_id" value="<?=$edit['position_id']?>"><?php endif; ?>
      <div class="grid-2">
        <label>Select election
          <select name="election_id" required>
            <option value="">Choose election</option>
            <?php foreach($elections as $el): ?>
              <option value="<?=$el['election_id']?>" <?= ((int)($edit['election_id']??0)===(int)$el['election_id'])?'selected':'' ?>><?=e($el['title'])?> (<?=e(ucfirst($el['status']))?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Position name
          <input name="name" required maxlength="120" placeholder="e.g. President, Chairperson" value="<?=e($edit['name']??'')?>">
        </label>
      </div>
      <label>Position description
        <textarea name="description" placeholder="Brief description of the position"><?=e($edit['description']??'')?></textarea>
      </label>
      <label>Responsibilities of the position
        <textarea name="responsibilities" placeholder="Key responsibilities and duties"><?=e($edit['responsibilities']??'')?></textarea>
      </label>
      <div class="grid-2">
        <label>Maximum number of winners
          <input type="number" name="max_winners" min="1" max="50" value="<?= (int)($edit['max_winners'] ?? $edit['max_selections'] ?? 1) ?>">
        </label>
        <label>Position status
          <select name="status">
            <option value="active" <?= (($edit['status']??'active')==='active')?'selected':'' ?>>Active</option>
            <option value="inactive" <?= (($edit['status']??'')==='inactive')?'selected':'' ?>>Inactive</option>
          </select>
        </label>
      </div>
      <div class="actions">
        <button class="btn" type="submit"><?= $edit ? 'Save Position' : 'Save Position' ?></button>
        <?php if($edit): ?><a class="btn secondary" href="positions.php">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Positions</h2><p>Search, filter, and manage election positions.</p></div></div>
    <form method="get" class="filters" style="display:grid;grid-template-columns:1fr 220px 180px auto;gap:10px;margin-bottom:16px">
      <input name="q" placeholder="Search position, description, or election" value="<?=e($q)?>">
      <select name="election_id">
        <option value="0">All elections</option>
        <?php foreach($elections as $el): ?><option value="<?=$el['election_id']?>" <?=$filterElection===(int)$el['election_id']?'selected':''?>><?=e($el['title'])?></option><?php endforeach; ?>
      </select>
      <select name="status">
        <option value="">All statuses</option>
        <option value="active" <?=$filterStatus==='active'?'selected':''?>>Active</option>
        <option value="inactive" <?=$filterStatus==='inactive'?'selected':''?>>Inactive</option>
      </select>
      <div class="actions"><button class="btn small" type="submit">Filter</button><a class="btn secondary small" href="positions.php">Reset</a></div>
    </form>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table>
        <thead><tr><th>ID</th><th>Position</th><th>Election</th><th>Description</th><th>Candidates</th><th>Max Winners</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?=$r['position_id']?></td>
            <td><strong><?=e($r['name'])?></strong></td>
            <td><?=e($r['election_title'])?><div class="muted" style="font-size:12px"><?=e(ucfirst($r['election_status']))?></div></td>
            <td><div style="max-width:260px;white-space:normal;color:#475569;font-size:13px"><?=e(mb_strimwidth((string)($r['description']??''),0,90,'…')) ?: '—'?></div></td>
            <td><?= (int)$r['candidate_count'] ?></td>
            <td><?= (int)($r['max_winners'] ?? $r['max_selections'] ?? 1) ?></td>
            <td><span class="badge <?= $r['status']==='active'?'green':'red' ?>"><?=e(ucfirst($r['status']))?></span></td>
            <td><small><?=e($r['created_at'] ? date('M j, Y', strtotime($r['created_at'])) : '—')?></small></td>
            <td>
              <div class="table-actions">
                <a class="btn secondary small" href="position_details.php?position_id=<?=$r['position_id']?>">View</a>
                <a class="btn small" href="position_details.php?position_id=<?=$r['position_id']?>#assign-candidate">Assign</a>
                <a class="btn secondary small" href="positions.php?edit=<?=$r['position_id']?>">Edit</a>
                <form class="inline" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="position_id" value="<?=$r['position_id']?>"><button class="btn small <?= $r['status']==='active'?'secondary':'btn' ?>" type="submit"><?= $r['status']==='active'?'Deactivate':'Activate' ?></button></form>
                <form class="inline" method="post" onsubmit="return confirm('Delete this position? This is only allowed when it has no candidates or votes.')"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="position_id" value="<?=$r['position_id']?>"><button class="btn danger small" type="submit">Delete</button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="9" class="muted">No positions found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_footer(); ?>
