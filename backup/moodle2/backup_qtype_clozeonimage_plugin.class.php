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
 * Backup support for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information needed to back up Cloze on Image questions.
 */
class backup_qtype_clozeonimage_plugin extends backup_qtype_plugin {
    /**
     * Define the qtype-specific backup structure.
     *
     * @return backup_plugin_element
     */
    protected function define_question_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../qtype', 'clozeonimage');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $options = new backup_nested_element('clozeonimage', ['id'], [
            'questionid',
            'sequence',
            'displaymode',
            'displaywidth',
            'controlappearance',
            'aftertext',
            'aftertextformat',
        ]);
        $positions = new backup_nested_element('positions');
        $position = new backup_nested_element('position', ['id'], [
            'questionid',
            'no',
            'xleft',
            'ytop',
            'anchor',
        ]);

        $pluginwrapper->add_child($options);
        $pluginwrapper->add_child($positions);
        $positions->add_child($position);

        $options->set_source_table(
            'qtype_clozeonimage',
            ['questionid' => backup::VAR_PARENTID]
        );
        $position->set_source_table(
            'qtype_clozeonimage_pos',
            ['questionid' => backup::VAR_PARENTID],
            'no ASC'
        );

        return $plugin;
    }

    /**
     * Return the file areas used by this question type.
     *
     * @return array
     */
    public static function get_qtype_fileareas() {
        return [
            'bgimage' => 'question_created',
            'aftertext' => 'question_created',
        ];
    }
}
