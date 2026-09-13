<?php
/**
 * ThreadFlow - Reusable Alert Renderer
 * 
 * Displays flash messages from session, then clears them.
 */
require_once __DIR__ . '/helpers.php';

$flashMessages = flash_get();

if (!empty($flashMessages)): ?>
    <div class="alerts-container" aria-live="polite">
        <?php foreach ($flashMessages as $type => $messages): ?>
            <?php
            $alertClass = 'alert-info';
            if ($type === 'success') $alertClass = 'alert-success';
            elseif ($type === 'error' || $type === 'danger') $alertClass = 'alert-danger';
            elseif ($type === 'warning') $alertClass = 'alert-warning';
            ?>
            <?php foreach ($messages as $msg): ?>
                <div class="alert <?= e($alertClass) ?>" role="alert">
                    <span class="alert-message"><?= e($msg) ?></span>
                    <button type="button" class="alert-close" aria-label="Dismiss alert">&times;</button>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
