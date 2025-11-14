<?php
/**
 * Final Space License Server — Legacy Compatibility Layer
 * Allows old admin pages (ls_* functions) to keep working under new fs_* core.
 */

if (!function_exists('ls_start')) {
    function ls_start() { if (function_exists('fs_start')) return fs_start(); }
}

if (!function_exists('ls_require_admin')) {
    function ls_require_admin() { if (function_exists('fs_require_admin')) return fs_require_admin(); }
}

if (!function_exists('ls_logout')) {
    function ls_logout() { if (function_exists('fs_logout')) return fs_logout(); }
}

if (!function_exists('ls_is_logged')) {
    function ls_is_logged() { if (function_exists('fs_is_logged')) return fs_is_logged(); }
}
