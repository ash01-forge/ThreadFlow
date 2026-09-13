<?php
/**
 * ThreadFlow - CLI Test User Provisioning Script
 * 
 * Securely provisions users for any role (admin, merchandiser, production_manager,
 * qc_officer, management) for local testing without web signup or CRUD.
 * CLI-only execution. Passwords are read interactively from STDIN only.
 * 
 * Usage:
 *   php bin/create_user.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script must be executed via the command line interface (CLI) only.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

function cli_prompt(string $question, string $default = ''): string {
    $prompt = $default !== '' ? "{$question} [{$default}]: " : "{$question}: ";
    echo $prompt;
    $input = trim(fgets(STDIN));
    return $input !== '' ? $input : $default;
}

echo "========================================================\n";
echo " ThreadFlow - Provision Test User Account\n";
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

// Select role
echo "Available Roles:\n";
echo "  1) admin\n";
echo "  2) merchandiser          (Member 1 - Buyers)\n";
echo "  3) production_manager    (Member 2 - Suppliers & Member 3 - Production Batches)\n";
echo "  4) qc_officer            (Member 4 - Defect Types)\n";
echo "  5) management            (Dashboard overview)\n\n";

$roleChoice = cli_prompt("Select role number (1-5)", "2");
$roleMap = [
    '1' => 'admin',
    '2' => 'merchandiser',
    '3' => 'production_manager',
    '4' => 'qc_officer',
    '5' => 'management',
];

if (!isset($roleMap[$roleChoice])) {
    echo "[ERROR] Invalid role selection: '{$roleChoice}'. Aborting.\n";
    exit(1);
}

$role = $roleMap[$roleChoice];

// Interactive input collection
$username = cli_prompt("Enter Username", $role . "_test");
$fullName = cli_prompt("Enter Full Name", ucwords(str_replace('_', ' ', $role)) . " Test User");
$email    = cli_prompt("Enter Email Address", $role . "@threadflow.local");

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
echo "\nEnter Password for {$username} (minimum 8 characters):\n";
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

    echo "\n[SUCCESS] User account successfully created!\n";
    echo "--------------------------------------------------------\n";
    echo " User ID:   {$newId}\n";
    echo " Username:  {$username}\n";
    echo " Email:     {$email}\n";
    echo " Full Name: {$fullName}\n";
    echo " Role:      {$role}\n";
    echo " Status:    Active\n";
    echo "--------------------------------------------------------\n";
    echo "You can test this role by logging in at: " . (BASE_URL ?: '/') . "/auth/login.php\n\n";
} else {
    echo "[ERROR] Failed to create user: " . $insertStmt->error . "\n";
    $insertStmt->close();
    exit(1);
}
