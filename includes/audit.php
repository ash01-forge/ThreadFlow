<?php
/**
 * ThreadFlow - Audit History Logging Helper
 * 
 * Provides centralized, immutable change and event tracking.
 * Strictly sanitizes sensitive information (passwords, tokens, cookies).
 * Fail-safe error handling writes to error_log() without exposing raw errors to users.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Strips sensitive keys recursively from arrays prior to audit serialization.
 *
 * @param mixed $data
 * @return mixed
 */
function audit_strip_secrets($data) {
    if (!is_array($data)) {
        return $data;
    }

    $forbiddenKeys = [
        'password',
        'password_hash',
        'password_confirmation',
        'csrf_token',
        '_csrf_token',
        'token',
        'tokens',
        'session_id',
        'session',
        '_session',
        'cookie',
        'cookies',
        'authorization',
        'auth_token',
        'secret',
        'secret_key',
        'api_key'
    ];

    $sanitized = [];
    foreach ($data as $key => $value) {
        $normalizedKey = strtolower((string)$key);
        if (in_array($normalizedKey, $forbiddenKeys, true)) {
            continue;
        }

        if (is_array($value)) {
            $sanitized[$key] = audit_strip_secrets($value);
        } else {
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
}

/**
 * Converts array of values into a sanitized JSON string.
 * Returns null if input is empty or null.
 *
 * @param array|null $values
 * @return string|null
 */
function audit_encode_values(?array $values): ?string {
    if ($values === null || empty($values)) {
        return null;
    }

    $clean = audit_strip_secrets($values);
    if (empty($clean)) {
        return null;
    }

    $encoded = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return ($encoded !== false) ? $encoded : null;
}

/**
 * Safely extracts client IP address without trusting unverified spoofable headers.
 *
 * @return string|null
 */
function audit_get_client_ip(): ?string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return $remoteAddr;
    }
    return null;
}

/**
 * Records an immutable audit log entry.
 *
 * @param array $params [
 *   'action'            => string (e.g. 'CREATE', 'UPDATE', 'STATUS_CHANGE'),
 *   'module'            => string (e.g. 'defect_types', 'quality_inspections', 'rework', 'packing_shipment'),
 *   'table_name'        => string (e.g. 'defect_types', 'quality_inspections', 'rework_jobs', 'packing_shipments'),
 *   'record_id'         => int|null,
 *   'record_identifier' => string|null (e.g. 'DEF-001', 'QI-2026-0001'),
 *   'old_values'        => array|null,
 *   'new_values'        => array|null,
 *   'user_id'           => int|null (optional override),
 *   'user_identity'     => string|null (optional override),
 *   'user_role'         => string|null (optional override),
 * ]
 * @return bool True if logged successfully, false otherwise.
 */
function audit_log(array $params): bool {
    global $conn;

    if (!($conn instanceof mysqli)) {
        error_log("ThreadFlow Audit Log: Database connection not available.");
        return false;
    }

    try {
        $action           = strtoupper(trim((string)($params['action'] ?? 'UNKNOWN')));
        $module           = trim((string)($params['module'] ?? 'general'));
        $tableName        = trim((string)($params['table_name'] ?? ''));
        $recordId         = isset($params['record_id']) && (int)$params['record_id'] > 0 ? (int)$params['record_id'] : null;
        $recordIdentifier = isset($params['record_identifier']) ? substr(trim((string)$params['record_identifier']), 0, 100) : null;

        if ($action === '' || $module === '' || $tableName === '') {
            error_log("ThreadFlow Audit Log: Missing mandatory parameters (action, module, table_name).");
            return false;
        }

        // Determine actor identity
        $currentUser = auth_user();
        $userId = $params['user_id'] ?? ($currentUser['user_id'] ?? null);
        $userId = ($userId !== null && (int)$userId > 0) ? (int)$userId : null;

        $userIdentity = $params['user_identity'] ?? ($currentUser['username'] ?? 'system');
        $userIdentity = substr(trim((string)$userIdentity), 0, 100);
        if ($userIdentity === '') {
            $userIdentity = 'system';
        }

        $userRole = $params['user_role'] ?? ($currentUser['role'] ?? null);
        $userRole = ($userRole !== null) ? substr(trim((string)$userRole), 0, 50) : null;

        // Serialize before/after payloads safely
        $oldValuesJson = isset($params['old_values']) && is_array($params['old_values']) 
            ? audit_encode_values($params['old_values']) 
            : null;

        $newValuesJson = isset($params['new_values']) && is_array($params['new_values']) 
            ? audit_encode_values($params['new_values']) 
            : null;

        $ipAddress = audit_get_client_ip();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) 
            ? substr(trim($_SERVER['HTTP_USER_AGENT']), 0, 255) 
            : null;

        $sql = "INSERT INTO audit_logs (
                    user_id,
                    user_identity,
                    user_role,
                    action,
                    module,
                    table_name,
                    record_id,
                    record_identifier,
                    old_values,
                    new_values,
                    ip_address,
                    user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("ThreadFlow Audit Log Prepare Failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param(
            "isssssisssss",
            $userId,
            $userIdentity,
            $userRole,
            $action,
            $module,
            $tableName,
            $recordId,
            $recordIdentifier,
            $oldValuesJson,
            $newValuesJson,
            $ipAddress,
            $userAgent
        );

        $executed = $stmt->execute();
        $stmt->close();

        return (bool)$executed;
    } catch (Throwable $e) {
        error_log("ThreadFlow Audit Log Exception: " . $e->getMessage());
        return false;
    }
}
