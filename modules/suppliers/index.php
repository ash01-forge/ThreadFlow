<?php
/**
 * ThreadFlow - Supplier Management Landing Page
 * 
 * Module 2: Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Supplier Management';
$currentNav = 'suppliers';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Supplier Management</h1>
        <div class="page-subtitle">Module 2 &bull; Ownership: Member 2 &bull; Access: Admin, Production Manager</div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span class="badge badge-warning">Under Development</span>
        <h2 class="mt-2">Module Status: Not Implemented Yet</h2>
    </div>
    <div class="card-body">
        <p>This module landing page is a protected placeholder shell. Full CRUD functionality (managing fabric suppliers, yarn mills, trim vendors, lead times, and ratings) will be implemented by <strong>Member 2</strong>.</p>
        
        <div class="alert alert-info">
            <strong>Teammate Note for Member 2:</strong>
            <ul style="margin-top: 0.5rem; margin-left: 1.25rem;">
                <li>Create your database table schema in <code>sql/suppliers.sql</code> using additive statements (<code>CREATE TABLE IF NOT EXISTS suppliers ...</code>).</li>
                <li>Build your views in this directory (<code>modules/suppliers/</code>).</li>
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
            <li>Supplier Profiles: Contact names, addresses, materials supplied, certifications.</li>
            <li>Vendor Evaluation: Lead time ratings, on-time delivery percentages.</li>
            <li>Material Sourcing Catalog: Linking suppliers to yarn, fabrics, buttons, and zippers.</li>
        </ul>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
