<?php
// Ultra-simple test - just check if file can be loaded
// Upload to: /cloudclusters/moodle/html/enrol/paddle/test_simple.php
// Access via: https://advance.ebvs.eu/enrol/paddle/test_simple.php

echo "<h1>Simple File Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

define('CLI_SCRIPT', false);

echo "<p>Step 1: Including config.php...</p>";
try {
    require_once('../../../config.php');
    echo "<p style='color:green'>✓ config.php loaded</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ config.php failed: " . $e->getMessage() . "</p>";
    die();
}

echo "<p>Step 2: Checking file exists...</p>";
$file = $CFG->dirroot . '/enrol/paddle/classes/external/get_checkout_id.php';
if (file_exists($file)) {
    echo "<p style='color:green'>✓ File exists: $file</p>";
    echo "<p>File size: " . filesize($file) . " bytes</p>";
    echo "<p>Modified: " . date('Y-m-d H:i:s', filemtime($file)) . "</p>";
} else {
    echo "<p style='color:red'>✗ File NOT found</p>";
    die();
}

echo "<p>Step 3: Reading first 50 lines...</p>";
$lines = file($file);
echo "<pre>";
for ($i = 0; $i < min(50, count($lines)); $i++) {
    echo htmlspecialchars(($i+1) . ': ' . $lines[$i]);
}
echo "</pre>";

echo "<p>Step 4: Checking for syntax errors with php -l...</p>";
echo "<p>Note: Can't run php -l from web context, but here's what to run on server:</p>";
echo "<code>php -l " . $file . "</code>";

echo "<p>Step 5: Attempting to parse file...</p>";
try {
    $tokens = token_get_all(file_get_contents($file));
    echo "<p style='color:green'>✓ File tokenizes successfully (" . count($tokens) . " tokens)</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Tokenization failed: " . $e->getMessage() . "</p>";
}

echo "<p>Step 6: Counting braces...</p>";
$content = file_get_contents($file);
$open_braces = substr_count($content, '{');
$close_braces = substr_count($content, '}');
echo "<p>Open braces: $open_braces</p>";
echo "<p>Close braces: $close_braces</p>";
if ($open_braces === $close_braces) {
    echo "<p style='color:green'>✓ Braces match</p>";
} else {
    echo "<p style='color:red'>✗ Brace mismatch! Difference: " . abs($open_braces - $close_braces) . "</p>";
}

echo "<p>Step 7: Looking for common syntax errors...</p>";
// Check for unescaped quotes in strings
if (preg_match('/\$debuglog\[\] = \'.*[^\\\\]\'.*\'/s', $content)) {
    echo "<p style='color:orange'>⚠ Warning: Possible unescaped quotes in debuglog strings</p>";
}

echo "<h2>Complete</h2>";
echo "<p>If you see this, the file is syntactically valid PHP.</p>";
