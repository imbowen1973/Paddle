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
 * Paddle enrolment plugin - support for user self unenrolment.
 *
 * @package    enrol_paddle
 */

require('../../config.php');

$enrolid = required_param('enrolid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'paddle'], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login();
require_login($course);

if (!is_enrolled($context, $USER)) {
    redirect(new moodle_url('/'));
}

/** @var enrol_paddle_plugin $plugin */
$plugin = enrol_get_plugin('paddle');

if (!$plugin->get_unenrolself_link($instance)) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

$PAGE->set_url('/enrol/paddle/unenrolself.php', ['enrolid' => $instance->id]);
$PAGE->set_title($plugin->get_instance_name($instance));

if ($confirm && confirm_sesskey()) {
    $plugin->unenrol_user($instance, $USER->id);
    redirect(new moodle_url('/index.php'));
}

echo $OUTPUT->header();
$yesurl = new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]);
$nourl = new moodle_url('/course/view.php', ['id' => $course->id]);
$message = get_string('unenrolselfconfirm', 'enrol_paddle', format_string($course->fullname));
echo $OUTPUT->confirm($message, $yesurl, $nourl);
echo $OUTPUT->footer();
