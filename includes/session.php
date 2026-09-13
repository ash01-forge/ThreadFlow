<?php
/**
 * ThreadFlow - Session Configuration & Initialization
 * 
 * Configures secure session parameters before starting the session.
 * Enforces HttpOnly, SameSite, and dynamic HTTPS Secure cookies.
 */

if (!function_exists('start_secure_session')) {
    function start_secure_session(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Determine whether current connection is using HTTPS
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            // Enforce strict cookie security parameters
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');

            session_set_cookie_params([
                'lifetime' => 0, // Session cookie expires when browser is closed
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }
    }
}

start_secure_session();
