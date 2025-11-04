<?php
// Ultra-simple check - no Moodle framework
// Upload to: /cloudclusters/moodle/html/enrol/paddle/simple_check.php
// Access: https://advance.ebvs.eu/enrol/paddle/simple_check.php

echo "<h1>Simple File Check</h1>";

echo "<h2>1. PHP Version</h2>";
echo "<p>" . phpversion() . "</p>";

echo "<h2>2. File Exists</h2>";
$file = __DIR__ . '/classes/external/get_checkout_id.php';
if (file_exists($file)) {
    echo "<p style='color:green'>✓ File exists: $file</p>";
    echo "<p>Size: " . filesize($file) . " bytes</p>";
    echo "<p>Modified: " . date('Y-m-d H:i:s', filemtime($file)) . "</p>";
} else {
    echo "<p style='color:red'>✗ File NOT found</p>";
}

echo "<h2>3. Version File</h2>";
$versionfile = __DIR__ . '/version.php';
if (file_exists($versionfile)) {
    $content = file_get_contents($versionfile);
    if (preg_match('/\$plugin->version\s*=\s*(\d+)/', $content, $matches)) {
        echo "<p>Version in file: " . $matches[1] . "</p>";
    }
}

echo "<h2>4. Services File</h2>";
$servicesfile = __DIR__ . '/db/services.php';
if (file_exists($servicesfile)) {
    echo "<p style='color:green'>✓ services.php exists</p>";
} else {
    echo "<p style='color:red'>✗ services.php NOT found</p>";
}

echo "<h2>5. First 20 lines of get_checkout_id.php</h2>";
if (file_exists($file)) {
    $lines = file($file);
    echo "<pre>";
    for ($i = 0; $i < min(20, count($lines)); $i++) {
        echo htmlspecialchars(($i+1) . ': ' . $lines[$i]);
    }
    echo "</pre>";
}

echo "<h2>Instructions</h2>";
echo "<p>If you see this page, basic PHP is working.</p>";
echo "<p>The 500 error on other pages means Moodle's framework is rejecting the plugin.</p>";
echo "<p><strong>Try this:</strong></p>";
echo "<ol>";
echo "<li>SSH into your server</li>";
echo "<li>Run: <code>cd /cloudclusters/moodle/html && php admin/cli/purge_caches.php</code></li>";
echo "<li>Run: <code>php admin/cli/uninstall_plugins.php --plugins=enrol_paddle --run</code></li>";
echo "<li>Run: <code>php admin/cli/upgrade.php --non-interactive</code></li>";
echo "<li>Test again</li>";
echo "</ol>";
