<?php
/**
 * Final Space License Server — Security Helper v7.2 FINAL
 * Compatible with PHP 8.2 and flat $CFG config
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/* ---------- LOGIN ---------- */
function fs_login(string $user, string $pass): bool {
    global $CFG;

    $cfgUser = $CFG['admin_user'] ?? 'admin';
    $cfgHash = $CFG['admin_pass_hash'] ?? '';
    $cfgPlain = $CFG['admin_pass'] ?? '';

    if ($user !== $cfgUser) return false;

    $ok = false;
    if ($cfgHash) {
        $ok = password_verify($pass, $cfgHash);
    } elseif ($cfgPlain) {
        $ok = hash_equals($cfgPlain, $pass);
    }

    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['admin_logged'] = true;
    }

    return $ok;
}

/* ---------- ACCESS CONTROL ---------- */
function fs_require_admin(): void {
    if (empty($_SESSION['admin_logged'])) {
        header('Location: login.php');
        exit;
    }
}

/* ---------- LOGOUT ---------- */
function fs_logout(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}

/* ---------- STATUS ---------- */
function fs_is_logged(): bool {
    return !empty($_SESSION['admin_logged']);
}

/* ---------- LEGACY MAPPERS ---------- */
if (!function_exists('ls_login')) {
    function ls_login($u, $p) { return fs_login($u, $p); }
}
if (!function_exists('ls_require_admin')) {
    function ls_require_admin() { return fs_require_admin(); }
}
