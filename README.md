# General-Purpose Electronic Voting Management System

## Requirements
- XAMPP with Apache, PHP 8+ and MySQL/MariaDB
- A modern web browser
- Visual Studio Code (recommended)

## Installation on Windows
1. Extract `voting_system` into `C:\xampp\htdocs\`.
2. Open XAMPP Control Panel and start Apache and MySQL.
3. Open `http://localhost/phpmyadmin`.
4. Import `database/voting_system.sql` (select the SQL file and click Import).
5. Open `config/database.php` and confirm the MySQL username/password. Default XAMPP is usually user `root` with an empty password.
6. Visit `http://localhost/voting_system/setup_admin.php`.
7. Create the first administrator account.
8. Delete `setup_admin.php` immediately after successful administrator creation.
9. Sign in at `http://localhost/voting_system/login.php`.

## Suggested test workflow
1. Sign in as administrator.
2. Approve self-registered voters in Admin > Voter approvals.
3. Create an election with start and end times in the future.
4. Add positions.
5. Register candidates and assign them to positions.
6. Activate the election. The election status is calculated from its start/end dates.
7. When the election is open, sign in as an approved voter and submit a ballot.
8. Once the scheduled end time has passed, use Admin > Results to close and publish results.
9. Voters and candidates can then view published results.

## Important limitations
This is an educational prototype, not a certified election platform. Before real-world deployment, commission an independent security review and add stronger identity verification, HTTPS, rate limiting, secure hosting/backups, accessibility testing, operational monitoring, and a formally reviewed ballot-secrecy design. A database separation between participation and ballots reduces direct identity-to-choice linkage but does not guarantee anonymity against a privileged database operator, server compromise, or traffic analysis.

The prototype supports one selection per position and one ballot per voter per election. It does not implement ranked-choice voting, write-in candidates, multi-factor authentication, email verification, cryptographic end-to-end verifiability, or election observer workflows.

## Security notes
- Passwords are stored using PHP `password_hash()`.
- Database access uses PDO prepared statements.
- Session IDs are regenerated after login.
- Mutating forms use CSRF tokens.
- Role-based authorization is enforced on protected pages.
- Ballot submission uses a database transaction and a unique participation constraint.
- Remove the one-time setup script after creating the administrator.
