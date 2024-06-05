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
 * The section in question quiz.
 *
 * @package   mod_quiz
 * @category  output
 * @copyright 2023 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param structure $structure
     */
    public function __construct(
        /** @var structure structure of the quiz for which to display the grade edit page. */
        protected readonly structure $structure,
        protected readonly \moodle_url $pageurl,
        protected readonly \mod_quiz\quiz_settings $quizobj,
        protected readonly \core_question\local\bank\question_edit_contexts $contexts,
        protected readonly array $pagevars,

    ) {}

    /**
     * Export the page data for the mustache template.
     *
     * @param renderer_base $output renderer to be used to render the page elements.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $PAGE, $OUTPUT;
        /** @var edit_renderer $editrenderer */
        $editrenderer = $PAGE->get_renderer('mod_quiz', 'edit');
        $data = [];
        foreach ($this->structure->get_sections() as $section) {
            $sectionstyle = '';
            if ($this->structure->is_only_one_slot_in_section($section)) {
                $sectionstyle .= ' only-has-one-slot';
            }
            if ($section->shufflequestions) {
                $sectionstyle .= ' shuffled';
            }

            $sectiondata['sectionstyle'] = $sectionstyle;

            if ($section->heading) {
                $sectiondata['sectionheadingclass'] = 'instancesection';
                $sectiondata['sectionheadingtext'] = format_string($section->heading);
            } else {
                // Use a sr-only default section heading, so we don't end up with an empty section heading.
                $sectiondata['sectionheadingclass'] = 'instancesection sr-only';
                $sectiondata['sectionheadingtext'] = get_string('sectionnoname', 'quiz');
            }

            if (!$this->structure->can_be_edited()) {
                $sectiondata['editsectionheadingicon'] = '';
            } else {
                $sectiondata['editsectionheadingicon'] = \html_writer::link(new \moodle_url('#'),
                    $OUTPUT->pix_icon('t/editstring', get_string('sectionheadingedit', 'quiz',
                    $sectiondata['sectionheadingtext']), 'moodle', ['class' => 'editicon visibleifjs']),
                    ['class' => 'editing_section', 'data-action' => 'edit_section_title', 'role' => 'button']);
            }
            $sectiondata['removeicon'] = '';
            if (!$this->structure->is_first_section($section) && $this->structure->can_be_edited()) {
                $title = get_string('sectionheadingremove', 'quiz', format_string($section->heading));
                $url = new \moodle_url('/mod/quiz/edit.php',
                    ['sesskey' => sesskey(), 'removesection' => '1', 'sectionid' => $section->id]);
                $image = $OUTPUT->pix_icon('t/delete', $title);
                $sectiondata['removeicon'] = $OUTPUT->action_link($url, $image, null, [
                    'class' => 'cm-edit-action editing_delete', 'data-action' => 'deletesection']);
            }
            $sectiondata['sectionid'] = $section->id;

            $sectiondata['shufflequestion'] = $editrenderer->section_shuffle_questions($this->structure, $section);
            $sectiondata['questions'] = '';
            foreach ($this->structure->get_slots_in_section($section->id) as $slot) {
                $sectiondata['questions'] .= $editrenderer->question_row($this->structure, $slot, $this->contexts,
                    $this->pagevars, $this->pageurl);
            }
            $sectiondata['lastsection'] ='';
            if ($this->structure->is_last_section($section)) {
                $sectiondata['lastsection'] .= \html_writer::start_div('last-add-menu');
                $sectiondata['lastsection'] .= \html_writer::tag('span', $editrenderer->add_menu_actions($this->structure, 0,
                    $this->pageurl, $this->contexts, $this->pagevars), ['class' => 'add-menu-outer']);
                $sectiondata['lastsection'] .= \html_writer::end_div();
            }

            $data[] = $sectiondata;
        }

        return $data;
    }
}
