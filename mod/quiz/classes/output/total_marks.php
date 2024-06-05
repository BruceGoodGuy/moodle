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

namespace mod_quiz\output;

use mod_quiz\structure;
use renderable;
use renderer_base;
use templatable;

/**
 * The total marks.
 *
 * @package   mod_quiz
 * @category  output
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class total_marks implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param \mod_quiz\quiz_settings $quizobj Information about the quiz in question.
     */
    public function __construct(
        protected readonly \mod_quiz\quiz_settings $quizobj,
    ) {
    }

    /**
     * Export the page data for the mustache template.
     *
     * @param renderer_base $output renderer to be used to render the page elements.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $quiz = $this->quizobj->get_quiz();
        $totalmark = quiz_format_grade($quiz, $quiz->sumgrades);

        return [
            'totalmark' => $totalmark,
        ];
    }
}
