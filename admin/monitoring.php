<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_role('admin');
$statusFilter = $_GET['status'] ?? '';
$q = $_GET['q'] ?? '';
$where = []; $params = [];
if ($statusFilter !== '' && in_array($statusFilter,['draft','scheduled','open','closed','published','cancelled'],true)) { $where[]='e.status=?'; $params[]=$statusFilter; }
if ($q !== '') { $where[]='e.title LIKE ?'; $params[]='%'.$q.'%'; }
$sql = "SELECT e.election_id,e.title,e.status,e.start_at,e.end_at,e.results_published,
  (SELECT COUNT(*) FROM voter_participation vp WHERE vp.election_id=e.election_id) AS participated
  FROM elections e";
if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
$sql .= ' ORDER BY e.start_at DESC';
$st=$pdo->prepare($sql); $st->execute($params); $elections=$st->fetchAll();
page_header('Voting Monitoring', ['subtitle'=>'Monitor election participation and eligibility']);
?>
<div class="page-stack">
  <div class="panel">
    <div class="panel-header"><div><h2>Election monitoring</h2><p>Participation vs eligible voters. If per-election eligibility rules exist, eligible count uses them; otherwise all approved voters.</p></div></div>
    <form method="get" class="actions" style="margin-bottom:16px">
      <input type="text" name="q" value="<?=e($q)?>" placeholder="Search election title" style="max-width:260px">
      <select name="status" style="max-width:200px">
        <option value="">All statuses</option>
        <?php foreach(['draft','scheduled','open','closed','published','cancelled'] as $s): ?><option value="<?=$s?>" <?= $statusFilter===$s?'selected':'' ?>><?=ucfirst($s)?></option><?php endforeach; ?>
      </select>
      <button class="btn small">Filter</button>
      <a class="btn secondary small" href="monitoring.php">Reset</a>
    </form>
    <div class="table-wrap" style="box-shadow:none;border:0"><table>
      <thead><tr><th>Election</th><th>Status</th><th>Eligible</th><th>Participated</th><th>Turnout</th><th>Rule</th></tr></thead>
      <tbody>
      <?php foreach($elections as $e): $eligible=eligible_voter_count_for_election($pdo,(int)$e['election_id']); $p=(int)$e['participated']; $den=max(1,$eligible); $pct=($p/$den)*100; $hasRule=election_has_eligibility_rules($pdo,(int)$e['election_id']); ?>
        <tr>
          <td><strong><?=e($e['title'])?></strong><div class="muted" style="font-size:12px"><?=e(date('M j, Y g:i A', strtotime($e['start_at'])))?></div></td>
          <td><span class="badge <?= match($e['status']){'open'=>'green','scheduled'=>'orange','closed'=>'red','draft'=>'gray','published'=>'blue','cancelled'=>'red',default=>'gray'} ?>"><?=e(ucfirst($e['status']))?></span></td>
          <td><?=$eligible?></td>
          <td><?=$p?></td>
          <td style="min-width:180px"><div style="display:flex;align-items:center;gap:8px"><div style="flex:1;background:#e5eaf2;border-radius:999px;height:10px;overflow:hidden"><div style="width:<?=min(100,$pct)?>%;height:100%;background:#2563eb"></div></div><span class="muted" style="font-size:12px"><?=number_format($pct,1)?>%</span></div></td>
          <td><span class="badge <?= $hasRule?'blue':'gray' ?>"><?= $hasRule?'Custom':'All approved' ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$elections): ?><tr><td colspan="6" class="muted">No elections found.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php page_footer(); ?>