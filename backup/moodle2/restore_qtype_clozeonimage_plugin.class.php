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
 * Restore support for qtype_clozeonimage.
 *
 * Adapted from Moodle core question/type/multianswer/backup/moodle2/restore_qtype_multianswer_plugin.class.php.
 * Modifications for Cloze on Image copyright 2026 DynamicCourseware.org.
 *
 * @package    qtype_clozeonimage
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores Cloze on Image options, positions, child references and responses.
 */
class restore_qtype_clozeonimage_plugin extends restore_qtype_plugin {
    /**
     * Define the paths handled at question level.
     *
     * @return restore_path_element[]
     */
    protected function define_question_plugin_structure() {
        return [
            new restore_path_element('clozeonimage', $this->get_pathfor('/clozeonimage')),
            new restore_path_element('clozeonimage_position', $this->get_pathfor('/positions/position')),
        ];
    }

    /**
     * Restore the main options record.
     *
     * Sequence IDs are recoded after all questions have been restored.
     *
     * @param array $data Backup data.
     */
    public function process_clozeonimage($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $oldquestionid = $this->get_old_parentid('question');
        $questioncreated = (bool) $this->get_mappingid('question_created', $oldquestionid);

        if ($questioncreated) {
            $data->questionid = $this->get_new_parentid('question');
            $newitemid = $DB->insert_record('qtype_clozeonimage', $data);
            $this->set_mapping('qtype_clozeonimage', $oldid, $newitemid);
        }
    }

    /**
     * Restore one position record unchanged against the restored parent.
     *
     * @param array $data Backup data.
     */
    public function process_clozeonimage_position($data) {
        global $DB;

        $data = (object) $data;
        $oldquestionid = $this->get_old_parentid('question');
        $questioncreated = (bool) $this->get_mappingid('question_created', $oldquestionid);

        if ($questioncreated) {
            $data->questionid = $this->get_new_parentid('question');
            $DB->insert_record('qtype_clozeonimage_pos', $data);
        }
    }

    /**
     * Recode wrapped-question IDs after all questions have been restored.
     */
    public function after_execute_question() {
        global $DB;

        $records = $DB->get_recordset_sql(
            "
                SELECT qcoi.id, qcoi.sequence
                  FROM {qtype_clozeonimage} qcoi
                  JOIN {backup_ids_temp} bi ON bi.newitemid = qcoi.questionid
                 WHERE bi.backupid = ?
                   AND bi.itemname = 'question_created'",
            [$this->get_restoreid()]
        );

        foreach ($records as $record) {
            $sequence = preg_split('/,/', $record->sequence, -1, PREG_SPLIT_NO_EMPTY);
            if (substr_count($record->sequence, ',') + 1 !== count($sequence)) {
                $this->task->log(
                    'Invalid sequence found in restored Cloze on Image question ' . $record->id,
                    backup::LOG_WARNING
                );
            }

            foreach ($sequence as $key => $oldquestionid) {
                $newquestionid = $this->get_mappingid('question', $oldquestionid);
                if (!$newquestionid) {
                    $this->task->log('Missing wrapped-question mapping in restored Cloze on Image question ' .
                        $record->id . ' for question ' . $oldquestionid, backup::LOG_WARNING);
                }
                $sequence[$key] = $newquestionid;
            }

            $DB->set_field(
                'qtype_clozeonimage',
                'sequence',
                implode(',', array_filter($sequence)),
                ['id' => $record->id]
            );
        }
        $records->close();
    }

    /**
     * Recode response data belonging to wrapped questions.
     *
     * @param int $questionid Restored parent question ID.
     * @param int $sequencenumber Attempt step sequence number.
     * @param array $response Response data.
     * @return array Recoded response data.
     */
    public function recode_response($questionid, $sequencenumber, array $response) {
        global $DB;

        $qtypes = $DB->get_records_menu('question', ['parent' => $questionid], '', 'id, qtype');
        $sequence = $DB->get_field('qtype_clozeonimage', 'sequence', ['questionid' => $questionid]);
        $fakestep = new question_attempt_step_read_only($response);

        foreach (explode(',', $sequence) as $key => $subquestionid) {
            $index = $key + 1;
            $substep = new question_attempt_step_subquestion_adapter($fakestep, 'sub' . $index . '_');
            $recodedresponse = $this->step->questions_recode_response_data(
                $qtypes[$subquestionid],
                $subquestionid,
                $sequencenumber,
                $substep->get_all_data()
            );

            foreach ($recodedresponse as $name => $value) {
                $response[$substep->add_prefix($name)] = $value;
            }
        }

        return $response;
    }

    /**
     * Return formatted content to decode during restore.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('qtype_clozeonimage', ['aftertext'], 'qtype_clozeonimage'),
        ];
    }

    /**
     * Convert backup data to the structure returned by get_question_options().
     *
     * @param array $backupdata Hierarchical backup data.
     * @return stdClass
     */
    public static function convert_backup_to_questiondata(array $backupdata): stdClass {
        $questiondata = parent::convert_backup_to_questiondata($backupdata);
        $positions = $backupdata['plugin_qtype_clozeonimage_question']['positions']['position'] ?? [];
        $questiondata->options->positions = [];
        foreach ($positions as $positiondata) {
            $position = (object) $positiondata;
            $questiondata->options->positions[(int) $position->no] = $position;
        }
        return $questiondata;
    }

    /**
     * Define database identity fields excluded from question hashes.
     *
     * @return array
     */
    protected function define_excluded_identity_hash_fields(): array {
        return [
            '/options/sequence',
            '/options/positions/id',
            '/options/positions/questionid',
        ];
    }

    /**
     * Remove runtime-only structures before comparing question identities.
     *
     * @param stdClass $questiondata Question data.
     * @param array $excludefields Additional excluded field paths.
     * @return stdClass
     */
    public static function remove_excluded_question_data(stdClass $questiondata, array $excludefields = []): stdClass {
        if (isset($questiondata->options->questions)) {
            unset($questiondata->options->questions);
        }
        if (isset($questiondata->options->questionrows)) {
            unset($questiondata->options->questionrows);
        }
        return parent::remove_excluded_question_data($questiondata, $excludefields);
    }
}
