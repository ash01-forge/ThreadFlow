<?php
/**
 * ThreadFlow - Root Entry Point
 * 
 * Routes authenticated requests to the Dashboard and unauthenticated
 * requests to the Login screen.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('auth/login.php');
}