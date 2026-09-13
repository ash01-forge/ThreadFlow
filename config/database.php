<?php
/**
 * ThreadFlow - Database Connection Handler
 * 
 * Provides a shared, configured mysqli connection ($conn).
 * Safe error handling prevents leaking database credentials or internal errors.
 */

require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        DB_PORT
    );

    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Log the actual internal database exception message safely
    error_log("ThreadFlow Database Error: " . $e->getMessage());

    // Never display raw database errors, passwords, or connection parameters to users
    http_response_code(500);
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Database connection failed. Check your local configuration in config/config.local.php.\n");
    } else {
        exit("<!DOCTYPE html><html><head><title>System Error | ThreadFlow</title><style>body{font-family:system-ui,sans-serif;background:#FCEBED;color:#1F1415;padding:40px;text-align:center;} .box{background:#fff;max-width:500px;margin:0 auto;padding:30px;border:1px solid #E5C3C7;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.05);} h2{color:#78000F;margin-top:0;}</style></head><body><div class='box'><h2>Database Connection Unavailable</h2><p>Unable to connect to the database. Please verify your local settings in <code>config/config.local.php</code> and ensure the MySQL service is running.</p></div></body></html>");
    }
    exit(1);
}