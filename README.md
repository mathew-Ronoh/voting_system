# Voting Management System (PHP/MySQL)

A role-based electronic voting system built with PHP 8+, MySQL/MariaDB, Apache (XAMPP), PDO prepared statements, CSRF protection, and session auth.

## Features
- **Roles:** Admin, Candidate, Voter (RBAC enforced on backend)
- **Admin:** elections, positions, candidates, voters, eligibility rules, monitoring, reports/audit, settings
- **Election Positions Module:** 13 predefined positions by category, CRUD/search/filter, activate/deactivate, seats, eligibility, candidate assignment, candidate application review
- **Voting:** active positions grouped by category, one/multi-seat rules, review/confirm, duplicate-vote prevention, ballot reference, results hidden until published
- **UI:** IEBC-inspired theme

## Team Setup (XAMPP Apache + MySQL)

### 1. Prerequisites
- XAMPP (Apache + MySQL/MariaDB) with PHP 8+
- Git

### 2. Clone project
Clone into your XAMPP web root:

**Linux (XAMPP):**
```bash
cd /opt/lampp/htdocs
git clone https://github.com/mathew-Ronoh/voting_system.git
```

**Windows (XAMPP):**
```bat
cd C:\xampp\htdocs
git clone https://github.com/mathew-Ronoh/voting_system.git
```

### 3. Configure database (local, not committed)
Copy the example config and adjust if needed:

```bash
cp config/database.example.php config/database.local.php
```

Default XAMPP settings work as-is:
- Host: `127.0.0.1`
- DB: `voting_system_db`
- User: `root`
- Password: `` (empty)

`config/database.local.php` is gitignored so each teammate can keep local credentials.

### 4. Create and import database
Start Apache and MySQL from XAMPP, then:

**Linux:**
```bash
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS voting_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
/opt/lampp/bin/mysql -u root voting_system_db < database/voting_system.sql
/opt/lampp/bin/php database/migrate_positions_module.php
```

**Windows (XAMPP shell/phpMyAdmin):**
```bat
mysql -u root -e "CREATE DATABASE IF NOT EXISTS voting_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root voting_system_db < database\voting_system.sql
php database\migrate_positions_module.php
```

You can also import `database/voting_system.sql` via phpMyAdmin: http://localhost/phpmyadmin/

### 5. Start servers
**Linux:**
```bash
sudo /opt/lampp/lampp startapache
sudo /opt/lampp/lampp startmysql
```

**Windows:** start Apache and MySQL in XAMPP Control Panel.

### 6. Open the app
- App: http://localhost/voting_system/
- Login: http://localhost/voting_system/login.php

If you need an initial admin account, use the existing setup flow only in a trusted local environment and remove any one-time setup script after use.

## Default Predefined Election Positions
Admin can load these from **Admin > Election Positions > Load 13 Predefined Positions**:

- **Executive Leadership:** President, Vice President, Secretary General, Deputy Secretary General
- **Finance:** Treasurer
- **Academic Affairs:** Academic Secretary
- **Student Welfare:** Organizing Secretary, Social Welfare Secretary
- **Student Representation:** Men's Representative, Women's Representative, Sports Representative, Disability Representative, Year Representative

## Common XAMPP issues

### Port 3306 already in use
If system MariaDB/MySQL is using 3306, XAMPP MySQL will fail to start. Check:
```bash
sudo ss -ltnp | grep 3306
sudo /opt/lampp/lampp status
```
Either stop the system service:
```bash
sudo systemctl stop mariadb
sudo /opt/lampp/lampp startmysql
```
Or configure XAMPP MySQL to use a different port and update `config/database.local.php`.

### XAMPP MySQL system-table warnings
If logs mention `mysql.event` / `innodb_table_stats` and suggest `mysql_upgrade`, back up first, then:
```bash
sudo /opt/lampp/bin/mysql_upgrade -u root
sudo /opt/lampp/lampp restartmysql
```

## Security notes
- Passwords are hashed with PHP `password_hash()`.
- DB access uses PDO prepared statements.
- Session IDs regenerated after login.
- CSRF tokens on mutating forms.
- RBAC enforced on backend.
- Ballot submission uses DB transaction and participation constraints.

## Important limitations
This is an educational prototype, not a certified election platform. Before real-world deployment, commission an independent security review and add stronger identity verification, HTTPS, rate limiting, secure hosting/backups, accessibility testing, monitoring, and a formally reviewed ballot-secrecy design.