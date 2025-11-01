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
 * External services for enrol_paddle.
 *
 * @package    enrol_paddle
 * @copyright  2025 Mark Bowen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'enrol_paddle_get_checkout_id' => array(
        'classname'   => 'enrol_paddle\external\get_checkout_id',
        'methodname'  => 'execute',
        'description' => 'Get Paddle checkout ID',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    )
);
