<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

function predefined_positions(): array {
    return [
        ['President','Executive Leadership','Leads the student body/organization and represents members in official matters.','Must be a registered member in good standing; meets election eligibility criteria.',1],
        ['Vice President','Executive Leadership','Supports the President and assumes duties when required.','Must be a registered member in good standing; meets election eligibility criteria.',1],
        ['Secretary General','Executive Leadership','Maintains records, correspondence, and official communications.','Must be a registered member in good standing; good communication and organizational skills.',1],
        ['Deputy Secretary General','Executive Leadership','Assists the Secretary General and acts in their absence.','Must be a registered member in good standing; good organizational skills.',1],
        ['Treasurer','Finance','Manages financial records, budgets, and accountability.','Must be a registered member in good standing; basic financial accountability knowledge preferred.',1],
        ['Academic Secretary','Academic Affairs','Handles academic-related issues and liaises on academic matters.','Must be a registered member in good standing; enrolled/active in academic program where applicable.',1],
        ['Organizing Secretary','Student Welfare','Coordinates events, activities, and logistics.','Must be a registered member in good standing; strong coordination skills.',1],
        ['Social Welfare Secretary','Student Welfare','Promotes member welfare, support services, and wellbeing initiatives.','Must be a registered member in good standing; committed to welfare advocacy.',1],
        ["Men's Representative",'Student Representation',"Represents men's interests and concerns.","Must be a registered male member in good standing where applicable.",1],
        ["Women's Representative",'Student Representation',"Represents women's interests and concerns.","Must be a registered female member in good standing where applicable.",1],
        ['Sports Representative','Student Representation','Represents sports and extracurricular activity interests.','Must be a registered member in good standing; interest in sports/activities.',1],
        ['Disability Representative','Student Representation','Advocates for accessibility and inclusion of members with disabilities.','Must be a registered member in good standing; committed to inclusion and accessibility.',1],
        ['Year Representative','Student Representation','Represents the interests of a specific year/group cohort.','Must belong to the represented year/group and be in good standing.',1],
    ];
}

