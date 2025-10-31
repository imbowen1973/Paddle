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
 * Upgrade steps for the enrol_paddle plugin.
 *
 * @copyright  2025 Mark Bowen
 *
 * @package    enrol_paddle
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrades for the Paddle enrolment plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_enrol_paddle_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // New version.php requires 2025102700.
    // This is the initial install, so no upgrade steps are needed yet.

    return true;
}

