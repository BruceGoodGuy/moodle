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
use html_writer;

/**
 * The select multiple controls in quiz.
 *
 * @package   mod_quiz
 * @category  output
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class selectmultiple_controls implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param structure $structure
     */
    public function __construct(

        /** @var structure structure of the quiz for which to display the grade edit page. */
        protected readonly structure $structure,
        protected readonly string $togglegroup = 'quiz-questions',
    ) {
    }

    public function export_for_template(renderer_base $output) {
        $selectmultipleitems = $this->selectmultiple_controls($this->structure);
        return [
            'selectmiltiplecontrols' => $selectmultipleitems,
        ];
    }

    /**
     * Generate the controls that appear when the bulk action button is pressed.
     *
     * @param structure $structure the structure of the quiz being edited.
     * @return string HTML to output.
     */
    protected function selectmultiple_controls(structure $structure) {
        global $OUTPUT;
        $output = '';

        // Bulk action button delete and bulk action button cancel.
        $buttondeleteoptions = [
            'type' => 'button',
            'id' => 'selectmultipledeletecommand',
            'value' => get_string('deleteselected', 'mod_quiz'),
            'class' => 'btn btn-secondary',
            'data-action' => 'toggle',
            'data-togglegroup' => '',
            'data-toggle' => 'action',
            'disabled' => true
        ];
        $buttoncanceloptions = [
            'type' => 'button',
            'id' => 'selectmultiplecancelcommand',
            'value' => get_string('cancel', 'moodle'),
            'class' => 'btn btn-secondary'
        ];

        $groupoptions = [
            'class' => 'btn-group selectmultiplecommand actions m-1',
            'role' => 'group'
        ];

        $output .= html_writer::tag('div',
            html_writer::tag('button', get_string('deleteselected', 'mod_quiz'), $buttondeleteoptions) .
            " " .
            html_writer::tag('button', get_string('cancel', 'moodle'),
                $buttoncanceloptions), $groupoptions);

        $toolbaroptions = [
            'class' => 'btn-toolbar m-1',
            'role' => 'toolbar',
            'aria-label' => get_string('selectmultipletoolbar', 'quiz'),
        ];

        // Select all/deselect all questions.
        $selectallid = 'questionselectall';
        $selectalltext = get_string('selectall', 'moodle');
        $deselectalltext = get_string('deselectall', 'moodle');
        $mastercheckbox = new \core\output\checkbox_toggleall($this->togglegroup, true, [
            'id' => $selectallid,
            'name' => $selectallid,
            'value' => 1,
            'label' => $selectalltext,
            'selectall' => $selectalltext,
            'deselectall' => $deselectalltext,
        ], true);

        $selectdeselect = html_writer::div($OUTPUT->render($mastercheckbox), 'selectmultiplecommandbuttons');
        $output .= html_writer::tag('div', $selectdeselect, $toolbaroptions);

        return $output;
    }
}
