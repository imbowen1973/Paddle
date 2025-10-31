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

namespace enrol_paddle\privacy;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy subsystem implementation for enrol_paddle.
 *
 * @copyright  2025 Mark Bowen
 */
class provider implements \core_privacy\local\metadata\provider, plugin_provider, core_userlist_provider {

    /**
     * Returns metadata about the data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'api.paddle.com',
            [
                'email'    => 'privacy:metadata:enrol_paddle:api:email',
                'name'     => 'privacy:metadata:enrol_paddle:api:name',
                'metadata' => 'privacy:metadata:enrol_paddle:api:metadata',
            ],
            'privacy:metadata:enrol_paddle:api'
        );

        $collection->add_database_table(
            'enrol_paddle',
            [
                'event'            => 'privacy:metadata:enrol_paddle:enrol_paddle:event',
                'courseid'         => 'privacy:metadata:enrol_paddle:enrol_paddle:courseid',
                'userid'           => 'privacy:metadata:enrol_paddle:enrol_paddle:userid',
                'instanceid'       => 'privacy:metadata:enrol_paddle:enrol_paddle:instanceid',
                'payment_currency' => 'privacy:metadata:enrol_paddle:enrol_paddle:payment_currency',
                'payment_gross'    => 'privacy:metadata:enrol_paddle:enrol_paddle:payment_gross',
                'txn_id'           => 'privacy:metadata:enrol_paddle:enrol_paddle:txn_id',
                'rawpayload'       => 'privacy:metadata:enrol_paddle:enrol_paddle:rawpayload',
                'timeupdated'      => 'privacy:metadata:enrol_paddle:enrol_paddle:timeupdated',
            ],
            'privacy:metadata:enrol_paddle:enrol_paddle'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {enrol_paddle} ep
                  JOIN {enrol} e ON ep.instanceid = e.id
                  JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
                 WHERE ep.userid = :userid";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if ($contextlist->is_empty()) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }

            $records = $DB->get_records('enrol_paddle', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);

            if (!$records) {
                continue;
            }

            $transactions = [];
            foreach ($records as $record) {
                $transactions[] = (object) [
                    'event' => $record->event,
                    'instanceid' => $record->instanceid,
                    'payment_currency' => $record->payment_currency,
                    'payment_gross' => $record->payment_gross,
                    'txn_id' => $record->txn_id,
                    'timeupdated' => \core_privacy\local\request\transform::datetime($record->timeupdated),
                    'rawpayload' => $record->rawpayload,
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('transactions', 'enrol_paddle')],
                (object) ['transactions' => $transactions]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof context_course) {
            return;
        }

        $DB->delete_records('enrol_paddle', ['courseid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if ($contextlist->is_empty()) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        $courseids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                $courseids[] = $context->instanceid;
            }
        }

        if (empty($courseids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;

        $DB->delete_records_select('enrol_paddle', "userid = :userid AND courseid $insql", $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        $params = ['courseid' => $context->instanceid] + $userparams;

        $DB->delete_records_select('enrol_paddle', "courseid = :courseid AND userid $usersql", $params);
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to add entries to.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $sql = "SELECT userid
                  FROM {enrol_paddle}
                 WHERE courseid = :courseid";
        $params = ['courseid' => $context->instanceid];

        $userlist->add_from_sql('userid', $sql, $params);
    }
}
