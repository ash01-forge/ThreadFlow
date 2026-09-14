<?php
/**
 * ThreadFlow - Database Diagnostic Test
 * 
 * Protected diagnostic endpoint for verifying database connectivity
 * and core table integrity. Accessible to Administrator role only.
 */
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin-only access for web diagnostics
require_role('admin');

$pageTitle = 'Database Diagnostics';
$currentNav = 'diagnostics';

$dbError = null;
$buyersCount = null;
$usersCount = null;
$buyersReady = false;
$usersReady = false;

try {
    // Check buyers table
    $buyerCheck = $conn->query("SHOW TABLES LIKE 'buyers'");
    if ($buyerCheck && $buyerCheck->num_rows > 0) {
        $buyersReady = true;
        $result = $conn->query("SELECT COUNT(*) AS total FROM buyers");
        $row = $result->fetch_assoc();
        $buyersCount = (int) $row['total'];
    }

    // Check users table
    $userCheck = $conn->query("SHOW TABLES LIKE 'users'");
    if ($userCheck && $userCheck->num_rows > 0) {
        $usersReady = true;
        $uResult = $conn->query("SELECT COUNT(*) AS total FROM users");
        $uRow = $uResult->fetch_assoc();
        $usersCount = (int) $uRow['total'];
    }
} catch (mysqli_sql_exception $e) {
    $dbError = $e->getMessage();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Database Diagnostics</h1>
        <div class="page-subtitle">Administrative System Check &bull; Database: <strong><?= e(DB_NAME) ?></strong></div>
    </div>
</div>

<?php if ($dbError): ?>
    <div class="alert alert-danger">
        <strong>Database Query Error:</strong> <?= e($dbError) ?>
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <strong>Database Connection:</strong> Connected successfully to MySQL host <code><?= e(DB_HOST) ?>:<?= e((string)DB_PORT) ?></code> using charset <code>utf8mb4</code>.
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h2>Core Table Status</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Status</th>
                        <th>Record Count</th>
                        <th>Definition File</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>buyers</code></td>
                        <td>
                            <?php if ($buyersReady): ?>
                                <span class="badge badge-success">Ready</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $buyersReady ? $buyersCount . ' record(s)' : 'N/A' ?></td>
                        <td><code>sql/buyers.sql</code></td>
                    </tr>
                    <tr>
                        <td><code>users</code></td>
                        <td>
                            <?php if ($usersReady): ?>
                                <span class="badge badge-success">Ready</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Missing (Run sql/auth.sql)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $usersReady ? $usersCount . ' user(s)' : 'N/A' ?></td>
                        <td><code>sql/auth.sql</code></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Environment Summary</h3>
    </div>
    <div class="card-body">
        <ul class="list-unstyled">
            <li>PHP Version: <strong><?= e(PHP_VERSION) ?></strong></li>
            <li>MySQL Client Version: <strong><?= e($conn->client_info) ?></strong></li>
            <li>Server Info: <strong><?= e($conn->server_info) ?></strong></li>
            <li>Application Environment: <strong><?= e(APP_ENV) ?></strong></li>
            <li>Base URL: <strong><?= e(BASE_URL) ?></strong></li>
        </ul>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>