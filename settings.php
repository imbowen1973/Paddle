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
 * Paddle enrolments plugin settings and presets.
 *
 * @package    enrol_paddle
 * @copyright  2025 Mark Bowen
 * @author     2025 Mark Bowen <
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_paddle_settings', '', get_string('pluginname_desc', 'enrol_paddle')));

    // Paddle configuration.
    $settings->add(new admin_setting_configselect('enrol_paddle/environment', get_string('environment', 'enrol_paddle'), get_string('environment_desc', 'enrol_paddle'), 'live', [
        'live' => get_string('environment_live', 'enrol_paddle'),
        'sandbox' => get_string('environment_sandbox', 'enrol_paddle'),
    ]));

    $settings->add(new admin_setting_configtext('enrol_paddle/live_api_url', get_string('liveapiurl', 'enrol_paddle'), get_string('liveapiurl_desc', 'enrol_paddle'), 'https://api.paddle.com', PARAM_URL));

    $settings->add(new admin_setting_configtext('enrol_paddle/sandbox_api_url', get_string('sandboxapiurl', 'enrol_paddle'), get_string('sandboxapiurl_desc', 'enrol_paddle'), 'https://sandbox-api.paddle.com', PARAM_URL));

    $settings->add(new admin_setting_configtext('enrol_paddle/api_key', get_string('apikey', 'enrol_paddle'), get_string('apikey_desc', 'enrol_paddle'), '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('enrol_paddle/integration_id', get_string('integrationid', 'enrol_paddle'), get_string('integrationid_desc', 'enrol_paddle'), '', PARAM_RAW_TRIMMED));

    // Paddle Billing API webhook settings.
    $settings->add(new admin_setting_configselect('enrol_paddle/webhook_hmac_algo', get_string('webhookhmac', 'enrol_paddle'), get_string('webhookhmac_desc', 'enrol_paddle'), 'sha256', [
        'sha256' => 'HMAC-SHA256',
        'sha512' => 'HMAC-SHA512',
    ]));

    $settings->add(new admin_setting_configtext('enrol_paddle/webhook_secret', get_string('webhooksecret', 'enrol_paddle'), get_string('webhooksecret_desc', 'enrol_paddle'), '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configcheckbox('enrol_paddle/mailstudents', get_string('mailstudents', 'enrol_paddle'), '', 0));
    $settings->add(new admin_setting_configcheckbox('enrol_paddle/mailteachers', get_string('mailteachers', 'enrol_paddle'), '', 0));
    $settings->add(new admin_setting_configcheckbox('enrol_paddle/mailadmins', get_string('mailadmins', 'enrol_paddle'), '', 0));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    //       it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_paddle/expiredaction', get_string('expiredaction', 'enrol_paddle'), get_string('expiredaction_help', 'enrol_paddle'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_paddle_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_paddle/status',
        get_string('status', 'enrol_paddle'), get_string('status_desc', 'enrol_paddle'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_paddle/cost', get_string('cost', 'enrol_paddle'), '', 0, PARAM_FLOAT, 4));

    $paddlecurrencies = enrol_get_plugin('paddle')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_paddle/currency', get_string('currency', 'enrol_paddle'), '', 'USD', $paddlecurrencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_paddle/roleid',
            get_string('defaultrole', 'enrol_paddle'),
            get_string('defaultrole_desc', 'enrol_paddle'),
            $student->id ?? null,
            $options));
    }

    $settings->add(new admin_setting_configduration('enrol_paddle/enrolperiod',
        get_string('enrolperiod', 'enrol_paddle'), get_string('enrolperiod_desc', 'enrol_paddle'), 0));
}
