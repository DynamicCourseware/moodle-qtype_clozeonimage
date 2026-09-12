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
 * Upgrade steps for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade qtype_clozeonimage.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_qtype_clozeonimage_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090300) {
        $table = new xmldb_table('qtype_clozeonimage_pos');
        $field = new xmldb_field('anchor', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '4', 'ytop');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090300, 'qtype', 'clozeonimage');
    }

    if ($oldversion < 2026091000) {
        $table = new xmldb_table('qtype_clozeonimage');
        $field = new xmldb_field(
            'controlappearance',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'displaywidth'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091000, 'qtype', 'clozeonimage');
    }

    return true;
}
