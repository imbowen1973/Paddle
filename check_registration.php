<?php
// Check if external function is registered
// Upload to: /cloudclusters/moodle/html/enrol/paddle/check_registration.php
// Access: https://advance.ebvs.eu/enrol/paddle/check_registration.php

require_once('../../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h1>External Function Registration Check</h1>";

// Check database
$func = $DB->get_record('external_functions', ['name' => 'enrol_paddle_get_checkout_id']);

if ($func) {
    echo "<p style='color:green'><strong>✓ Function IS registered in database</strong></p>";
    echo "<pre>";
    print_r($func);
    echo "</pre>";
} else {
    echo "<p style='color:red'><strong>✗ Function NOT registered in database!</strong></p>";
    echo "<p>This is why you're getting 500 errors - Moodle can't find the function.</p>";

    echo "<h2>Solution:</h2>";
    echo "<ol>";
    echo "<li>Go to: <a href='" . $CFG->wwwroot . "/admin/index.php'>Site Administration → Notifications</a></li>";
    echo "<li>Run any pending database upgrades</li>";
    echo "<li>Go to: <a href='" . $CFG->wwwroot . "/admin/purgecaches.php'>Development → Purge all caches</a></li>";
    echo "<li>Refresh this page to check again</li>";
    echo "</ol>";
}

// Check plugin version
echo "<h2>Plugin Version</h2>";
$version = $DB->get_field('config_plugins', 'value', ['plugin' => 'enrol_paddle', 'name' => 'version']);
echo "<p>Database version: " . ($version ? $version : 'NOT FOUND') . "</p>";
echo "<p>Expected: 2025110137</p>";

if ($version != 2025110137) {
    echo "<p style='color:orange'><strong>⚠ Version mismatch!</strong></p>";
    echo "<p>You need to visit <a href='" . $CFG->wwwroot . "/admin/index.php'>admin/index.php</a> to upgrade</p>";
}

// Check if services.php exists
echo "<h2>Services File</h2>";
$servicesfile = $CFG->dirroot . '/enrol/paddle/db/services.php';
if (file_exists($servicesfile)) {
    echo "<p style='color:green'>✓ services.php exists</p>";

    $functions = [];
    require($servicesfile);

    if (isset($functions['enrol_paddle_get_checkout_id'])) {
        echo "<p style='color:green'>✓ Function defined in services.php</p>";
        echo "<pre>";
        print_r($functions['enrol_paddle_get_checkout_id']);
        echo "</pre>";
    }
} else {
    echo "<p style='color:red'>✗ services.php NOT found!</p>";
}
