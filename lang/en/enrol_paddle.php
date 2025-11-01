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
 * Strings for component 'enrol_paddle', language 'en'.
 *
 * @package    enrol_paddle
 */

$string['assignrole'] = 'Assign role';
$string['cost'] = 'Enrol cost';
$string['costerror'] = 'The enrolment cost is not numeric';
$string['currency'] = 'Currency';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during Paddle enrolments';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['errdisabled'] = 'The Paddle enrolment plugin is disabled and does not handle webhook notifications.';
$string['erripninvalid'] = 'Webhook signature verification failed for the Paddle notification.';
$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['paddle:config'] = 'Configure Paddle enrol instances';
$string['paddle:manage'] = 'Manage enrolled users';
$string['paddle:unenrol'] = 'Unenrol users from course';
$string['paddle:unenrolself'] = 'Unenrol self from the course';
$string['messageprovider:paddle_enrolment'] = 'Paddle enrolment messages';
$string['nocost'] = 'There is no cost associated with enrolling in this course!';
$string['pluginname'] = 'Paddle';
$string['pluginname_desc'] = 'The Paddle module allows you to set up paid courses. If the cost for any course is zero, then students are not asked to pay for entry. There is a site-wide cost as a default for the whole site and a course setting that you can set for each course individually.';
$string['processexpirationstask'] = 'Paddle enrolment send expiry notifications task';
$string['sendpaymentbutton'] = 'Pay with Paddle';
$string['status'] = 'Allow Paddle enrolments';
$string['status_desc'] = 'Allow users to use Paddle to enrol into a course by default.';
$string['transactions'] = 'Transactions';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';

// Settings: Paddle specifics.
$string['liveapiurl'] = 'Live API URL';
$string['liveapiurl_desc'] = 'The URL for the Paddle Live API.';
$string['sandboxapiurl'] = 'Sandbox API URL';
$string['sandboxapiurl_desc'] = 'The URL for the Paddle Sandbox API.';
$string['webhookhmac'] = 'Webhook HMAC algorithm';
$string['webhookhmac_desc'] = 'Select the HMAC digest algorithm for webhook signature verification.';
$string['webhooksecret'] = 'Webhook secret key';
$string['webhooksecret_desc'] = 'HMAC secret key for verifying Paddle Billing webhook signatures. Found in your Paddle dashboard under Notifications.';
$string['environment'] = 'Paddle environment';
$string['environment_desc'] = 'Select which Paddle environment to use for API calls and checkout sessions.';
$string['environment_live'] = 'Live';
$string['environment_sandbox'] = 'Sandbox';
$string['apikey'] = 'Paddle API key';
$string['apikey_desc'] = 'Server-side API key used to create checkout sessions via the Paddle REST API.';
$string['integrationid'] = 'Integration identifier';
$string['integrationid_desc'] = 'Optional Paddle-Integration-Identifier header value (helps Paddle identify your integration).';
$string['priceid'] = 'Default Paddle Price ID';
$string['priceid_desc'] = 'The Paddle Price ID to use for checkouts (e.g., pri_01xxxxx). Create prices in your Paddle dashboard under Catalog → Products. You can override this per-course in the enrollment instance settings.';
$string['paymentinstant'] = 'You will be enrolled immediately after payment is confirmed.';

// REST checkout / errors.
$string['missingapikey'] = 'Paddle API key has not been configured.';
$string['missingpriceid'] = 'Paddle Price ID has not been configured. Please set a Price ID in the plugin settings or enrollment instance.';
$string['checkoutcreationfailed'] = 'Unable to create a Paddle checkout session. Please try again or contact support.';
$string['apirequestfailed'] = 'Paddle API request failed: {$a}';

// Privacy metadata.
$string['privacy:metadata:enrol_paddle:api'] = 'The Paddle enrolment plugin sends user information to the Paddle API to create checkout sessions.';
$string['privacy:metadata:enrol_paddle:api:email'] = 'Email address of the purchaser is shared with Paddle to prefill checkout details.';
$string['privacy:metadata:enrol_paddle:api:name'] = 'The purchaser name is sent to Paddle to personalise receipts.';
$string['privacy:metadata:enrol_paddle:api:metadata'] = 'Metadata containing Moodle user ID, course ID, and enrolment instance ID is provided to reconcile the payment.';
$string['privacy:metadata:enrol_paddle:enrol_paddle'] = 'Information about the Paddle transactions for Paddle enrolments.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:event'] = 'The type of Paddle webhook event recorded.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:courseid'] = 'The ID of the course purchased.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:userid'] = 'The ID of the user who purchased the course enrolment.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:instanceid'] = 'The enrolment instance ID associated with the transaction.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:payment_currency'] = 'The currency used for the transaction.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:payment_gross'] = 'The total amount paid for the transaction.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:txn_id'] = 'The Paddle transaction identifier.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:rawpayload'] = 'Raw JSON payload received from Paddle for auditing purposes.';
$string['privacy:metadata:enrol_paddle:enrol_paddle:timeupdated'] = 'Timestamp of when Moodle processed the Paddle webhook.';
