<?php
require_once __DIR__ . '/../config/database.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function colExists(PDO $pdo, $table, $col) {
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table,$col]); return (int)$s->fetchColumn()>0;
}
function idxExists(PDO $pdo, $table, $idx) {
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");
    $s->execute([$table,$idx]); return (int)$s->fetchColumn()>0;
}

$stmts = [];
if (!colExists($pdo,'positions','description')) $stmts[]="ALTER TABLE positions ADD COLUMN description TEXT NULL AFTER name";
if (!colExists($pdo,'positions','responsibilities')) $stmts[]="ALTER TABLE positions ADD COLUMN responsibilities TEXT NULL AFTER description";
if (!colExists($pdo,'positions','max_winners')) $stmts[]="ALTER TABLE positions ADD COLUMN max_winners TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER responsibilities";
if (!colExists($pdo,'positions','status')) $stmts[]="ALTER TABLE positions ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER max_winners";
if (!colExists($pdo,'positions','created_at')) $stmts[]="ALTER TABLE positions ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status";
if (!colExists($pdo,'elections','allow_multi_position')) $stmts[]="ALTER TABLE elections ADD COLUMN allow_multi_position TINYINT(1) NOT NULL DEFAULT 0 AFTER results_published";

foreach ($stmts as $sql) { echo $sql."\n"; $pdo->exec($sql); }

// Sync max_winners from max_selections where needed
$pdo->exec("UPDATE positions SET max_winners=max_selections WHERE max_winners IS NULL OR max_winners=0");
// ensure max_selections mirrors max_winners for compatibility
$pdo->exec("UPDATE positions SET max_selections=max_winners WHERE max_selections<>max_winners");

echo "Migration complete\n";
