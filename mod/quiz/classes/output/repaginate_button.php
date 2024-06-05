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
 * Repaginate button in question quiz.
 *
 * @package   mod_quiz
 * @category  output
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repaginate_button implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param structure $structure Structure of the quiz for which to display the grade edit page.
     * @param \moodle_url $pageurl The page url.
     */
    public function __construct(
        protected readonly structure $structure,
        protected readonly \moodle_url $pageurl,
    ) {
    }

    /**
     * Export items to be rendered with a template.
     *
     * @param renderer_base $output The renderer.
     * @return array An array of value for template.
     */
    public function export_for_template(renderer_base $output): array {
        global $PAGE;
        $header = html_writer::tag('span', get_string('repaginatecommand', 'quiz'), ['class' => 'repaginatecommand']);
        $form = $this->repaginate_form($this->structure, $this->pageurl);

        $buttonoptions = [
            'type'  => 'submit',
            'name'  => 'repaginate',
            'id'    => 'repaginatecommand',
            'value' => get_string('repaginatecommand', 'quiz'),
            'class' => 'btn btn-secondary mr-1',
            'data-header' => $header,
            'data-form'   => $form,
        ];
        if (!$this->structure->can_be_repaginated()) {
            $buttonoptions['disabled'] = 'disabled';
        } else {
            $PAGE->requires->js_call_amd('mod_quiz/repaginate', 'init');
        }

        $repaginate = html_writer::empty_tag('input', $buttonoptions);

        return [
            'repaginate' => $repaginate,
        ];
    }

    /**
     * Return the repaginate form
     * @param structure $structure the structure of the quiz being edited.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    protected function repaginate_form(structure $structure, \moodle_url $pageurl) {
        $perpage = [];
        $perpage[0] = get_string('allinone', 'quiz');
        for ($i = 1; $i <= 50; ++$i) {
            $perpage[$i] = $i;
        }

        $hiddenurl = clone($pageurl);
        $hiddenurl->param('sesskey', sesskey());

        $select = html_writer::select($perpage, 'questionsperpage',
            $structure->get_questions_per_page(), false, ['class' => 'custom-select']);

        $buttonattributes = [
            'type' => 'submit',
            'name' => 'repaginate',
            'value' => get_string('go'),
            'class' => 'btn btn-secondary ml-1'
        ];

        $formcontent = html_writer::tag('form', html_writer::div(
            html_writer::input_hidden_params($hiddenurl) .
            get_string('repaginate', 'quiz', $select) .
            html_writer::empty_tag('input', $buttonattributes)
        ), ['action' => 'edit.php', 'method' => 'post']);

        return html_writer::div($formcontent, '', ['id' => 'repaginatedialog']);
    }
}
