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
 * Post-checkout handler for Paddle enrolments.
 *
 * @package    enrol_paddle
 */

require('../../config.php');

$courseid = required_param('id', PARAM_INT);

if (!enrol_is_enabled('paddle')) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

require_login();

if (!empty($SESSION->wantsurl)) {
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} else {
    $destination = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
}

$fullname = format_string($course->fullname, true, ['context' => $context]);

if (is_enrolled($context, null, '', true)) {
    redirect($destination, get_string('paymentthanks', '', $fullname));
}

$PAGE->set_url($destination);
echo $OUTPUT->header();
$a = (object) [
    'teacher' => get_string('defaultcourseteacher'),
    'fullname' => $fullname,
];
notice(get_string('paymentsorry', '', $a), $destination);
