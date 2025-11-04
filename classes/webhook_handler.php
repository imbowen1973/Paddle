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
 * Webhook handler for Paddle events.
 *
 * @package    enrol_paddle
 * @copyright  2025 Mark Bowen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paddle;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles webhook notifications from Paddle.
 */
class webhook_handler {

    /**
     * Transaction-related events that we process.
     */
    const TRANSACTION_EVENTS = [
        'transaction.completed',
        'transaction.paid',
        'transaction.payment_failed',
        'checkout.completed',
        'order.completed',
        'subscription.created',
        'subscription.updated',
        'subscription.activated',
        'subscription.past_due',
        'subscription.paused',
        'subscription.resumed',
        'subscription.canceled',
    ];

    /**
     * Process webhook notification from Paddle.
     *
     * @param string $payload Raw POST data
     * @param string $signature Paddle signature from headers
     * @return array Response array with status and message
     */
    public function process_webhook(string $payload, string $signature): array {
        global $DB;

        // Validate inputs.
        if (empty($payload)) {
            return [
                'status' => 'error',
                'message' => 'Missing payload'
            ];
        }

        // Decode the payload.
        $data = json_decode($payload);
        if (!$data) {
            return [
                'status' => 'error',
                'message' => 'Invalid JSON payload'
            ];
        }

        // Get event name.
        $eventname = $data->event_type ?? '';
        if (empty($eventname)) {
            return [
                'status' => 'error',
                'message' => 'Missing event type'
            ];
        }

        // Check if this is a transaction event we need to process.
        if (!in_array($eventname, self::TRANSACTION_EVENTS)) {
            // Acknowledge non-transaction events (like product.created, api_key.created).
            return [
                'status' => 'success',
                'message' => 'Event acknowledged but not processed: ' . $eventname
            ];
        }

        // Verify signature if webhook secret is configured.
        $plugin = enrol_get_plugin('paddle');
        $webhooksecret = $plugin->get_config('webhook_secret');

        if (!empty($webhooksecret)) {
            $verified = $this->verify_signature($payload, $signature, $webhooksecret);
            if (!$verified) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid signature'
                ];
            }
        }

        // Process the event.
        try {
            $result = $this->handle_transaction_event($data, $eventname);
            return $result;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error processing event: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify Paddle webhook signature.
     *
     * @param string $payload Raw POST data
     * @param string $signature Paddle signature from headers
     * @param string $secret Webhook secret key
     * @return bool True if signature is valid
     */
    private function verify_signature(string $payload, string $signature, string $secret): bool {
        if (empty($signature) || empty($secret)) {
            return false;
        }

        // Paddle signature format: ts=timestamp;h1=hash
        $parts = [];
        foreach (explode(';', $signature) as $part) {
            list($key, $value) = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        if (!isset($parts['ts']) || !isset($parts['h1'])) {
            return false;
        }

        $timestamp = $parts['ts'];
        $providedHash = $parts['h1'];

        // Construct the signed payload.
        $signedPayload = $timestamp . ':' . $payload;

        // Calculate expected hash.
        $expectedHash = hash_hmac('sha256', $signedPayload, $secret);

        // Compare hashes.
        return hash_equals($expectedHash, $providedHash);
    }

    /**
     * Handle transaction-related events.
     *
     * @param object $data Webhook data
     * @param string $eventname Event name
     * @return array Response array
     */
    private function handle_transaction_event($data, string $eventname): array {
        global $DB;

        // Extract transaction/order data.
        $transactionData = $data->data ?? null;
        if (!$transactionData) {
            return [
                'status' => 'error',
                'message' => 'Missing transaction data'
            ];
        }

        // Get custom data (contains our metadata).
        $customData = $transactionData->custom_data ?? null;
        if (!$customData) {
            return [
                'status' => 'error',
                'message' => 'Missing custom data'
            ];
        }

        // Extract enrollment information.
        $userid = $customData->userid ?? null;
        $courseid = $customData->courseid ?? null;
        $instanceid = $customData->instanceid ?? null;

        if (!$userid || !$courseid || !$instanceid) {
            return [
                'status' => 'error',
                'message' => 'Missing enrollment metadata (userid, courseid, or instanceid)'
            ];
        }

        // Handle different event types.
        switch ($eventname) {
            case 'transaction.completed':
            case 'transaction.paid':
            case 'checkout.completed':
            case 'order.completed':
                return $this->enrol_user_in_course($userid, $courseid, $instanceid, $transactionData);

            case 'subscription.created':
            case 'subscription.activated':
                return $this->enrol_user_in_course($userid, $courseid, $instanceid, $transactionData);

            case 'subscription.canceled':
                return $this->unenrol_user_from_course($userid, $courseid, $instanceid);

            default:
                return [
                    'status' => 'success',
                    'message' => 'Event acknowledged: ' . $eventname
                ];
        }
    }

    /**
     * Enrol user in course.
     *
     * @param int $userid Moodle user ID
     * @param int $courseid Moodle course ID
     * @param int $instanceid Enrolment instance ID
     * @param object $transactionData Transaction data from Paddle
     * @return array Response array
     */
    private function enrol_user_in_course(int $userid, int $courseid, int $instanceid, $transactionData): array {
        global $DB;

        // Verify the user exists.
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'User not found: ' . $userid
            ];
        }

        // Verify the course exists.
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return [
                'status' => 'error',
                'message' => 'Course not found: ' . $courseid
            ];
        }

        // Verify the enrolment instance exists.
        $instance = $DB->get_record('enrol', [
            'id' => $instanceid,
            'enrol' => 'paddle',
            'courseid' => $courseid
        ]);
        if (!$instance) {
            return [
                'status' => 'error',
                'message' => 'Enrolment instance not found: ' . $instanceid
            ];
        }

        // Check if user is already enrolled.
        $existingEnrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $instanceid,
            'userid' => $userid
        ]);

        $plugin = enrol_get_plugin('paddle');

        if ($existingEnrolment) {
            // Update existing enrolment.
            $plugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);

            return [
                'status' => 'success',
                'message' => 'User enrolment updated successfully'
            ];
        } else {
            // Enrol the user.
            $timestart = time();
            $timeend = 0;

            // Calculate end date if enrolment period is set.
            if ($instance->enrolperiod) {
                $timeend = $timestart + $instance->enrolperiod;
            }

            $plugin->enrol_user($instance, $userid, $instance->roleid, $timestart, $timeend);

            return [
                'status' => 'success',
                'message' => 'User enrolled successfully'
            ];
        }
    }

    /**
     * Unenrol user from course.
     *
     * @param int $userid Moodle user ID
     * @param int $courseid Moodle course ID
     * @param int $instanceid Enrolment instance ID
     * @return array Response array
     */
    private function unenrol_user_from_course(int $userid, int $courseid, int $instanceid): array {
        global $DB;

        // Verify the enrolment instance exists.
        $instance = $DB->get_record('enrol', [
            'id' => $instanceid,
            'enrol' => 'paddle',
            'courseid' => $courseid
        ]);

        if (!$instance) {
            return [
                'status' => 'error',
                'message' => 'Enrolment instance not found: ' . $instanceid
            ];
        }

        $plugin = enrol_get_plugin('paddle');
        $plugin->unenrol_user($instance, $userid);

        return [
            'status' => 'success',
            'message' => 'User unenrolled successfully'
        ];
    }
}
