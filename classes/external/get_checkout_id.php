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
 * External API for getting Paddle checkout ID.
 *
 * @package    enrol_paddle
 * @copyright  2025 Mark Bowen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

namespace enrol_paddle\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_course;
use moodle_url;
use moodle_exception;

/**
 * External function to get Paddle checkout ID.
 */
class get_checkout_id extends external_api {

    /**
     * Returns parameters for execute method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Enrolment instance ID'),
        ]);
    }

    /**
     * Get Paddle checkout ID for enrollment.
     *
     * @param int $instanceid Enrolment instance ID.
     * @return array
     * @throws moodle_exception
     */
    public static function execute(int $instanceid): array {
        global $DB, $USER;

        try {
            $params = self::validate_parameters(self::execute_parameters(), ['instanceid' => $instanceid]);
            $instanceid = $params['instanceid'];
        } catch (\invalid_parameter_exception $e) {
            throw new moodle_exception('invalidparameter', 'enrol_paddle');
        }

        $response = ['success' => false];

        try {
            $plugin = enrol_get_plugin('paddle');
            if (!$plugin) {
                throw new moodle_exception('errdisabled', 'enrol_paddle');
            }

            $instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'paddle', 'status' => ENROL_INSTANCE_ENABLED], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
            $context = context_course::instance($course->id, MUST_EXIST);

            // Determine course cost and currency.
            if ((float)$instance->cost <= 0) {
                $cost = (float)$plugin->get_config('cost');
            } else {
                $cost = (float)$instance->cost;
            }

            $cost = format_float($cost, 2, false);
            if ($cost <= 0) {
                throw new moodle_exception('nocost', 'enrol_paddle');
            }

            $currency = $instance->currency ?: (string)$plugin->get_config('currency', 'USD');

            $apikey = trim((string)$plugin->get_config('api_key'));
            if (empty($apikey)) {
                throw new moodle_exception('missingapikey', 'enrol_paddle');
            }

            $environment = (string)$plugin->get_config('environment', 'live');
            $apiendpoint = $environment === 'sandbox' ? $plugin->get_config('sandbox_api_url') : $plugin->get_config('live_api_url');
            $checkoutendpoint = $apiendpoint.'/checkouts';

            $metadata = [
                'userid' => $USER->id,
                'courseid' => $course->id,
                'instanceid' => $instance->id,
            ];

            $coursefullname = format_string($course->fullname, true, ['context' => $context]);

            // Get Price ID from instance or global config.
            $priceid = trim((string)$instance->customtext1);
            if (empty($priceid)) {
                $priceid = trim((string)$plugin->get_config('price_id'));
            }
            if (empty($priceid)) {
                throw new moodle_exception('missingpriceid', 'enrol_paddle');
            }

            $payload = [
                'items' => [[
                    'price_id' => $priceid,
                    'quantity' => 1,
                ]],
                'customer' => [
                    'email' => $USER->email,
                ],
                'custom_data' => $metadata,
                'checkout_data' => [
                    'success_url' => (new moodle_url('/enrol/paddle/return.php', ['id' => $course->id]))->out(false),
                    'cancel_url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                ],
            ];

            $curl = new \curl();
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer '.$apikey,
            ];
            $integrationid = trim((string)$plugin->get_config('integration_id', ''));
            if ($integrationid !== '') {
                $headers[] = 'Paddle-Integration-Identifier: '.$integrationid;
            }

            $options = [
                'CURLOPT_HTTPHEADER' => $headers,
                'timeout' => 30,
            ];

            // Debug logging if enabled.
            if ($plugin->get_config('debug_mode')) {
                error_log('PADDLE DEBUG: API Request to ' . $checkoutendpoint);
                error_log('PADDLE DEBUG: Headers: ' . json_encode($headers));
                error_log('PADDLE DEBUG: Payload: ' . json_encode($payload, JSON_PRETTY_PRINT));
            }

            $responsebody = $curl->post($checkoutendpoint, json_encode($payload), $options);

            // Debug logging if enabled.
            if ($plugin->get_config('debug_mode')) {
                error_log('PADDLE DEBUG: HTTP Code: ' . ($curl->get_info()['http_code'] ?? 'unknown'));
                error_log('PADDLE DEBUG: Response: ' . $responsebody);
            }

            if ($curl->get_errno()) {
                throw new moodle_exception('apirequestfailed', 'enrol_paddle', '', $curl->error);
            }

            $json = json_decode($responsebody);
            if (!$json) {
                throw new moodle_exception('apirequestfailed', 'enrol_paddle', '', 'Invalid JSON response');
            }

            $checkoutid = null;
            if (isset($json->data) && isset($json->data->id)) {
                $checkoutid = $json->data->id;
            } else if (isset($json->id)) {
                $checkoutid = $json->id;
            }

            if (empty($checkoutid)) {
                $errorcode = isset($json->error) ? json_encode($json->error) : $responsebody;
                throw new moodle_exception('apirequestfailed', 'enrol_paddle', '', $errorcode);
            }

            $response = [
                'success' => true,
                'checkout_id' => $checkoutid,
                'metadata' => json_encode($metadata),
            ];

            // Add debug info if enabled.
            if ($plugin->get_config('debug_mode')) {
                $response['debug'] = [
                    'endpoint' => $checkoutendpoint,
                    'payload' => json_encode($payload),
                    'response_code' => (string)($curl->get_info()['http_code'] ?? 'unknown'),
                    'response_body' => $responsebody,
                ];
            }

        } catch (moodle_exception $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new moodle_exception('apirequestfailed', 'enrol_paddle', '', $ex->getMessage());
        }

        return $response;
    }

    /**
     * Returns result structure for execute method.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'checkout_id' => new external_value(PARAM_TEXT, 'Paddle checkout ID'),
            'metadata' => new external_value(PARAM_TEXT, 'Metadata for the checkout', VALUE_OPTIONAL),
            'debug' => new external_single_structure([
                'endpoint' => new external_value(PARAM_TEXT, 'API endpoint'),
                'payload' => new external_value(PARAM_TEXT, 'Request payload'),
                'response_code' => new external_value(PARAM_TEXT, 'HTTP response code'),
                'response_body' => new external_value(PARAM_TEXT, 'Response body'),
            ], 'Debug information', VALUE_OPTIONAL),
        ]);
    }
}
