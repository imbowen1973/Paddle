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
 * Listens for Paddle Billing webhook notifications.
 *
 * This script validates Paddle Billing webhook HMAC signatures,
 * extracts metadata, and enrols the user.
 *
 * @package    enrol_paddle
 */

// Disable moodle specific debug messages and any errors in output.
define('NO_DEBUG_DISPLAY', true);

// This script does not require login.
require("../../config.php"); // phpcs:ignore
require_once("lib.php");
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

// The custom handler just logs exceptions and stops.
set_exception_handler(\enrol_paddle\util::get_exception_handler());

// Make sure we are enabled in the first place.
if (!enrol_is_enabled('paddle')) {
    http_response_code(503);
    throw new moodle_exception('errdisabled', 'enrol_paddle');
}

// Helper: decode body and verify signature for Paddle Billing API.
function enrol_paddle_read_and_verify(enrol_plugin $plugin) {
    // Paddle Billing sends JSON with Paddle-Signature header (HMAC).
    $raw = file_get_contents('php://input');
    $sigheader = $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '';
    if (!$raw || !$sigheader) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing body or signature');
    }
    $secret = (string)$plugin->get_config('webhook_secret', '');
    if (empty($secret)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing webhook secret');
    }
    // Expected format: t=timestamp;h1=..., optionally h2=... for SHA-512
    $parts = [];
    foreach (explode(';', $sigheader) as $p) {
        $kv = explode('=', trim($p), 2);
        if (count($kv) === 2) { $parts[strtolower($kv[0])] = $kv[1]; }
    }
    if (empty($parts['t'])) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Invalid signature header');
    }
    $signed = $parts['t'].':'.$raw;
    $algo = (string)$plugin->get_config('webhook_hmac_algo', 'sha512');
    $computed256 = hash_hmac('sha256', $signed, $secret);
    $computed512 = hash_hmac('sha512', $signed, $secret);
    $ok = false;
    if (!empty($parts['h1']) && hash_equals($computed256, $parts['h1'])) { $ok = true; }
    if (!empty($parts['h2']) && hash_equals($computed512, $parts['h2'])) { $ok = true; }
    // Enforce configured algorithm when present in header.
    if ($algo === 'sha512' && !empty($parts['h2'])) { $ok = hash_equals($computed512, $parts['h2']); }
    if ($algo === 'sha256' && !empty($parts['h1'])) { $ok = hash_equals($computed256, $parts['h1']); }
    if (!$ok) { throw new moodle_exception('erripninvalid', 'enrol_paddle', '', null, 'HMAC verify failed'); }
    $json = json_decode($raw);
    if (!$json) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Invalid JSON');
    }
    return $json; // Paddle Billing event JSON.
}

global $DB, $PAGE;

$plugin = enrol_get_plugin('paddle');
$payload = enrol_paddle_read_and_verify($plugin);

// Extract metadata from Paddle Billing webhook.
$userid = $courseid = $instanceid = 0;
$amount = 0.0; $currency = '';
$eventname = '';

// Paddle Billing API format.
$eventname = (string)($payload->event_type ?? $payload->event ?? '');

// Ignore non-transaction events (setup events like product.created, api_key.created)
$transaction_events = [
    'transaction.completed',
    'transaction.paid',
    'transaction.payment_failed',
    'checkout.completed',
    'order.completed',
    'subscription.created',
    'subscription.updated'
];

if (!in_array($eventname, $transaction_events)) {
    // Not a transaction event - acknowledge but don't process
    http_response_code(200);
    die('Event acknowledged but not processed: ' . $eventname);
}

$data = $payload->data ?? (object)[];
$meta = $data->custom_data ?? $data->metadata ?? (object)[];
$userid = (int)($meta->userid ?? 0);
$courseid = (int)($meta->courseid ?? 0);
$instanceid = (int)($meta->instanceid ?? 0);
$amount = (float)($data->amount ?? 0);
$currency = (string)($data->currency_code ?? $data->currency ?? '');

if (!$userid || !$courseid || !$instanceid) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing passthrough identifiers');
}

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

$plugin_instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'paddle', 'status' => 0], '*', MUST_EXIST);

