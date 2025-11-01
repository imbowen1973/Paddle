<?php
// Test if external function can be called directly
// Upload to: /cloudclusters/moodle/html/enrol/paddle/test_external.php
// Access via: https://advance.ebvs.eu/enrol/paddle/test_external.php

define('CLI_SCRIPT', false);
require_once('../../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h1>External Function Direct Test</h1>";

// Try to load the class directly
try {
    echo "<p>Attempting to load class...</p>";

    if (class_exists('enrol_paddle\external\get_checkout_id')) {
        echo "<p style='color:green'>✓ Class exists</p>";

        // Try to call execute_parameters
        try {
            $params = \enrol_paddle\external\get_checkout_id::execute_parameters();
            echo "<p style='color:green'>✓ execute_parameters() works</p>";
            echo "<pre>";
            print_r($params);
            echo "</pre>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ execute_parameters() failed: " . $e->getMessage() . "</p>";
        }

        // Try to call execute_returns
        try {
            $returns = \enrol_paddle\external\get_checkout_id::execute_returns();
            echo "<p style='color:green'>✓ execute_returns() works</p>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ execute_returns() failed: " . $e->getMessage() . "</p>";
        }

        // Try to call execute with a test instance
        echo "<h2>Test Execute Call</h2>";
        echo "<p>Looking for a paddle enrol instance...</p>";
        $instance = $DB->get_record('enrol', ['enrol' => 'paddle'], '*', IGNORE_MULTIPLE);

        if ($instance) {
            echo "<p>Found instance ID: " . $instance->id . "</p>";
            echo "<p>Calling execute({$instance->id})...</p>";

            try {
                $result = \enrol_paddle\external\get_checkout_id::execute($instance->id);
                echo "<p style='color:green'>✓ Execute succeeded!</p>";
                echo "<pre>";
                print_r($result);
                echo "</pre>";
            } catch (Exception $e) {
                echo "<p style='color:red'>✗ Execute failed: " . $e->getMessage() . "</p>";
                echo "<pre>";
                print_r($e);
                echo "</pre>";
            }
        } else {
            echo "<p style='color:orange'>No paddle enrol instances found</p>";
        }

    } else {
        echo "<p style='color:red'>✗ Class does NOT exist</p>";
        echo "<p>Checking if file exists...</p>";

        $file = $CFG->dirroot . '/enrol/paddle/classes/external/get_checkout_id.php';
        if (file_exists($file)) {
            echo "<p>File exists: $file</p>";
            echo "<p>Trying to include it manually...</p>";

            try {
                require_once($file);
                echo "<p>File included successfully</p>";

                if (class_exists('enrol_paddle\external\get_checkout_id')) {
                    echo "<p style='color:green'>✓ Class NOW exists after manual include</p>";
                } else {
                    echo "<p style='color:red'>✗ Class still doesn't exist after include</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color:red'>Error including file: " . $e->getMessage() . "</p>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        } else {
            echo "<p style='color:red'>File does NOT exist: $file</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Fatal error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Check services.php registration
echo "<h2>Services.php Check</h2>";
$servicesfile = $CFG->dirroot . '/enrol/paddle/db/services.php';
if (file_exists($servicesfile)) {
    echo "<p>services.php exists</p>";
    $functions = [];
    require($servicesfile);

    if (isset($functions['enrol_paddle_get_checkout_id'])) {
        echo "<p style='color:green'>✓ Function defined in services.php</p>";
        echo "<pre>";
        print_r($functions['enrol_paddle_get_checkout_id']);
        echo "</pre>";
    } else {
        echo "<p style='color:red'>✗ Function NOT defined in services.php</p>";
    }
}

// Check database registration
echo "<h2>Database Registration</h2>";
$dbfunc = $DB->get_record('external_functions', ['name' => 'enrol_paddle_get_checkout_id']);
if ($dbfunc) {
    echo "<p style='color:green'>✓ Function registered in database</p>";
    echo "<pre>";
    print_r($dbfunc);
    echo "</pre>";
} else {
    echo "<p style='color:red'>✗ Function NOT in database</p>";
    echo "<p><strong>ACTION REQUIRED:</strong> Go to Site Administration → Development → Purge all caches</p>";
}
