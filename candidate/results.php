<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate','admin');
$qs = $_GET['election_id'] ? '?election_id='.(int)$_GET['election_id'] : '';
// Reuse voter results page logic by including it in-place is tricky due to require_role/header; simple redirect is fine.
header('Location: /voting_system/voter/results.php' . $qs);
exit;