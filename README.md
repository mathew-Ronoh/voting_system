# Voting Management System (PHP + MySQL / XAMPP)

A role-based electronic voting system built with **PHP 8+**, **MySQL/MariaDB**, **Apache (XAMPP)**, PDO prepared statements, CSRF protection, and session authentication.

This guide explains how to download the project and run it on your own computer with XAMPP.

---

## 1. What you need

- **XAMPP** with Apache, PHP 8+, and MySQL/MariaDB
- A web browser
- **Git** (optional — you can also download the ZIP)

Download XAMPP: https://www.apachefriends.org/

---

## 2. Download the project from GitHub

Repository: **https://github.com/mathew-Ronoh/voting_system**

### Option A — Clone with Git (recommended)

**Windows (Command Prompt or Git Bash):**
```bat
cd C:\xampp\htdocs
git clone https://github.com/mathew-Ronoh/voting_system.git
```

**Linux (XAMPP installed in /opt/lampp):**
```bash
cd /opt/lampp/htdocs
git clone https://github.com/mathew-Ronoh/voting_system.git
```

### Option B — Download ZIP (no Git needed)

1. Open https://github.com/mathew-Ronoh/voting_system
2. Click **Code > Download ZIP**
3. Extract the ZIP into your XAMPP web root:
   - **Windows:** `C:\xampp\htdocs\`
   - **Linux:** `/opt/lampp/htdocs/`

After extraction, make sure the folder is named `voting_system` so the URL is:
```
http://localhost/voting_system/
```
If the extracted folder has a name like `voting_system-main`, rename it to `voting_system`.

---

## 3. Start Apache and MySQL

### Windows
1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**

### Linux
```bash
sudo /opt/lampp/lampp startapache
sudo /opt/lampp/lampp startmysql
```

Check status:
```bash
sudo /opt/lampp/lampp status
```

You should see **Apache is running** and **MySQL is running**.

---

## 4. Create the database

You can do this with **phpMyAdmin** (easiest) or the command line.

### Option A — phpMyAdmin (recommended for beginners)

1. Open **http://localhost/phpmyadmin/**
2. Log in with:
   - **Username:** `root`
   - **Password:** leave empty (default XAMPP)
3. Click **New** in the left sidebar.
4. Database name: `voting_system_db`
5. Collation: `utf8mb4_unicode_ci`
6. Click **Create**.
7. Select `voting_system_db`, open the **Import** tab.
8. Choose the file:
   ```
   database/voting_system.sql
   ```
9. Click **Import**.
10. Open the **SQL** tab and run the migration file contents by importing:
    ```
    database/migrate_positions_module.php
    ```
    Note: phpMyAdmin imports `.sql` files, not `.php`. So for the migration, use the command line below (Option B), or ask a teammate to run it once.

> The migration adds the positions module columns/tables and compatibility fixes. It is safe to run more than once.

### Option B — Command line (Windows)

Open **XAMPP Shell** or Command Prompt and run:
```bat
cd C:\xampp\htdocs\voting_system
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS voting_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root voting_system_db < database\voting_system.sql
C:\xampp\php\php.exe database\migrate_positions_module.php
```

### Option B — Command line (Linux)

```bash
cd /opt/lampp/htdocs/voting_system
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS voting_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
/opt/lampp/bin/mysql -u root voting_system_db < database/voting_system.sql
/opt/lampp/bin/php database/migrate_positions_module.php
```

---

## 5. Configure the database connection

The app defaults to standard XAMPP settings:

| Setting | Default |
|---|---|
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `voting_system_db` |
| User | `root` |
| Password | empty |

If your XAMPP MySQL uses a different password/port, create a local override:

```bash
cp config/database.example.php config/database.local.php
```

Edit `config/database.local.php`:
```php
<?php
$host = '127.0.0.1';
$db   = 'voting_system_db';
$user = 'root';
$pass = ''; // your password
$charset = 'utf8mb4';
```

`config/database.local.php` is gitignored, so your credentials are not committed.

---

## 6. Create the first administrator

1. Open:
   ```
   http://localhost/voting_system/setup_admin.php
   ```
2. Enter your name, email, and a password (minimum 4 characters).
3. Click **Create administrator**.
4. **Delete `setup_admin.php` immediately** after creating the admin.

Then sign in at:
```
http://localhost/voting_system/login.php
```

---

## 7. Quick start after login (Admin)

1. Go to **Election Management** and create an election.
2. Click **Manage Positions** or **Load 13 Positions** to add positions.
3. Go to **Candidate Management** to register/assign candidates.
4. Approve voter registrations under **Voter Approvals**.
5. Activate the election when ready.
6. Voters log in, view candidates grouped by category, and cast ballots.
7. Publish results only after the election ends.

### Default predefined positions
Admin can load these from **Admin > Election Positions** or **Admin > Election Management > Load 13 Positions**:

- **Executive Leadership:** President, Vice President, Secretary General, Deputy Secretary General
- **Finance:** Treasurer
- **Academic Affairs:** Academic Secretary
- **Student Welfare:** Organizing Secretary, Social Welfare Secretary
- **Student Representation:** Men's Representative, Women's Representative, Sports Representative, Disability Representative, Year Representative

---

## 8. URLs

| Page | URL |
|---|---|
| App | http://localhost/voting_system/ |
| Login | http://localhost/voting_system/login.php |
| Admin setup (one-time) | http://localhost/voting_system/setup_admin.php |
| phpMyAdmin | http://localhost/phpmyadmin/ |

---

## 9. Common problems

### “Database connection failed”
- Make sure **MySQL is running** in XAMPP.
- Check `config/database.php` or `config/database.local.php`.
- Confirm the database `voting_system_db` exists.

### “Unknown column …” or HTTP 500
Run the migration:
```bash
/opt/lampp/bin/php database/migrate_positions_module.php
```
Windows:
```bat
C:\xampp\php\php.exe database\migrate_positions_module.php
```

### MySQL won’t start / port 3306 already in use
Another MySQL/MariaDB service is using port 3306:
```bash
sudo ss -ltnp | grep 3306
```
Stop the other service, then start XAMPP MySQL:
```bash
sudo systemctl stop mariadb
sudo /opt/lampp/lampp startmysql
```

### phpMyAdmin shows an empty table
An empty result is not an error. Check the correct table:
- Voters are in `users` + `voters`.
- Candidates are in `users` + `candidates`.
- Candidate-to-position assignments are in `election_candidates`.
- Votes are in `votes`.

### XAMPP MySQL system-table warnings
If logs mention `mysql.event` / `innodb_table_stats`, back up first, then:
```bash
sudo /opt/lampp/bin/mysql_upgrade -u root
sudo /opt/lampp/lampp restartmysql
```

---

## 10. Security notes

- Passwords are hashed with PHP `password_hash()`.
- Database access uses PDO prepared statements.
- Session IDs are regenerated after login.
- CSRF tokens protect mutating forms.
- Role-based access control is enforced on the backend.
- Ballot submission uses a database transaction and participation constraints.
- Remove `setup_admin.php` after creating the administrator.

---

## 11. Important limitations

This is an educational prototype, not a certified election platform. Before real-world deployment, commission an independent security review and add stronger identity verification, HTTPS, rate limiting, secure hosting/backups, accessibility testing, monitoring, and a formally reviewed ballot-secrecy design.
