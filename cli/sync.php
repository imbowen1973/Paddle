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
 * Paddle CLI sync tool.
 *
 * @package    enrol_paddle
 * @copyright  2025 Mark Bowen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params([
    'verbose' => false,
    'help' => false,
], ['v' => 'verbose', 'h' => 'help']);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if (!empty($options['help'])) {
    $help = <<<EOF
Process Paddle enrolment expiration sync

Options:
-v, --verbose         Print verbose progress information
-h, --help            Print out this help

Example:
$ sudo -u www-data /usr/bin/php enrol/paddle/cli/sync.php

EOF;

    echo $help;
    exit(0);
}

if (!enrol_is_enabled('paddle')) {
    echo "enrol_paddle plugin is disabled\n";
    exit(2);
}

$trace = empty($options['verbose']) ? new null_progress_trace() : new text_progress_trace();

/** @var enrol_paddle_plugin $plugin */
$plugin = enrol_get_plugin('paddle');

$result = $plugin->sync($trace);

exit($result);
