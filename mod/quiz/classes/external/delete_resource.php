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

namespace mod_quiz\external;

use block_globalsearch\globalsearch_test;
use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_quiz\quiz_settings;

/**
 * Web service method to delete the properties of resource.
 *
 * The user must have the 'mod/quiz:manage' capability for the quiz.
 *
 * All the properties that can be set are optional. Only the ones passed are changed.
 *
 * @package   mod_quiz
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_resource extends external_api {

    /**
     * Declare the method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The question id'),
            'quizid' => new external_value(PARAM_INT, 'The quiz id to get section title'),
//            'newshuffle' => new external_value(PARAM_TEXT, 'The new shuffle of the questions'),
        ]);
    }

    /**
     * Update grade items to this quiz.
     */
    public static function execute(int $id, int $quizid) {
        global $DB;
        $quizobj = quiz_settings::create($quizid);
        require_capability('mod/quiz:manage', $quizobj->get_context());
        self::validate_context($quizobj->get_context());
        $quiz = $quizobj->get_quiz();
        $structure = $quizobj->get_structure();
        if (!$slot = $DB->get_record('quiz_slots', ['quizid' => $quiz->id, 'id' => $id])) {
            throw new moodle_exception('AJAX commands.php: Bad slot ID '.$id);
        }
        if (!$structure->has_use_capability($slot->slot)) {
            $slotdetail = $structure->get_slot_by_id($slot->id);
            $context = context::instance_by_id($slotdetail->contextid);
            throw new required_capability_exception($context,
                'moodle/question:useall', 'nopermissions', '');
        }
        $structure->remove_slot($slot->slot);
        quiz_delete_previews($quiz);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();
        return [
            'newsummarks' => quiz_format_grade($quiz, $quiz->sumgrades),
            'deleted' => true,
            'newnumquestions' => $structure->get_question_count()
        ];
    }

    /**
     * Define the webservice response.
     *
     * @return external_description|null always null.
     */
    public static function execute_returns(): ?external_description {
        return new external_single_structure([
            'newsummarks' => new external_value(PARAM_TEXT, 'Round a grade to the correct number of decimal place'),
            'deleted' => new external_value(PARAM_TEXT, 'Whether it have been delete'),
            'newnumquestions' => new external_value(PARAM_INT, 'The number of questions'),
        ]);
    }
}