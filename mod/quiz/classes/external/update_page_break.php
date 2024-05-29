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

use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_quiz\quiz_settings;

/**
 * Web service method to update the properties of page break.
 *
 * The user must have the 'mod/quiz:manage' capability for the quiz.
 *
 * All the properties that can be set are optional. Only the ones passed are changed.
 *
 * @package   mod_quiz
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_page_break extends external_api {

    /**
     * Declare the method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The question id'),
            'quizid' => new external_value(PARAM_INT, 'The quiz id to get section title'),
            'value' => new external_value(PARAM_INT, 'The value'),
        ]);
    }

    /**
     * Update grade items to this quiz.
     */
    public static function execute(int $id, int $quizid, int $value) {
        $quizobj = quiz_settings::create($quizid);
        require_capability('mod/quiz:manage', $quizobj->get_context());
        self::validate_context($quizobj->get_context());
        $structure = $quizobj->get_structure();
        $slots = $structure->update_page_break($id, $value);
        $json = [];
        foreach ($slots as $slot) {
            $json[$slot->slot] = ['id' => $slot->id, 'slot' => $slot->slot,
                'page' => $slot->page];
        }
        $quiz = $quizobj->get_quiz();
        quiz_delete_previews($quiz);

        return ['slots' => json_encode($json)];
    }

    /**
     * Define the webservice response.
     *
     * @return external_description|null always null.
     */
    public static function execute_returns(): ?external_description {
        return new external_single_structure([
            'slots' => new external_value(PARAM_TEXT, 'The new slot page break update'),
        ]);
    }
}