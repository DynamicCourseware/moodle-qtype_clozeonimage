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

    if ($oldversion < 2026101000) {
        $table = new xmldb_table('qtype_clozeonimage_pos');
        $fields = [
            new xmldb_field('content', XMLDB_TYPE_TEXT, null, null, null),
            new xmldb_field('contentformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1'),
            new xmldb_field('width', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '320'),
            new xmldb_field('slotcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        // Existing positions remain ordinary one-expression blocks; no question data is rewritten.
        upgrade_plugin_savepoint(true, 2026101000, 'qtype', 'clozeonimage');
    }

    if ($oldversion < 2026101100) {
        $table = new xmldb_table('qtype_clozeonimage');
        foreach (['introcount', 'aftercount'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026101100, 'qtype', 'clozeonimage');
    }

    if ($oldversion < 2026101101) {
        $table = new xmldb_table('qtype_clozeonimage');
        $field = new xmldb_field('showcomposition', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101101, 'qtype', 'clozeonimage');
    }

    if ($oldversion < 2026101102) {
        $table = new xmldb_table('qtype_clozeonimage');
        $field = new xmldb_field('blockappearance', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '2');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101102, 'qtype', 'clozeonimage');
    }

    if ($oldversion < 2026101700) {
        // Add the new format without converting, deleting or rewriting old question contents.
        $table = new xmldb_table('qtype_clozeonimage');
        foreach ([new xmldb_field('formatversion', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1'),
                new xmldb_field('composition', XMLDB_TYPE_TEXT, null, null, null)] as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $table = new xmldb_table('qtype_clozeonimage_pos');
        foreach (['elementkey', 'relativeimage'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach (['relativex', 'relativey'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_NUMBER, '20,8', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $field = new xmldb_field('layer', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '100');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('qtype_clozeonimage_img');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('elementkey', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        foreach (['xleft', 'ytop', 'width', 'height'] as $name) {
            $table->add_field($name, XMLDB_TYPE_NUMBER, '20,8', null, XMLDB_NOTNULL, null, '0');
        }
        $table->add_field('layer', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
        $table->add_index('question-key', XMLDB_INDEX_UNIQUE, ['questionid', 'elementkey']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026101700, 'qtype', 'clozeonimage');
    }

    if ($oldversion < 2026101701) {
        // Preserve subpixel anchor positions when image associations are changed or detached.
        $table = new xmldb_table('qtype_clozeonimage_pos');
        foreach (['xleft', 'ytop'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_NUMBER, '20,8', null, XMLDB_NOTNULL, null, '0');
            $dbman->change_field_type($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101701, 'qtype', 'clozeonimage');
    }
    if ($oldversion < 2026101702) {
        // Retain calculated block subpixels separately from the integer-only authoring display.
        $table = new xmldb_table('qtype_clozeonimage_pos');
        $field = new xmldb_field('width', XMLDB_TYPE_NUMBER, '20,8', null, XMLDB_NOTNULL, null, '320');
        $dbman->change_field_type($table, $field);
        upgrade_plugin_savepoint(true, 2026101702, 'qtype', 'clozeonimage');
    }
    if ($oldversion < 2026101900) {
        $table = new xmldb_table('qtype_clozeonimage_pos');
        foreach (['stickytop', 'stickyhorizontal'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026101900, 'qtype', 'clozeonimage');
    }
    if ($oldversion < 2026101902) {
        $table = new xmldb_table('qtype_clozeonimage_pos');
        $field = new xmldb_field('stickybottom', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101902, 'qtype', 'clozeonimage');
    }
    return true;
}
