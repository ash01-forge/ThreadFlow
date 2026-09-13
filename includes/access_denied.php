<?php
/**
 * ThreadFlow - Access Denied (HTTP 403) Page
 */
require_once __DIR__ . '/helpers.php';

$currentUser = $_SESSION['user'] ?? null;
$userRole = $currentUser['role'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access Denied | ThreadFlow</title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="page-denied">
    <div class="denied-container">
        <div class="card denied-card">
            <div class="card-header text-center">
                <span class="badge badge-danger">403 Forbidden</span>
                <h1 class="denied-title">Access Denied</h1>
            </div>
            <div class="card-body text-center">
                <p>Your current account role (<strong><?= e(ucwords(str_replace('_', ' ', $userRole))) ?></strong>) does not have permission to access this resource or perform this action.</p>
                <p class="text-muted text-sm">If you believe this is an error, please contact your system administrator to adjust your role assignments.</p>
                <div class="actions mt-4">
                    <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-primary">Return to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
