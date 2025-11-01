<?php
// Diagnostic script to test external function registration
// Upload to: /cloudclusters/moodle/html/enrol/paddle/test_diagnostic.php
// Run via: https://advance.ebvs.eu/enrol/paddle/test_diagnostic.php

define('CLI_SCRIPT', false);
require_once('../../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h1>Paddle Plugin Diagnostic</h1>";

// Check version
$plugin = core_plugin_manager::instance()->get_plugin_info('enrol_paddle');
echo "<h2>Plugin Version</h2>";
if ($plugin) {
    echo "<p>Version: " . $plugin->versiondb . "</p>";
    echo "<p>Expected: 2025110130</p>";
    echo "<p>Match: " . ($plugin->versiondb == 2025110130 ? 'YES' : 'NO') . "</p>";
} else {
    echo "<p style='color:red'>Plugin not found!</p>";
}

// Check external function registration
echo "<h2>External Function Registration</h2>";
$external = $DB->get_record('external_functions', ['name' => 'enrol_paddle_get_checkout_id']);
if ($external) {
    echo "<pre>";
    print_r($external);
    echo "</pre>";
} else {
    echo "<p style='color:red'>External function NOT registered in database!</p>";
    echo "<p>Run: Site Administration → Development → Purge all caches</p>";
}

// Check if class file exists
echo "<h2>Class File</h2>";
$classfile = $CFG->dirroot . '/enrol/paddle/classes/external/get_checkout_id.php';
if (file_exists($classfile)) {
    echo "<p style='color:green'>File exists: $classfile</p>";
    echo "<p>File size: " . filesize($classfile) . " bytes</p>";
    echo "<p>Modified: " . date('Y-m-d H:i:s', filemtime($classfile)) . "</p>";
} else {
    echo "<p style='color:red'>File NOT found: $classfile</p>";
}

// Check debug mode
echo "<h2>Debug Mode</h2>";
$debug = $DB->get_record('config_plugins', ['plugin' => 'enrol_paddle', 'name' => 'debug_mode']);
if ($debug) {
    echo "<p>debug_mode = " . $debug->value . " (" . ($debug->value ? 'ENABLED' : 'DISABLED') . ")</p>";
} else {
    echo "<p style='color:orange'>debug_mode setting not found</p>";
}

// Test class autoload
echo "<h2>Class Autoload Test</h2>";
try {
    if (class_exists('enrol_paddle\external\get_checkout_id')) {
        echo "<p style='color:green'>Class loads successfully</p>";

        // Check methods
        $methods = get_class_methods('enrol_paddle\external\get_checkout_id');
        echo "<p>Methods found:</p><ul>";
        foreach ($methods as $method) {
            echo "<li>$method</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Class does NOT exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error loading class: " . $e->getMessage() . "</p>";
}

// Check PHP error log location
echo "<h2>PHP Error Log</h2>";
echo "<p>error_log setting: " . ini_get('error_log') . "</p>";
echo "<p>log_errors: " . (ini_get('log_errors') ? 'ON' : 'OFF') . "</p>";

// Test error_log() function
error_log('PADDLE DIAGNOSTIC TEST: This is a test log entry at ' . date('Y-m-d H:i:s'));
echo "<p style='color:green'>Test log written. Check error log for: 'PADDLE DIAGNOSTIC TEST'</p>";

// Check services.php
echo "<h2>Services Definition</h2>";
$servicesfile = $CFG->dirroot . '/enrol/paddle/db/services.php';
if (file_exists($servicesfile)) {
    echo "<p style='color:green'>services.php exists</p>";
    require_once($servicesfile);
    if (isset($functions['enrol_paddle_get_checkout_id'])) {
        echo "<pre>";
        print_r($functions['enrol_paddle_get_checkout_id']);
        echo "</pre>";
    } else {
        echo "<p style='color:red'>Function not defined in \$functions array</p>";
    }
}

echo "<h2>Actions Required</h2>";
echo "<ol>";
echo "<li>If version doesn't match: Re-deploy plugin files</li>";
echo "<li>If external function not registered: Purge all caches</li>";
echo "<li>If class doesn't load: Check PHP syntax errors</li>";
echo "<li>Check the PHP error log location shown above for 'PADDLE' messages</li>";
echo "</ol>";
