<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Keep scheduled/open election states aligned with their configured dates.
$pdo->exec("UPDATE elections SET status = CASE
    WHEN NOW() < start_at THEN 'scheduled'
    WHEN NOW() BETWEEN start_at AND end_at THEN 'open'
    ELSE 'closed'
END WHERE status IN ('scheduled','open')");

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403); exit('Invalid request token. Refresh and try again.');
    }
}
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function current_user_id(): int { return (int)($_SESSION['user_id'] ?? 0); }
function current_role(): string { return (string)($_SESSION['role'] ?? ''); }
function log_action(PDO $pdo, string $action, ?string $details = null): void {
    try {
        $s = $pdo->prepare("INSERT INTO audit_logs(actor_user_id,action,details,ip_address) VALUES(?,?,?,?)");
        $s->execute([current_user_id() ?: null, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { /* do not break user flow */ }
}
function notify_user(PDO $pdo, int $userId, string $title, string $message): void {
    try {
        $s = $pdo->prepare("INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)");
        $s->execute([$userId, $title, $message]);
    } catch (Throwable $e) { }
}
function unread_notifications_count(PDO $pdo, int $userId): int {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $s->execute([$userId]);
        return (int)$s->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
function confirmation_reference(): string {
    return 'EV-' . strtoupper(bin2hex(random_bytes(5)));
}
function get_voter(PDO $pdo, int $userId): ?array {
    $s = $pdo->prepare("SELECT * FROM voters WHERE user_id=? LIMIT 1");
    $s->execute([$userId]);
    $r = $s->fetch();
    return $r ?: null;
}
function election_has_eligibility_rules(PDO $pdo, int $electionId): bool {
    $s=$pdo->prepare("SELECT COUNT(*) FROM election_eligibility WHERE election_id=?");
    $s->execute([$electionId]);
    return (int)$s->fetchColumn() > 0;
}
function is_voter_eligible_for_election(PDO $pdo, int $electionId, int $voterId): bool {
    if (election_has_eligibility_rules($pdo, $electionId)) {
        $s=$pdo->prepare("SELECT COUNT(*) FROM election_eligibility WHERE election_id=? AND voter_id=?");
        $s->execute([$electionId,$voterId]);
        return (int)$s->fetchColumn() > 0;
    }
    $s=$pdo->prepare("SELECT COUNT(*) FROM voters WHERE voter_id=? AND approval_status='approved'");
    $s->execute([$voterId]);
    return (int)$s->fetchColumn() > 0;
}
function eligible_voter_count_for_election(PDO $pdo, int $electionId): int {
    if (election_has_eligibility_rules($pdo, $electionId)) {
        $s=$pdo->prepare("SELECT COUNT(*) FROM election_eligibility ee JOIN voters v ON v.voter_id=ee.voter_id WHERE ee.election_id=? AND v.approval_status='approved'");
        $s->execute([$electionId]);
        return (int)$s->fetchColumn();
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE approval_status='approved'")->fetchColumn();
}
function require_login(): void {
    if (!is_logged_in()) { header('Location: /voting_system/login.php'); exit; }
}
function require_role(string ...$roles): void {
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403); exit('You are not authorized to access this page.');
    }
}
function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
function redirect(string $path): never {
    header('Location: ' . $path); exit;
}
function role_home(string $role): string {
    return match ($role) {
        'admin' => '/voting_system/admin/dashboard.php',
        'candidate' => '/voting_system/candidate/dashboard.php',
        'voter' => '/voting_system/voter/dashboard.php',
        default => '/voting_system/index.php',
    };
}
function nav_items_for_role(string $role): array {
    $base = '/voting_system';
    return match ($role) {
        'admin' => [
            ['label' => 'Dashboard', 'href' => "$base/admin/dashboard.php", 'icon' => 'grid'],
            ['label' => 'Election Management', 'href' => "$base/admin/elections.php", 'icon' => 'calendar'],
            ['label' => 'Election Positions', 'href' => "$base/admin/positions.php", 'icon' => 'list'],
            ['label' => 'Candidate Management', 'href' => "$base/admin/candidates.php", 'icon' => 'users'],
            ['label' => 'Voter Management', 'href' => "$base/admin/voters.php", 'icon' => 'user-check'],
            ['label' => 'Voter Approvals', 'href' => "$base/admin/voters.php?status=pending", 'icon' => 'user-check'],
            ['label' => 'Voting Monitoring', 'href' => "$base/admin/monitoring.php", 'icon' => 'activity'],
            ['label' => 'Election Results', 'href' => "$base/admin/results.php", 'icon' => 'chart'],
            ['label' => 'Reports & Analytics', 'href' => "$base/admin/reports.php", 'icon' => 'report'],
            ['label' => 'Audit Logs', 'href' => "$base/admin/audit_logs.php", 'icon' => 'shield'],
            ['label' => 'System Settings', 'href' => "$base/admin/settings.php", 'icon' => 'settings'],
        ],
        'candidate' => [
            ['label' => 'Dashboard', 'href' => "$base/candidate/dashboard.php", 'icon' => 'grid'],
            ['label' => 'My Profile', 'href' => "$base/candidate/profile.php", 'icon' => 'user'],
            ['label' => 'Apply for Position', 'href' => "$base/candidate/apply.php", 'icon' => 'file'],
            ['label' => 'My Elections', 'href' => "$base/candidate/elections.php", 'icon' => 'calendar'],
            ['label' => 'My Manifesto', 'href' => "$base/candidate/manifesto.php", 'icon' => 'file'],
            ['label' => 'Published Results', 'href' => "$base/candidate/results.php", 'icon' => 'chart'],
            ['label' => 'Notifications', 'href' => "$base/candidate/notifications.php", 'icon' => 'bell'],
        ],
        'voter' => [
            ['label' => 'Dashboard', 'href' => "$base/voter/dashboard.php", 'icon' => 'grid'],
            ['label' => 'Available Elections', 'href' => "$base/voter/elections.php", 'icon' => 'calendar'],
            ['label' => 'My Voting History', 'href' => "$base/voter/history.php", 'icon' => 'history'],
            ['label' => 'Election Results', 'href' => "$base/voter/results.php", 'icon' => 'chart'],
            ['label' => 'Notifications', 'href' => "$base/voter/notifications.php", 'icon' => 'bell'],
            ['label' => 'My Profile', 'href' => "$base/voter/profile.php", 'icon' => 'user'],
        ],
        default => [],
    };
}
function nav_icon(string $name): string {
    $icons = [
        'grid' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
        'list' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'user-check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="m17 11 2 2 4-4"/></svg>',
        'activity' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
        'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-6"/></svg>',
        'report' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M9 18h4"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 0 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 0 1-4 0v-.1a1 1 0 0 0-.7-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 0 1 0-4h.1a1 1 0 0 0 .9-.7 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 0 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2h.1a1 1 0 0 0 .6-.9V4a2 2 0 0 1 4 0v.1a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 0 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1v.1a1 1 0 0 0 .9.6H20a2 2 0 0 1 0 4h-.1a1 1 0 0 0-.9.6z"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'file' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 7-3 7h18s-3 0-3-7"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>',
        'history' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>',
    ];
    return $icons[$name] ?? $icons['grid'];
}
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
    $last = strtoupper(substr($parts[count($parts)-1] ?? '', 0, 1));
    return $first . ($last !== $first ? $last : '');
}
function page_header(string $title, array $opts = []): void {
    $name = $_SESSION['full_name'] ?? 'Guest';
    $role = current_role();
    $authed = is_logged_in() && $role !== '';
    $layout = $opts['layout'] ?? ($authed ? 'app' : 'public');
    $subtitle = $opts['subtitle'] ?? '';
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' | EVoting</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="/voting_system/assets/css/style.css">';
    echo '</head><body>';

    if ($layout === 'app' && $authed) {
        $items = nav_items_for_role($role);
        echo '<div class="app-shell">';
        echo '<div class="sidebar-overlay" id="sidebarOverlay"></div>';
        echo '<aside class="sidebar" id="sidebar">';
        echo '<div class="sidebar-brand"><div class="brand-mark">EV</div><div><div class="brand-name">EVoting</div><span class="brand-sub">Election Management</span></div></div>';
        echo '<nav class="sidebar-nav"><div class="nav-section">Navigation</div>';
        foreach ($items as $item) {
            $active = ($current === parse_url($item['href'], PHP_URL_PATH)) ? ' active' : '';
            echo '<a class="nav-link' . $active . '" href="' . e($item['href']) . '"><span class="nav-icon">' . nav_icon($item['icon']) . '</span><span>' . e($item['label']) . '</span></a>';
        }
        echo '</nav>';
        echo '<div class="sidebar-footer"><a class="nav-link" href="/voting_system/logout.php"><span class="nav-icon">' . nav_icon('logout') . '</span><span>Logout</span></a></div>';
        echo '</aside>';
        echo '<div class="main-wrap">';
        echo '<header class="topbar"><div class="topbar-left">';
        echo '<button class="menu-toggle" id="menuToggle" aria-label="Open menu"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>';
        echo '<div class="page-title-wrap"><div class="page-title">' . e($title) . '</div>';
        if ($subtitle !== '') echo '<div class="page-subtitle">' . e($subtitle) . '</div>';
        echo '</div></div>';
        echo '<div class="topbar-right">';
        global $pdo;
        $unread = 0;
        try { $unread = unread_notifications_count($pdo, current_user_id()); } catch (Throwable $e) {}
        $notifHref = $role==='admin' ? '/voting_system/admin/dashboard.php' : ($role==='candidate' ? '/voting_system/candidate/notifications.php' : '/voting_system/voter/notifications.php');
        echo '<a class="icon-btn" href="'.e($notifHref).'" aria-label="Notifications">' . nav_icon('bell') . ($unread>0?'<span class="dot"></span>':'') . '</a>';
        echo '<div class="user-chip"><div class="avatar">' . e(initials($name)) . '</div><div class="user-meta"><span class="name">' . e($name) . '</span><span class="role">' . e($role) . '</span></div></div>';
        echo '</div></header>';
        echo '<main class="content">';
    } else {
        echo '<div class="public-shell">';
        echo '<header class="public-topbar"><a class="brand" href="/voting_system/index.php"><div class="brand-mark">EV</div><span>EVoting</span></a><nav>';
        if ($authed) {
            echo '<a class="btn secondary small" href="' . e(role_home($role)) . '">Dashboard</a>';
            echo '<a class="btn small" href="/voting_system/logout.php">Logout</a>';
        } else {
            echo '<a class="btn secondary small" href="/voting_system/login.php">Sign in</a>';
            echo '<a class="btn small" href="/voting_system/register.php">Register</a>';
        }
        echo '</nav></header><main class="public-main"><div class="container">';
    }

    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash']; unset($_SESSION['flash']);
        echo '<div class="alert ' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
}

function page_footer(array $opts = []): void {
    $role = current_role();
    $authed = is_logged_in() && $role !== '';
    $layout = $opts['layout'] ?? ($authed ? 'app' : 'public');
    if ($layout === 'app' && $authed) {
        echo '</main></div></div>';
    } else {
        echo '</div></main><footer class="public-footer">Electronic Voting Management System &middot; PHP &amp; MySQL</footer></div>';
    }
    echo '<script>(function(){var b=document.getElementById("menuToggle"),s=document.getElementById("sidebar"),o=document.getElementById("sidebarOverlay");if(!b||!s||!o)return;function c(){s.classList.remove("open");o.classList.remove("open")}b.addEventListener("click",function(){s.classList.add("open");o.classList.add("open")});o.addEventListener("click",c);document.addEventListener("keydown",function(e){if(e.key==="Escape")c()});})();</script>';
    echo '</body></html>';
}