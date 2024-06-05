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
 * The information of the quiz.
 *
 * @package   mod_quiz
 * @category  output
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_information implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param structure $structure Structure of the quiz for which to display the grade edit page.
     */
    public function __construct(
        protected readonly structure $structure,
    ) {
    }

    /**
     * Export items to be rendered with a template.
     *
     * @param renderer_base $output The renderer.
     * @return array An array of value for template.
     */
    public function export_for_template(renderer_base $output): array {
        [$currentstatus, $explanation] = $this->structure->get_dates_summary();
        $quesitoncount = $this->structure->get_question_count();

        return [
            'currentstatus' => $currentstatus,
            'explanation' => $explanation,
            'quesitoncount' => $quesitoncount,
        ];
    }
}
