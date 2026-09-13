<?php
/**
 * ThreadFlow - Production Batch Management Landing Page
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Production Batches';
$currentNav = 'batches';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Production Batches</h1>
        <div class="page-subtitle">Module 3 &bull; Ownership: Member 3 &bull; Access: Admin, Production Manager</div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span class="badge badge-warning">Under Development</span>
        <h2 class="mt-2">Module Status: Not Implemented Yet</h2>
    </div>
    <div class="card-body">
        <p>This module landing page is a protected placeholder shell. Full batch lifecycle tracking (cutting, sewing lines, washing, finishing, and batch traceability) will be implemented by <strong>Member 3</strong>.</p>
        
        <div class="alert alert-info">
            <strong>Teammate Note for Member 3:</strong>
            <ul style="margin-top: 0.5rem; margin-left: 1.25rem;">
                <li>Create your database table schema in <code>sql/production_batches.sql</code> using additive statements (<code>CREATE TABLE IF NOT EXISTS production_batches ...</code>).</li>
                <li>Build your views in this directory (<code>modules/production_batches/</code>).</li>
                <li>Always protect views with <code>require_role(['admin', 'production_manager'])</code> and forms with <code>require_csrf()</code>.</li>
                <li>Reuse the shared database connection <code>$conn</code> with prepared statements.</li>
            </ul>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Planned Capabilities</h3>
    </div>
    <div class="card-body">
        <ul style="margin-left: 1.25rem;">
            <li>Batch Generation: Assign unique batch numbers to buyer purchase orders.</li>
            <li>Floor Routing: Track batches through cutting, bundling, sewing, and ironing.</li>
            <li>Line Allocation: Assign production quotas to specific floor lines and shifts.</li>
        </ul>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
