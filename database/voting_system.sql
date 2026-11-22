CREATE DATABASE IF NOT EXISTS voting_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE voting_system_db;

CREATE TABLE users (
 user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','candidate','voter') NOT NULL,
 status ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE voters (
 voter_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL UNIQUE,
 voter_reference VARCHAR(100) NOT NULL UNIQUE,
 approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 approved_by BIGINT UNSIGNED NULL,
 approved_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_voters_user FOREIGN KEY(user_id) REFERENCES users(user_id),
 CONSTRAINT fk_voters_approver FOREIGN KEY(approved_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE elections (
 election_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(200) NOT NULL,
 description TEXT NULL,
 start_at DATETIME NOT NULL,
 end_at DATETIME NOT NULL,
 status ENUM('draft','scheduled','open','closed','cancelled') NOT NULL DEFAULT 'draft',
 results_published TINYINT(1) NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_elections_creator FOREIGN KEY(created_by) REFERENCES users(user_id),
 INDEX idx_election_status_dates(status,start_at,end_at)
) ENGINE=InnoDB;

CREATE TABLE positions (
 position_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 election_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 max_selections TINYINT UNSIGNED NOT NULL DEFAULT 1,
 UNIQUE KEY uq_election_position_name(election_id,name),
 UNIQUE KEY uq_position_election(position_id,election_id),
 CONSTRAINT fk_positions_election FOREIGN KEY(election_id) REFERENCES elections(election_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE candidates (
 candidate_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL UNIQUE,
 manifesto TEXT NULL,
 profile_image VARCHAR(255) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_candidates_user FOREIGN KEY(user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE election_candidates (
 election_candidate_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 election_id BIGINT UNSIGNED NOT NULL,
 position_id BIGINT UNSIGNED NOT NULL,
 candidate_id BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_candidate_position(candidate_id,position_id),
 UNIQUE KEY uq_election_candidate(election_id,position_id,candidate_id),
 CONSTRAINT fk_ec_election FOREIGN KEY(election_id) REFERENCES elections(election_id) ON DELETE CASCADE,
 CONSTRAINT fk_ec_position FOREIGN KEY(position_id,election_id) REFERENCES positions(position_id,election_id) ON DELETE CASCADE,
 CONSTRAINT fk_ec_candidate FOREIGN KEY(candidate_id) REFERENCES candidates(candidate_id)
) ENGINE=InnoDB;

CREATE TABLE voter_participation (
 participation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 election_id BIGINT UNSIGNED NOT NULL,
 voter_id BIGINT UNSIGNED NOT NULL,
 submitted_at DATETIME NOT NULL,
 UNIQUE KEY uq_voter_once_per_election(election_id,voter_id),
 CONSTRAINT fk_participation_election FOREIGN KEY(election_id) REFERENCES elections(election_id),
 CONSTRAINT fk_participation_voter FOREIGN KEY(voter_id) REFERENCES voters(voter_id)
) ENGINE=InnoDB;

CREATE TABLE ballots (
 ballot_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 election_id BIGINT UNSIGNED NOT NULL,
 submitted_at DATETIME NOT NULL,
 CONSTRAINT fk_ballot_election FOREIGN KEY(election_id) REFERENCES elections(election_id)
) ENGINE=InnoDB;

CREATE TABLE votes (
 vote_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ballot_id BIGINT UNSIGNED NOT NULL,
 election_id BIGINT UNSIGNED NOT NULL,
 position_id BIGINT UNSIGNED NOT NULL,
 candidate_id BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_ballot_position(ballot_id,position_id),
 INDEX idx_vote_tally(election_id,position_id,candidate_id),
 CONSTRAINT fk_vote_ballot FOREIGN KEY(ballot_id) REFERENCES ballots(ballot_id),
 CONSTRAINT fk_vote_election FOREIGN KEY(election_id) REFERENCES elections(election_id),
 CONSTRAINT fk_vote_position FOREIGN KEY(position_id) REFERENCES positions(position_id),
 CONSTRAINT fk_vote_candidate FOREIGN KEY(candidate_id) REFERENCES candidates(candidate_id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id BIGINT UNSIGNED NULL,
 action VARCHAR(120) NOT NULL,
 details TEXT NULL,
 ip_address VARCHAR(45) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_audit_actor FOREIGN KEY(actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
