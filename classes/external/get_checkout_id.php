<?php
// TEST VERSION - returns immediately
defined('MOODLE_INTERNAL') || die();

namespace enrol_paddle\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

class get_checkout_id extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Enrolment instance ID'),
        ]);
    }

    public static function execute(int $instanceid): array {
        // IMMEDIATE RETURN - test if function is called
        return [
            'success' => false,
            'checkout_id' => '',
            'error' => 'TEST MODE: Function executed with instanceid=' . $instanceid,
            'debug' => [
                'console_log' => 'TEST: Function IS being called! ID=' . $instanceid,
                'endpoint' => 'TEST',
                'payload' => 'TEST',
                'response_code' => 'TEST',
                'response_body' => 'TEST'
            ]
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'checkout_id' => new external_value(PARAM_TEXT, 'Paddle checkout ID'),
            'metadata' => new external_value(PARAM_TEXT, 'Metadata for the checkout', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
            'debug' => new external_single_structure([
                'console_log' => new external_value(PARAM_RAW, 'Console debug log'),
                'endpoint' => new external_value(PARAM_TEXT, 'API endpoint'),
                'payload' => new external_value(PARAM_TEXT, 'Request payload'),
                'response_code' => new external_value(PARAM_TEXT, 'HTTP response code'),
                'response_body' => new external_value(PARAM_TEXT, 'Response body'),
            ], 'Debug information'),
        ]);
    }
}
