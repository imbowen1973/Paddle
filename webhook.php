<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Paddle webhook endpoint.
 *
 * This script receives webhook notifications from Paddle and processes them.
 * URL: https://yourmoodle.com/enrol/paddle/webhook.php
 *
 * @package    enrol_paddle
 * @copyright  2025 Mark Bowen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output.
define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

require('../../config.php');
require_once($CFG->libdir . '/enrollib.php');

// Get the raw POST data.
$payload = file_get_contents('php://input');

// Get the Paddle signature from headers.
$signature = isset($_SERVER['HTTP_PADDLE_SIGNATURE']) ? $_SERVER['HTTP_PADDLE_SIGNATURE'] : '';

// Alternative header name (some servers use this format).
if (empty($signature) && isset($_SERVER['HTTP_X_PADDLE_SIGNATURE'])) {
    $signature = $_SERVER['HTTP_X_PADDLE_SIGNATURE'];
}

// Process the webhook.
$handler = new \enrol_paddle\webhook_handler();
$result = $handler->process_webhook($payload, $signature);

// Set appropriate HTTP response code.
if ($result['status'] === 'success') {
    http_response_code(200);
} else if ($result['status'] === 'error') {
    if (strpos($result['message'], 'Invalid') !== false) {
        http_response_code(401);
    } else if (strpos($result['message'], 'Missing') !== false) {
        http_response_code(400);
    } else {
        http_response_code(500);
    }
}

// Return JSON response.
header('Content-Type: application/json');
echo json_encode($result);
exit;