$editId = (int)($_GET['edit'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['position_id'] ?? 0);
    $eid = (int)($_POST['election_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $responsibilities = trim($_POST['responsibilities'] ?? '');
    $eligibility = trim($_POST['eligibility_requirements'] ?? '');
    $seats = max(1, (int)($_POST['number_of_seats'] ?? $_POST['max_winners'] ?? 1));
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($action === 'load_predefined') {
        if (!$eid) { flash('Select an election to load predefined positions.', 'error'); redirect('/voting_system/admin/positions.php'); }
        $chk = $pdo->prepare("SELECT election_id FROM elections WHERE election_id=?"); $chk->execute([$eid]);
        if (!$chk->fetch()) { flash('Election not found.', 'error'); redirect('/voting_system/admin/positions.php'); }
        $ins = $pdo->prepare("INSERT INTO positions(election_id,name,category,description,eligibility_requirements,number_of_seats,max_winners,max_selections,status) SELECT ?,?,?,?,?,?,?,?,'active' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM positions WHERE election_id=? AND name=?)");
        $added=0;
        foreach (predefined_positions() as $p) {
            $ins->execute([$eid,$p[0],$p[1],$p[2],$p[3],$p[4],$p[4],$p[4],$eid,$p[0]]);
            $added += $ins->rowCount();
        }
        log_action($pdo,'positions_predefined_loaded',"Loaded $added predefined positions for election #$eid");
        flash($added ? "$added predefined position(s) added." : 'All predefined positions already exist for this election.');
        redirect('/voting_system/admin/positions.php?election_id='.$eid);
    }

    if ($action === 'create' || $action === 'update') {
        if (!$eid || $name === '') { flash('Select an election and enter a position name.', 'error'); redirect('/voting_system/admin/positions.php'.($action==='update'&&$id?'?edit='.$id:'')); }
        $eChk = $pdo->prepare("SELECT election_id FROM elections WHERE election_id=?"); $eChk->execute([$eid]);
        if (!$eChk->fetch()) { flash('Selected election was not found.', 'error'); redirect('/voting_system/admin/positions.php'); }
        try {
            if ($action === 'create') {
                $s = $pdo->prepare("INSERT INTO positions(election_id,name,category,description,responsibilities,eligibility_requirements,number_of_seats,max_winners,max_selections,status) VALUES(?,?,?,?,?,?,?,?,?,?)");
                $s->execute([$eid,$name,$category?:null,$description?:null,$responsibilities?:null,$eligibility?:null,$seats,$seats,$seats,$status]);
                log_action($pdo,'position_created','Position "'.$name.'" in election #'.$eid);
                flash('Position created successfully.');
            } else {
                $own = $pdo->prepare("SELECT position_id,election_id FROM positions WHERE position_id=?"); $own->execute([$id]); $cur=$own->fetch();
                if (!$cur) { flash('Position not found.','error'); redirect('/voting_system/admin/positions.php'); }
                $voteCount = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE position_id=?"); $voteCount->execute([$id]);
                if ((int)$voteCount->fetchColumn() > 0 && (int)$cur['election_id'] !== $eid) { flash('Cannot change election for a position that already has votes.', 'error'); redirect('/voting_system/admin/positions.php?edit='.$id); }
                $s = $pdo->prepare("UPDATE positions SET election_id=?,name=?,category=?,description=?,responsibilities=?,eligibility_requirements=?,number_of_seats=?,max_winners=?,max_selections=?,status=? WHERE position_id=?");
                $s->execute([$eid,$name,$category?:null,$description?:null,$responsibilities?:null,$eligibility?:null,$seats,$seats,$seats,$status,$id]);
                log_action($pdo,'position_updated','Position #'.$id.' "'.$name.'"');
                flash('Position updated successfully.');
            }
        } catch (Throwable $e) { flash('Could not save position. Check for duplicate name in the same election.', 'error'); }
        redirect('/voting_system/admin/positions.php');
    }

    if ($action === 'toggle' && $id) {
        $s=$pdo->prepare("SELECT status,name FROM positions WHERE position_id=?"); $s->execute([$id]); $p=$s->fetch();
        if ($p) { $new=$p['status']==='active'?'inactive':'active'; $pdo->prepare("UPDATE positions SET status=? WHERE position_id=?")->execute([$new,$id]); log_action($pdo,'position_status_changed','Position #'.$id.' '.$new); flash('Position '.($new==='active'?'activated':'deactivated').'.'); }
        redirect('/voting_system/admin/positions.php');
    }

    if ($action === 'delete' && $id) {
        $vc=$pdo->prepare("SELECT COUNT(*) FROM votes WHERE position_id=?"); $vc->execute([$id]);
        $cc=$pdo->prepare("SELECT COUNT(*) FROM election_candidates WHERE position_id=?"); $cc->execute([$id]);
        $ac=$pdo->prepare("SELECT COUNT(*) FROM candidate_applications WHERE position_id=?"); $ac->execute([$id]);
        if ((int)$vc->fetchColumn()>0) flash('Cannot delete a position that already has recorded votes.','error');
        elseif ((int)$cc->fetchColumn()>0 || (int)$ac->fetchColumn()>0) flash('Cannot delete a position with assigned candidates or applications. Remove them or deactivate the position.','error');
        else { $pdo->prepare("DELETE FROM positions WHERE position_id=?")->execute([$id]); log_action($pdo,'position_deleted','Position #'.$id); flash('Position deleted.'); }
        redirect('/voting_system/admin/positions.php');
    }
    redirect('/voting_system/admin/positions.php');
}

$edit = null;
if ($editId) { $s=$pdo->prepare("SELECT p.*, e.title AS election_title FROM positions p JOIN elections e ON e.election_id=p.election_id WHERE p.position_id=?"); $s->execute([$editId]); $edit=$s->fetch() ?: null; }
$elections = $pdo->query("SELECT election_id,title,status FROM elections ORDER BY created_at DESC")->fetchAll();
$q = trim($_GET['q'] ?? '');
$filterElection = (int)($_GET['election_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$filterCategory = trim($_GET['category'] ?? '');
$where=[]; $params=[];
if ($q !== '') { $where[]="(p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ? OR e.title LIKE ?)"; $like='%'.$q.'%'; array_push($params,$like,$like,$like,$like); }
if ($filterElection>0) { $where[]="p.election_id=?"; $params[]=$filterElection; }
if (in_array($filterStatus,['active','inactive'],true)) { $where[]="p.status=?"; $params[]=$filterStatus; }
if ($filterCategory!=='') { $where[]="p.category=?"; $params[]=$filterCategory; }
$sql="SELECT p.*, e.title AS election_title, e.status AS election_status, (SELECT COUNT(*) FROM election_candidates ec WHERE ec.position_id=p.position_id) AS candidate_count FROM positions p JOIN elections e ON e.election_id=p.election_id";
if ($where) $sql .= " WHERE ".implode(' AND ',$where);
$sql .= " ORDER BY e.created_at DESC, p.category, p.name";
$st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM positions WHERE category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$totals = $pdo->query("SELECT COUNT(*) total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) active_count, SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) inactive_count FROM positions")->fetch();
$assignedTotal = (int)$pdo->query("SELECT COUNT(*) FROM election_candidates")->fetchColumn();

page_header('Election Positions', ['subtitle' => 'Create and manage election positions, categories, and seat configuration']);
?>
<div class="page-stack">
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon blue">▣</div><div><div class="stat-value"><?= (int)($totals['total']??0) ?></div><div class="stat-label">Total positions</div></div></div>
    <div class="stat-card"><div class="stat-icon green">●</div><div><div class="stat-value"><?= (int)($totals['active_count']??0) ?></div><div class="stat-label">Active positions</div></div></div>
    <div class="stat-card"><div class="stat-icon orange">◌</div><div><div class="stat-value"><?= (int)($totals['inactive_count']??0) ?></div><div class="stat-label">Inactive positions</div></div></div>
    <div class="stat-card"><div class="stat-icon red">◉</div><div><div class="stat-value"><?= $assignedTotal ?></div><div class="stat-label">Assigned candidates</div></div></div>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2><?= $edit ? 'Edit position' : 'Add new position' ?></h2><p>Manage position details, category, eligibility, and seats.</p></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if($edit): ?><a class="btn secondary small" href="positions.php">Cancel edit</a><?php endif; ?>
      </div>
    </div>
    <form method="post" style="margin-bottom:18px;padding:14px;border:1px dashed var(--border);border-radius:12px;background:var(--surface-2)">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="load_predefined">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
        <label style="margin:0;flex:1;min-width:240px">Load predefined positions for election
          <select name="election_id" required>
            <option value="">Choose election</option>
            <?php foreach($elections as $el): ?><option value="<?=$el['election_id']?>" <?=$filterElection===(int)$el['election_id']?'selected':''?>><?=e($el['title'])?></option><?php endforeach; ?>
          </select>
        </label>
        <button class="btn secondary" type="submit">Load 13 Predefined Positions</button>
      </div>
      <div class="muted" style="font-size:12px;margin-top:8px">Adds the standard 13 positions (Executive, Finance, Academic, Welfare, Representation) if missing.</div>
    </form>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if($edit): ?><input type="hidden" name="position_id" value="<?=$edit['position_id']?>"><?php endif; ?>
      <div class="grid-2">
        <label>Select election
          <select name="election_id" required>
            <option value="">Choose election</option>
            <?php foreach($elections as $el): ?><option value="<?=$el['election_id']?>" <?= ((int)($edit['election_id']??0)===(int)$el['election_id'])?'selected':'' ?>><?=e($el['title'])?> (<?=e(ucfirst($el['status']))?>)</option><?php endforeach; ?>
          </select>
        </label>
        <label>Position name<input name="name" required maxlength="120" value="<?=e($edit['name']??'')?>" placeholder="e.g. President"></label>
      </div>
      <div class="grid-2">
        <label>Category
          <input name="category" list="category-list" value="<?=e($edit['category']??'')?>" placeholder="e.g. Executive Leadership">
          <datalist id="category-list">
            <?php foreach(array_unique(array_merge(['Executive Leadership','Finance','Academic Affairs','Student Welfare','Student Representation'],$categories)) as $cat): ?><option value="<?=e($cat)?>"><?php endforeach; ?>
          </datalist>
        </label>
        <label>Number of available seats<input type="number" name="number_of_seats" min="1" max="50" value="<?= (int)($edit['number_of_seats'] ?? $edit['max_winners'] ?? 1) ?>"></label>
      </div>
      <label>Position description<textarea name="description" placeholder="Brief description"><?=e($edit['description']??'')?></textarea></label>
      <label>Eligibility requirements<textarea name="eligibility_requirements" placeholder="Who can contest for this position"><?=e($edit['eligibility_requirements']??'')?></textarea></label>
      <div class="grid-2">
        <label>Position status
          <select name="status"><option value="active" <?= (($edit['status']??'active')==='active')?'selected':'' ?>>Active</option><option value="inactive" <?= (($edit['status']??'')==='inactive')?'selected':'' ?>>Inactive</option></select>
        </label>
        <label>Responsibilities (optional)<input name="responsibilities" value="<?=e($edit['responsibilities']??'')?>" placeholder="Key responsibilities"></label>
      </div>
      <div class="actions"><button class="btn" type="submit">Save Position</button><?php if($edit): ?><a class="btn secondary" href="positions.php">Cancel</a><?php endif; ?></div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-header"><div><h2>Positions</h2><p>Search, filter, and manage positions.</p></div></div>
    <form method="get" style="display:grid;grid-template-columns:1fr 180px 180px 160px auto;gap:10px;margin-bottom:16px">
      <input name="q" placeholder="Search position, category, description..." value="<?=e($q)?>">
      <select name="election_id"><option value="0">All elections</option><?php foreach($elections as $el): ?><option value="<?=$el['election_id']?>" <?=$filterElection===(int)$el['election_id']?'selected':''?>><?=e($el['title'])?></option><?php endforeach; ?></select>
      <select name="category"><option value="">All categories</option><?php foreach($categories as $cat): ?><option value="<?=e($cat)?>" <?=$filterCategory===$cat?'selected':''?>><?=e($cat)?></option><?php endforeach; ?></select>
      <select name="status"><option value="">All statuses</option><option value="active" <?=$filterStatus==='active'?'selected':''?>>Active</option><option value="inactive" <?=$filterStatus==='inactive'?'selected':''?>>Inactive</option></select>
      <div class="actions"><button class="btn small" type="submit">Filter</button><a class="btn secondary small" href="positions.php">Reset</a></div>
    </form>
    <div class="table-wrap" style="box-shadow:none;border:0">
      <table>
        <thead><tr><th>ID</th><th>Position</th><th>Category</th><th>Election</th><th>Candidates</th><th>Seats</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?=$r['position_id']?></td>
            <td><strong><?=e($r['name'])?></strong><div class="muted" style="font-size:12px"><?=e(mb_strimwidth((string)($r['description']??''),0,70,'…'))?></div></td>
            <td><?=e($r['category'] ?: '—')?></td>
            <td><?=e($r['election_title'])?></td>
            <td><?= (int)$r['candidate_count'] ?></td>
            <td><?= (int)($r['number_of_seats'] ?? $r['max_winners'] ?? 1) ?></td>
            <td><span class="badge <?= $r['status']==='active'?'green':'red' ?>"><?=e(ucfirst($r['status']))?></span></td>
            <td>
              <div class="table-actions">
                <a class="btn secondary small" href="position_details.php?position_id=<?=$r['position_id']?>">View</a>
                <a class="btn small" href="position_details.php?position_id=<?=$r['position_id']?>#assign-candidate">Assign</a>
                <a class="btn secondary small" href="positions.php?edit=<?=$r['position_id']?>">Edit</a>
                <form class="inline" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="position_id" value="<?=$r['position_id']?>"><button class="btn secondary small" type="submit"><?= $r['status']==='active'?'Deactivate':'Activate' ?></button></form>
                <form class="inline" method="post" onsubmit="return confirm('Delete this position? Only allowed when it has no candidates, applications, or votes.')"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="position_id" value="<?=$r['position_id']?>"><button class="btn danger small" type="submit">Delete</button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="8" class="muted">No positions found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_footer(); ?>
