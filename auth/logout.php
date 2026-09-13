<?php
/**
 * ThreadFlow - User Logout
 * 
 * Enforces POST-only logout with CSRF token verification.
 * Disallows GET requests to protect against logout CSRF vulnerabilities.
 */
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    flash_set('error', 'Logout must be requested via a valid POST submission.');
    redirect('dashboard.php');
}

require_csrf();

auth_logout();

// Start a clean secure session to deliver the logout confirmation flash
start_secure_session();
flash_set('success', 'You have been logged out successfully.');
redirect('auth/login.php');
