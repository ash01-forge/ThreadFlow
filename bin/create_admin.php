<?php
/**
 * ThreadFlow - CLI Initial Admin Provisioning Script
 * 
 * Securely provisions the initial administrator account.
 * CLI-only execution. Passwords are read interactively from STDIN only.
 * 
 * Usage:
 *   php bin/create_admin.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script must be executed via the command line interface (CLI) only.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function cli_prompt(string $question, string $default = ''): string {
    $prompt = $default !== '' ? "{$question} [{$default}]: " : "{$question}: ";
    echo $prompt;
    $input = trim(fgets(STDIN));
    return $input !== '' ? $input : $default;
}

echo "========================================================\n";
echo " ThreadFlow - Provision Initial Administrator Account\n";
echo "========================================================\n\n";

// Ensure users table exists
try {
    $check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($check->num_rows === 0) {
        echo "[ERROR] The 'users' table does not exist in database '" . DB_NAME . "'.\n";
        echo "Please import sql/auth.sql first before running this script.\n";
        exit(1);
    }
} catch (mysqli_sql_exception $e) {
    echo "[ERROR] Could not query database: " . $e->getMessage() . "\n";
    exit(1);
}

// Interactive input collection
$username = cli_prompt("Enter Administrator Username", "admin");
$fullName = cli_prompt("Enter Full Name", "System Administrator");
$email    = cli_prompt("Enter Email Address", "admin@threadflow.local");

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "[ERROR] Invalid email address format: '{$email}'.\n";
    exit(1);
}

// Check for existing duplicate username or email
$checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    echo "[ERROR] A user with username '{$username}' or email '{$email}' already exists.\n";
    exit(1);
}

// Passwords MUST be read interactively from standard input
echo "\nEnter Administrator Password (minimum 8 characters):\n";
echo "Password: ";
$password = trim(fgets(STDIN));

if (strlen($password) < 8) {
    echo "[ERROR] Password must be at least 8 characters long.\n";
    exit(1);
}

echo "Confirm Password: ";
$passwordConfirm = trim(fgets(STDIN));

if ($password !== $passwordConfirm) {
    echo "[ERROR] Passwords do not match. Aborting.\n";
    exit(1);
}

// Hash password with bcrypt/argon2 via PASSWORD_DEFAULT
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';
$isActive = 1;

$insertStmt = $conn->prepare("INSERT INTO users (username, email, full_name, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
if (!$insertStmt) {
    echo "[ERROR] Failed to prepare insert statement: " . $conn->error . "\n";
    exit(1);
}

$insertStmt->bind_param("sssssi", $username, $email, $fullName, $passwordHash, $role, $isActive);

if ($insertStmt->execute()) {
    $newId = $insertStmt->insert_id;
    $insertStmt->close();

    echo "\n[SUCCESS] Administrator account successfully created!\n";
    echo "--------------------------------------------------------\n";
    echo " User ID:   {$newId}\n";
    echo " Username:  {$username}\n";
    echo " Email:     {$email}\n";
    echo " Full Name: {$fullName}\n";
    echo " Role:      {$role}\n";
    echo " Status:    Active\n";
    echo "--------------------------------------------------------\n";
    echo "You can now log in at: " . (BASE_URL ?: '/') . "/auth/login.php\n\n";
} else {
    echo "[ERROR] Failed to create administrator: " . $insertStmt->error . "\n";
    $insertStmt->close();
    exit(1);
}