// Verify currency and amount meets or exceeds Moodle pricing.
if ($currency && $currency !== $plugin_instance->currency) {
    \enrol_paddle\util::message_paddle_error_to_admin('Currency mismatch: '.$currency, (object)['courseid'=>$courseid,'userid'=>$userid,'instanceid'=>$instanceid]);
    die;
}
if ((float)$plugin_instance->cost <= 0) {
    $expected = (float)$plugin->get_config('cost');
} else {
    $expected = (float)$plugin_instance->cost;
}
$expected = format_float($expected, 2, false);
if ($amount && $amount + 0.00001 < (float)$expected) {
    \enrol_paddle\util::message_paddle_error_to_admin('Amount paid is not enough ('.$amount.' < '.$expected.')', (object)['courseid'=>$courseid,'userid'=>$userid,'instanceid'=>$instanceid]);
    die;
}

// Idempotency: do not double-enrol for the same transaction id if present.
$txid = $payload->order_id ?? $payload->checkout_id ?? $payload->transaction_id ?? null;
if ($txid) {
    if ($DB->record_exists('enrol_paddle', ['txn_id' => (string)$txid])) {
        die; // Already processed.
    }
}

// Log row into enrol_paddle table.
$log = new stdClass();
$log->courseid = $course->id;
$log->userid = $user->id;
$log->instanceid = $plugin_instance->id;
$log->event = (string)$eventname;
$log->payment_currency = $currency ?: $plugin_instance->currency;
$log->payment_gross = $amount ?: (float)$expected;
$log->txn_id = (string)($txid ?? uniqid('paddle_', true));
$log->timeupdated = time();
$log->rawpayload = json_encode($payload);
if ($DB->record_exists('enrol_paddle', ['txn_id' => $log->txn_id])) {
    $log->txn_id = $log->txn_id.'_'.time();
}
$DB->insert_record('enrol_paddle', $log);

// Enrol user with optional period.
if ($plugin_instance->enrolperiod) {
    $timestart = time();
    $timeend   = $timestart + $plugin_instance->enrolperiod;
} else {
    $timestart = 0;
    $timeend   = 0;
}
$plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);

// Notifications.
// Pass $view=true to filter hidden caps if the user cannot see them
if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC', '', '', '', '', false, true)) {
    $users = sort_by_roleassignment_authority($users, $context);
    $teacher = array_shift($users);
} else {
    $teacher = false;
}

$mailstudents = $plugin->get_config('mailstudents');
$mailteachers = $plugin->get_config('mailteachers');
$mailadmins   = $plugin->get_config('mailadmins');
$shortname = format_string($course->shortname, true, array('context' => $context));

if (!empty($mailstudents)) {
    $a = new stdClass();
    $a->coursename = format_string($course->fullname, true, array('context' => $context));
    $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

    $eventdata = new \core\message\message();
    $eventdata->courseid          = $course->id;
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_paddle';
    $eventdata->name              = 'paddle_enrolment';
    $eventdata->userfrom          = empty($teacher) ? core_user::get_noreply_user() : $teacher;
    $eventdata->userto            = $user;
    $eventdata->subject           = get_string('enrolmentnew', 'enrol', $shortname);
    $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    message_send($eventdata);
}

if (!empty($mailteachers) && !empty($teacher)) {
    $a = new stdClass();
    $a->course = format_string($course->fullname, true, array('context' => $context));
    $a->user = fullname($user);

    $eventdata = new \core\message\message();
    $eventdata->courseid          = $course->id;
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_paddle';
    $eventdata->name              = 'paddle_enrolment';
    $eventdata->userfrom          = $user;
    $eventdata->userto            = $teacher;
    $eventdata->subject           = get_string('enrolmentnew', 'enrol', $shortname);
    $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    message_send($eventdata);
}

if (!empty($mailadmins)) {
    $a = new stdClass();
    $a->course = format_string($course->fullname, true, array('context' => $context));
    $a->user = fullname($user);
    $admins = get_admins();
    foreach ($admins as $admin) {
        $eventdata = new \core\message\message();
        $eventdata->courseid          = $course->id;
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_paddle';
        $eventdata->name              = 'paddle_enrolment';
        $eventdata->userfrom          = $user;
        $eventdata->userto            = $admin;
        $eventdata->subject           = get_string('enrolmentnew', 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }
}
