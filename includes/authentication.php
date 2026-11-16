<?php
// Central auth, session and CSRF helpers.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}

define('SESSION_IDLE_TIMEOUT', 1800); // 30 minutes

function requireLogin() {
    if (isset($_SESSION['user_id'], $_SESSION['last_activity'])
        && time() - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        header("Location: ../login.php?expired=1");
        exit();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// Accepts one or more roles. Admins may also open staff pages.
function requireRole(...$roles) {
    requireLogin();
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin' && in_array('staff', $roles, true)) {
        return;
    }
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        exit("Access denied.");
    }
}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Use csrfField() inside any POST form to output the hidden token input.
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="'
        . escape(generateCsrfToken()) . '">';
}
