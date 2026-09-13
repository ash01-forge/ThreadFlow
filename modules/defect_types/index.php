<?php
/**
 * ThreadFlow - Defect Type Management Landing Page
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Defect Types';
$currentNav = 'defect_types';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Defect Types</h1>
        <div class="page-subtitle">Module 4 &bull; Ownership: Member 4 &bull; Access: Admin, QC Officer</div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span class="badge badge-warning">Under Development</span>
        <h2 class="mt-2">Module Status: Not Implemented Yet</h2>
    </div>
    <div class="card-body">
        <p>This module landing page is a protected placeholder shell. Defect taxonomy, severity level configuration, sewing fault categorization, and inspection defect logging will be implemented by <strong>Member 4</strong>.</p>
        
        <div class="alert alert-info">
            <strong>Teammate Note for Member 4:</strong>
            <ul style="margin-top: 0.5rem; margin-left: 1.25rem;">
                <li>Create your database table schema in <code>sql/defect_types.sql</code> using additive statements (<code>CREATE TABLE IF NOT EXISTS defect_types ...</code>).</li>
                <li>Build your views in this directory (<code>modules/defect_types/</code>).</li>
                <li>Always protect views with <code>require_role(['admin', 'qc_officer'])</code> and forms with <code>require_csrf()</code>.</li>
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
            <li>Defect Master List: Sewing faults, fabric flaws, measurement variances, stain classifications.</li>
            <li>Severity Tiers: Minor, Major, and Critical defect thresholds.</li>
            <li>Inspection Station Routing: Associate defect codes with inline QC and end-of-line checking.</li>
        </ul>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
