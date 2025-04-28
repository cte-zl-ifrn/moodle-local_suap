<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     local_suap
 * @category    upgrade
 * @copyright   2022 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/upgradelib.php');

function xmldb_local_suap_upgrade($oldversion) {
    suap_bulk_course_custom_field();
    suap_bulk_user_custom_field();

    global $DB;

    if ($oldversion < 2025_04_28_003) {
        $fields = $DB->get_records('customfield_field', ['shortname' => 'carga_horaria']);

        foreach ($fields as $field) {
            $field->type = 'text';
            $DB->update_record('customfield_field', $field);
        }
        upgrade_plugin_savepoint(true, 2025_04_28_003, 'local', 'suap');
    }


    return local_suap_migrate($oldversion);
}

