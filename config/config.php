<?php
/**
 * ThreadFlow - Central Configuration Loader
 * 
 * Loads default settings and merges them with config.local.php (if present).
 * Defines application constants and configures safe error reporting.
 */

// Prevent multiple inclusions from re-declaring constants
if (!defined('THREADFLOW_CONFIG_LOADED')) {
    define('THREADFLOW_CONFIG_LOADED', true);

    // Default configuration values
    $defaultConfig = [
        'db_host'          => '127.0.0.1',
        'db_port'          => 3306,
        'db_name'          => 'threadflow_db',
        'db_user'          => 'root',
        'db_pass'          => '',
        'base_url'         => '/ThreadFlow',
        'app_name'         => 'ThreadFlow',
        'app_env'          => 'development',
        'session_lifetime' => 0,
    ];

    // Load local configuration overrides if available
    $localConfigFile = __DIR__ . '/config.local.php';
    if (file_exists($localConfigFile)) {
        $localConfig = require $localConfigFile;
        if (is_array($localConfig)) {
            $config = array_merge($defaultConfig, $localConfig);
        } else {
            $config = $defaultConfig;
        }
    } else {
        $config = $defaultConfig;
    }

    // Define standard constants
    define('BASE_URL', rtrim($config['base_url'], '/'));
    define('APP_NAME', $config['app_name']);
    define('APP_ENV', $config['app_env']);

    define('DB_HOST', $config['db_host']);
    define('DB_PORT', (int) $config['db_port']);
    define('DB_NAME', $config['db_name']);
    define('DB_USER', $config['db_user']);
    define('DB_PASS', $config['db_pass']);

    // Error handling configuration: never display raw system or database errors
    if (APP_ENV === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', '0'); // Kept at 0 to prevent leaking raw traces/credentials in output
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
    } else {
        error_reporting(0);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
    }
}
