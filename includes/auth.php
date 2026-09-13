<?php
/**
 * ThreadFlow - Authentication & Role Authorization
 * 
 * Provides login, logout, active user verification, and role-based access control.
 * Re-queries the database on protected requests to enforce current active status and role.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

// Recognized system roles
define('ROLES_LIST', ['admin', 'merchandiser', 'production_manager', 'qc_officer', 'management']);

/**
 * Retrieves the currently authenticated user with live verification from the database.
 * The database is queried on each HTTP request to enforce real-time active status and role assignments.
 * Within a single request cycle, the verified record is memoized.
 *
 * @param bool $forceRefresh Set true to bypass in-request memoization
 * @return array|null Live user record or null if not authenticated
 */
function auth_user(bool $forceRefresh = false): ?array {
    global $conn;
    static $requestUser = null;
    static $alreadyChecked = false;

    if ($alreadyChecked && !$forceRefresh) {
        return $requestUser;
    }

    if (empty($_SESSION['user_id'])) {
        $alreadyChecked = true;
        $requestUser = null;
        return null;
    }

    $userId = (int) $_SESSION['user_id'];

    try {
        $stmt = $conn->prepare("SELECT user_id, username, email, full_name, role, is_active FROM users WHERE user_id = ? LIMIT 1");
        if (!$stmt) {
            error_log("auth_user prepare statement failed: " . $conn->error);
            $alreadyChecked = true;
            $requestUser = null;
            return null;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $liveUser = $result->fetch_assoc();
        $stmt->close();

        // If user no longer exists or has been deactivated in the database
        if (!$liveUser || (int) $liveUser['is_active'] !== 1) {
            auth_logout();
            $alreadyChecked = true;
            $requestUser = null;
            return null;
        }

        // Keep session cache synchronized with live database data
        $_SESSION['user'] = [
            'user_id'   => (int) $liveUser['user_id'],
            'username'  => $liveUser['username'],
            'email'     => $liveUser['email'],
            'full_name' => $liveUser['full_name'],
            'role'      => $liveUser['role'],
            'is_active' => (int) $liveUser['is_active'],
        ];

        $alreadyChecked = true;
        $requestUser = $_SESSION['user'];
        return $requestUser;
    } catch (Exception $e) {
        error_log("auth_user exception: " . $e->getMessage());
        $alreadyChecked = true;
        $requestUser = null;
        return null;
    }
}

/**
 * Checks if a user is currently logged in and active.
 *
 * @return bool
 */
function is_logged_in(): bool {
    return auth_user() !== null;
}

/**
 * Checks whether the current user has any of the specified roles.
 *
 * @param string|array $roles Single role string or array of allowed roles
 * @return bool
 */
function has_role($roles): bool {
    $user = auth_user();
    if (!$user) {
        return false;
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array($user['role'], $allowed, true);
}

/**
 * Enforces that a user is authenticated.
 * If not, saves the intended URL and redirects to the login page.
 *
 * @return void
 */
function require_login(): void {
    if (!is_logged_in()) {
        if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        }
        flash_set('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }
}

/**
 * Enforces that the authenticated user possesses one of the required roles.
 * Halts with HTTP 403 Access Denied if unauthorized.
 *
 * @param string|array $roles
 * @return void
 */
function require_role($roles): void {
    require_login();

    if (!has_role($roles)) {
        http_response_code(403);
        include __DIR__ . '/access_denied.php';
        exit;
    }
}

/**
 * Attempts to authenticate a user with provided username/email and password.
 *
 * @param string $usernameOrEmail
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function auth_attempt(string $usernameOrEmail, string $password): array {
    global $conn;

    $usernameOrEmail = trim($usernameOrEmail);

    if ($usernameOrEmail === '' || $password === '') {
        return [
            'success' => false,
            'message' => 'Both username/email and password are required.',
            'user'    => null,
        ];
    }

    try {
        $stmt = $conn->prepare("SELECT user_id, username, email, full_name, password_hash, role, is_active FROM users WHERE (username = ? OR email = ?) LIMIT 1");
        if (!$stmt) {
            error_log("auth_attempt statement preparation failed: " . $conn->error);
            return [
                'success' => false,
                'message' => 'Authentication service currently unavailable.',
                'user'    => null,
            ];
        }

        $stmt->bind_param("ss", $usernameOrEmail, $usernameOrEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid username/email or password.',
                'user'    => null,
            ];
        }

        if ((int) $user['is_active'] !== 1) {
            return [
                'success' => false,
                'message' => 'This account has been deactivated. Please contact an administrator.',
                'user'    => null,
            ];
        }

        // Session ID regeneration prevents session fixation attacks
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['user'] = [
            'user_id'   => (int) $user['user_id'],
            'username'  => $user['username'],
            'email'     => $user['email'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
            'is_active' => (int) $user['is_active'],
        ];

        // Update last login timestamp
        $updateStmt = $conn->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("i", $user['user_id']);
            $updateStmt->execute();
            $updateStmt->close();
        }

        // Trigger fresh auth_user() check
        auth_user(true);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user'    => $_SESSION['user'],
        ];
    } catch (Exception $e) {
        error_log("auth_attempt error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred during authentication.',
            'user'    => null,
        ];
    }
}

/**
 * Terminates the authenticated session completely.
 *
 * @return void
 */
function auth_logout(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
