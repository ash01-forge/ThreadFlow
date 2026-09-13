<?php
/**
 * ThreadFlow - Buyer Management Landing Page
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

$pageTitle = 'Buyer Management';
$currentNav = 'buyers';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Buyer Management</h1>
        <div class="page-subtitle">Module 1 &bull; Ownership: Member 1 &bull; Access: Admin, Merchandiser</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/buyers/create.php')) ?>" class="btn btn-secondary">View Add Buyer Form</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span class="badge badge-warning">Under Development</span>
        <h2 class="mt-2">Module Status: Not Implemented Yet</h2>
    </div>
    <div class="card-body">
        <p>This module landing page is a protected placeholder shell. Full CRUD functionality (listing buyers, creating new buyers, editing profiles, and active status toggling) will be implemented by <strong>Member 1</strong>.</p>
        
        <div class="alert alert-info">
            <strong>Teammate Note for Member 1:</strong>
            <ul style="margin-top: 0.5rem; margin-left: 1.25rem;">
                <li>The database table <code>buyers</code> is already defined in <code>sql/buyers.sql</code>.</li>
                <li>The test form is available at <a href="<?= e(url('modules/buyers/create.php')) ?>"><code>modules/buyers/create.php</code></a>.</li>
                <li>When creating your submission handler <code>modules/buyers/store.php</code>, remember to include <code>require_role(['admin', 'merchandiser'])</code>, call <code>require_csrf()</code>, and use prepared statements with <code>$conn</code>.</li>
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
            <li>Buyer Directory: Search, filter by country, view contact details.</li>
            <li>Add New Buyer: Multi-field validation, duplicate check.</li>
            <li>Edit Buyer: Update contact person, email, phone, and address.</li>
            <li>Status Toggle: Activate or deactivate buyer accounts.</li>
        </ul>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
