<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function colExists(PDO $pdo, string $table, string $col): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $col]);
    return (int)$s->fetchColumn() > 0;
}
function idxExists(PDO $pdo, string $table, string $idx): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");
    $s->execute([$table, $idx]);
    return (int)$s->fetchColumn() > 0;
}
function tableExists(PDO $pdo, string $table): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $s->execute([$table]);
    return (int)$s->fetchColumn() > 0;
}

$stmts = [];

// Positions module columns
if (!colExists($pdo, 'positions', 'description')) $stmts[] = "ALTER TABLE positions ADD COLUMN description TEXT NULL AFTER name";
if (!colExists($pdo, 'positions', 'responsibilities')) $stmts[] = "ALTER TABLE positions ADD COLUMN responsibilities TEXT NULL AFTER description";
if (!colExists($pdo, 'positions', 'max_winners')) $stmts[] = "ALTER TABLE positions ADD COLUMN max_winners TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER responsibilities";
if (!colExists($pdo, 'positions', 'status')) $stmts[] = "ALTER TABLE positions ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER max_winners";
if (!colExists($pdo, 'positions', 'created_at')) $stmts[] = "ALTER TABLE positions ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status";
if (!colExists($pdo, 'positions', 'category')) $stmts[] = "ALTER TABLE positions ADD COLUMN category VARCHAR(100) NULL AFTER name";
if (!colExists($pdo, 'positions', 'eligibility_requirements')) $stmts[] = "ALTER TABLE positions ADD COLUMN eligibility_requirements TEXT NULL AFTER responsibilities";
if (!colExists($pdo, 'positions', 'number_of_seats')) $stmts[] = "ALTER TABLE positions ADD COLUMN number_of_seats TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER eligibility_requirements";

if (!colExists($pdo, 'elections', 'allow_multi_position')) $stmts[] = "ALTER TABLE elections ADD COLUMN allow_multi_position TINYINT(1) NOT NULL DEFAULT 0 AFTER results_published";

foreach ($stmts as $sql) {
    echo $sql . "\n";
    $pdo->exec($sql);
}

// Sync seat fields for compatibility
$pdo->exec("UPDATE positions SET number_of_seats = max_winners WHERE (number_of_seats IS NULL OR number_of_seats = 0) AND max_winners > 0");
$pdo->exec("UPDATE positions SET max_winners = number_of_seats, max_selections = number_of_seats WHERE number_of_seats > 0");

// Candidate applications table
if (!tableExists($pdo, 'candidate_applications')) {
    echo "CREATE TABLE candidate_applications\n";
    $pdo->exec("CREATE TABLE candidate_applications (
      application_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      candidate_id BIGINT UNSIGNED NOT NULL,
      election_id BIGINT UNSIGNED NOT NULL,
      position_id BIGINT UNSIGNED NOT NULL,
      status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      notes TEXT NULL,
      reviewed_by BIGINT UNSIGNED NULL,
      reviewed_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_candidate_position_application (candidate_id, election_id, position_id),
      KEY idx_app_status (status),
      KEY idx_app_election (election_id),
      KEY idx_app_position (position_id),
      CONSTRAINT fk_app_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
      CONSTRAINT fk_app_election FOREIGN KEY (election_id) REFERENCES elections(election_id) ON DELETE CASCADE,
      CONSTRAINT fk_app_position FOREIGN KEY (position_id) REFERENCES positions(position_id) ON DELETE CASCADE,
      CONSTRAINT fk_app_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Multi-seat votes: allow multiple candidates per ballot+position
if (tableExists($pdo, 'votes') && idxExists($pdo, 'votes', 'uq_ballot_position') && !idxExists($pdo, 'votes', 'uq_ballot_position_candidate')) {
    echo "Updating votes unique key for multi-seat support\n";
    if (!idxExists($pdo, 'votes', 'idx_vote_ballot')) {
        $pdo->exec("ALTER TABLE votes ADD INDEX idx_vote_ballot (ballot_id)");
    }
    $pdo->exec("ALTER TABLE votes DROP INDEX uq_ballot_position");
    $pdo->exec("ALTER TABLE votes ADD UNIQUE KEY uq_ballot_position_candidate (ballot_id, position_id, candidate_id)");
}

echo "Migration complete\n";
