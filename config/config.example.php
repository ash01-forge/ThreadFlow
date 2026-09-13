<?php
/**
 * ThreadFlow - Configuration Template
 * 
 * Copy this file to 'config.local.php' in the same directory and update
 * with your local environment settings.
 * DO NOT commit 'config.local.php' to version control.
 */

return [
    // Database credentials
    'db_host'     => '127.0.0.1',
    'db_port'     => 3306,
    'db_name'     => 'threadflow_db',
    'db_user'     => 'root',
    'db_pass'     => '',

    // Application settings
    'base_url'    => '/ThreadFlow',
    'app_name'    => 'ThreadFlow',
    'app_env'     => 'development', // 'development' or 'production'

    // Session settings
    'session_lifetime' => 0, // 0 = until browser closes
];
