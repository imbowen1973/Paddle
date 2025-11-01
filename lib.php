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
 * Paddle enrolment plugin.
 *
 * This plugin allows you to set up paid courses.
 *
 * @package    enrol_paddle
 * @copyright  2025 Mark Bowen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * Paddle enrolment plugin implementation.
 * @author  Eugene Venter - based on code by Martin Dougiamas and others
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_paddle_plugin extends enrol_plugin {

    public function get_currencies() {
        // Paddle supports standard ISO 4217 currency codes.
        $codes = array(
            'AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'INR', 'JPY',
            'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RUB', 'SEK', 'SGD', 'THB', 'TRY', 'TWD', 'USD');
        $currencies = array();
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }

        return $currencies;
    }

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        $found = false;
        foreach ($instances as $instance) {
            if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
                continue;
            }
            if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
                continue;
            }
            $found = true;
            break;
        }
        if ($found) {
            return array(new pix_icon('icon', get_string('pluginname', 'enrol_paddle'), 'enrol_paddle'));
        }
        return array();
    }

    public function roles_protected() {
        // users with role assign cap may tweak the roles later
        return false;
    }

    public function allow_unenrol(stdClass $instance) {
        // users with unenrol cap may unenrol other users manually - requires enrol/paddle:unenrol
        return true;
    }

    public function allow_manage(stdClass $instance) {
        // users with manage cap may tweak period and status - requires enrol/paddle:manage
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    /**
     * Returns true if the user can add a new instance in this course.
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/paddle:config', $context)) {
            return false;
        }

        // multiple instances supported - different cost for different roles
        return true;
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Add new instance of enrol plugin.
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, ?array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }

    /**
     * Update instance of enrol plugin.
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     */
    public function update_instance($instance, $data) {
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        return parent::update_instance($instance, $data);
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    function enrol_page_hook(stdClass $instance) {
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;

        ob_start();

        if ($DB->record_exists('user_enrolments', array('userid'=>$USER->id, 'enrolid'=>$instance->id))) {
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id'=>$instance->courseid));
        $context = context_course::instance($course->id);

        $shortname = format_string($course->shortname, true, array('context' => $context));
        $strloginto = get_string("loginto", "", $shortname);
        $strcourses = get_string("courses");

        // Pass $view=true to filter hidden caps if the user cannot see them
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        if ( (float) $instance->cost <= 0 ) {
            $cost = (float) $this->get_config('cost');
        } else {
            $cost = (float) $instance->cost;
        }

        if (abs($cost) < 0.01) { // no cost, other enrolment methods (instances) should be used
            echo '<p>'.get_string('nocost', 'enrol_paddle').'</p>';
        } else {

            // Calculate localised and "." cost, make sure we send Paddle the same value,
            // please note Paddle expects amount with 2 decimal places and "." separator.
            $localisedcost = format_float($cost, 2, true);
            $cost = format_float($cost, 2, false);

            if (isguestuser()) { // force login only for guest user, not real users with guest role
                $wwwroot = $CFG->wwwroot;
                echo '<div class="mdl-align"><p>'.get_string('paymentrequired').'</p>';
                echo '<p><b>'.get_string('cost').": $instance->currency $localisedcost".'</b></p>';
                echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
                echo '</div>';
            } else {
                $coursefullname  = format_string($course->fullname, true, array('context'=>$context));
                $courseshortname = $shortname;
                $userfullname    = fullname($USER);
                $userfirstname   = $USER->firstname;
                $userlastname    = $USER->lastname;
                $useraddress     = $USER->address;
                $usercity        = $USER->city;
                $instancename    = $this->get_instance_name($instance);

                $template_data = [
                    'instancename' => $instancename,
                    'currency' => $instance->currency,
                    'localisedcost' => $localisedcost,
                ];
                $PAGE->requires->js_call_amd('enrol_paddle/module', 'init', [[
                    'instanceid' => (int)$instance->id,
                    'environment' => $this->get_config('environment'),
                    'checkoutcreationfailed' => get_string('checkoutcreationfailed', 'enrol_paddle')
                ]]);
                echo $OUTPUT->render_from_template('enrol_paddle/enrol', $template_data);
            }

        }

        return $OUTPUT->box(ob_get_clean());
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = array(
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
                'cost'       => $data->cost,
                'currency'   => $data->currency,
            );
        }
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        return $options;
    }

    /**
     * Return an array of valid options for the roleid.
     *
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    protected function get_roleid_options($instance, $context) {
        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        return $roles;
    }


    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_paddle'), $options);
        $mform->setDefault('status', $this->get_config('status'));

        $mform->addElement('text', 'cost', get_string('cost', 'enrol_paddle'), array('size' => 4));
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', format_float($this->get_config('cost'), 2, true));

        $paddlecurrencies = $this->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_paddle'), $paddlecurrencies);
        $mform->setDefault('currency', $this->get_config('currency'));

        $mform->addElement('text', 'customtext1', get_string('priceid', 'enrol_paddle'), array('size' => 30));
        $mform->setType('customtext1', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('customtext1', 'priceid', 'enrol_paddle');
        $mform->setDefault('customtext1', '');

        $roles = $this->get_roleid_options($instance, $context);
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_paddle'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $options = array('optional' => true, 'defaultunit' => 86400);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_paddle'), $options);
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_paddle');

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_paddle'), $options);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_paddle');

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_paddle'), $options);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_paddle');

        if (enrol_accessing_via_instance($instance)) {
            $warningtext = get_string('instanceeditselfwarningtext', 'core_enrol');
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), $warningtext);
        }
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = array();

        if (!empty($data['enrolenddate']) and $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_paddle');
        }

        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('costerror', 'enrol_paddle');
        }

        $validstatus = array_keys($this->get_status_options());
        $validcurrency = array_keys($this->get_currencies());
        $validroles = array_keys($this->get_roleid_options($instance, $context));
        $tovalidate = array(
            'name' => PARAM_TEXT,
            'status' => $validstatus,
            'currency' => $validcurrency,
            'roleid' => $validroles,
            'enrolperiod' => PARAM_INT,
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT
        );

        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }

    /**
     * Execute synchronisation.
     * @param progress_trace $trace
     * @return int exit code, 0 means ok
     */
    public function sync(progress_trace $trace) {
        $this->process_expirations($trace);
        return 0;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/paddle:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/paddle:config', $context);
    }

    /**
     * External function to get Paddle checkout ID.
     *
     * @param int $instanceid Enrolment instance ID.
     * @return array
     * @throws moodle_exception
     */
    public static function get_checkout_id_external(int $instanceid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_checkout_id_external_parameters(), ['instanceid' => $instanceid]);
        $instanceid = $params['instanceid'];

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

            $curl = new curl();
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

            // Log what we're returning for debugging.
            error_log('PADDLE RETURN VALUE: ' . json_encode($response));

        } catch (moodle_exception $ex) {
            error_log('PADDLE EXCEPTION: ' . $ex->getMessage());
            throw $ex;
        } catch (Exception $ex) {
            error_log('PADDLE GENERIC EXCEPTION: ' . $ex->getMessage());
            throw new moodle_exception('apirequestfailed', 'enrol_paddle', '', $ex->getMessage());
        }

        return $response;
    }

    /**
     * Returns parameters of external function get_checkout_id_external.
     *
     * @return external_function_parameters
     */
    public static function get_checkout_id_external_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Enrolment instance ID'),
        ]);
    }

    /**
     * Returns result of external function get_checkout_id_external.
     *
     * @return external_single_structure
     */
    public static function get_checkout_id_external_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'checkout_id' => new external_value(PARAM_TEXT, 'Paddle checkout ID'),
            'metadata' => new external_value(PARAM_RAW, 'Metadata for the checkout', VALUE_OPTIONAL),
            'debug' => new external_single_structure([
                'endpoint' => new external_value(PARAM_TEXT, 'API endpoint'),
                'payload' => new external_value(PARAM_TEXT, 'Request payload'),
                'response_code' => new external_value(PARAM_TEXT, 'HTTP response code'),
                'response_body' => new external_value(PARAM_TEXT, 'Response body'),
            ], 'Debug information', VALUE_OPTIONAL),
        ]);
    }

}

