<?php
/**
 * ThreadFlow - Reusable Core Helpers
 * 
 * Contains HTML escaping, URL generation, redirects, flash messaging,
 * CSRF protection, and server-side validation helpers.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/session.php';

/**
 * Escapes HTML output safely to prevent XSS attacks.
 *
 * @param mixed $string
 * @return string
 */
function e($string): string {
    return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generates an application-relative URL using the configured BASE_URL.
 *
 * @param string $path
 * @return string
 */
function url(string $path = ''): string {
    $trimmedPath = ltrim($path, '/');
    if ($trimmedPath === '') {
        return BASE_URL !== '' ? BASE_URL : '/';
    }
    return BASE_URL . '/' . $trimmedPath;
}

/**
 * Generates a URL for a static asset located in the assets/ directory.
 *
 * @param string $path
 * @return string
 */
function asset(string $path = ''): string {
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Redirects the browser to a given URL or internal path and terminates script.
 *
 * @param string $path
 * @return void
 */
function redirect(string $path): void {
    // If it is not a full external URL, resolve it with url()
    if (!preg_match('/^https?:\/\//i', $path)) {
        $target = url($path);
    } else {
        $target = $path;
    }

    header("Location: " . $target);
    exit;
}

/**
 * Sets a flash message to be displayed on the next page load.
 *
 * @param string $type ('success', 'error', 'warning', 'info')
 * @param string $message
 * @return void
 */
function flash_set(string $type, string $message): void {
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }
    if (!isset($_SESSION['_flash'][$type])) {
        $_SESSION['_flash'][$type] = [];
    }
    $_SESSION['_flash'][$type][] = $message;
}

/**
 * Retrieves flash messages and clears them from the session.
 *
 * @param string|null $type Specific type, or null for all types
 * @return array
 */
function flash_get(?string $type = null): array {
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return [];
    }

    if ($type !== null) {
        $messages = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $messages;
    }

    $messages = $_SESSION['_flash'];
    $_SESSION['_flash'] = [];
    return $messages;
}

/**
 * Checks if there are any flash messages stored.
 *
 * @param string|null $type
 * @return bool
 */
function flash_has(?string $type = null): bool {
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return false;
    }

    if ($type !== null) {
        return !empty($_SESSION['_flash'][$type]);
    }

    return !empty($_SESSION['_flash']);
}

/**
 * Returns or generates a cryptographically secure CSRF token for the current session.
 *
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Generates an HTML hidden input field containing the CSRF token.
 *
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verifies a submitted CSRF token using timing-attack safe comparison.
 *
 * @param string|null $token
 * @return bool
 */
function csrf_verify(?string $token = null): bool {
    $sessionToken = $_SESSION['_csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }

    $submittedToken = $token ?? $_POST['csrf_token'] ?? '';
    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Enforces CSRF token validation on POST requests.
 * Halts with HTTP 403 if invalid.
 *
 * @return void
 */
function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify()) {
            http_response_code(403);
            exit("<!DOCTYPE html><html><head><title>403 Forbidden</title><style>body{font-family:system-ui,sans-serif;background:#FCEBED;color:#1F1415;padding:40px;text-align:center;}.box{background:#fff;max-width:480px;margin:0 auto;padding:24px;border:1px solid #E5C3C7;border-radius:6px;}h2{color:#78000F;margin-top:0;}</style></head><body><div class='box'><h2>403 - Invalid or Expired Security Token</h2><p>Your session or form security token has expired. Please go back, refresh the page, and try again.</p><p><a href='" . e(url('dashboard.php')) . "'>Return to Dashboard</a></p></div></body></html>");
        }
    }
}

/**
 * Validates that a string value is not empty after trimming.
 *
 * @param mixed $value
 * @param string $fieldLabel
 * @param array $errors Output error array
 * @param string $fieldKey
 * @return string Sanitized trimmed string
 */
function validate_required($value, string $fieldLabel, array &$errors, string $fieldKey): string {
    $clean = trim((string) $value);
    if ($clean === '') {
        $errors[$fieldKey] = "{$fieldLabel} is required.";
    }
    return $clean;
}

/**
 * Validates that a string is a valid email format.
 *
 * @param mixed $value
 * @param string $fieldLabel
 * @param array $errors
 * @param string $fieldKey
 * @return string Sanitized email
 */
function validate_email($value, string $fieldLabel, array &$errors, string $fieldKey): string {
    $clean = trim((string) $value);
    if ($clean !== '' && !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
        $errors[$fieldKey] = "{$fieldLabel} must be a valid email address.";
    }
    return $clean;
}

/**
 * Validates maximum string length.
 *
 * @param mixed $value
 * @param int $maxLength
 * @param string $fieldLabel
 * @param array $errors
 * @param string $fieldKey
 * @return string
 */
function validate_max_length($value, int $maxLength, string $fieldLabel, array &$errors, string $fieldKey): string {
    $clean = (string) $value;
    if (mb_strlen($clean) > $maxLength) {
        $errors[$fieldKey] = "{$fieldLabel} cannot exceed {$maxLength} characters.";
    }
    return $clean;
}

/**
 * Validates that a value is in a list of allowed values.
 *
 * @param mixed $value
 * @param array $allowedValues
 * @param string $fieldLabel
 * @param array $errors
 * @param string $fieldKey
 * @return string
 */
function validate_in($value, array $allowedValues, string $fieldLabel, array &$errors, string $fieldKey): string {
    $clean = (string) $value;
    if (!in_array($clean, $allowedValues, true)) {
        $errors[$fieldKey] = "{$fieldLabel} contains an invalid selection.";
    }
    return $clean;
}
