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
 * Represents for the edit page.
 *
 * @package   mod_quiz
 * @category  output
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_page implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param structure $structure Structure of the quiz for which to display the grade edit page.
     * @param \moodle_url $pageurl The page url.
     */
    public function __construct(
        protected readonly structure $structure,
        protected readonly \moodle_url $pageurl,
        protected readonly \mod_quiz\quiz_settings $quizobj,
        protected readonly \core_question\local\bank\question_edit_contexts $contexts,
        protected readonly array $pagevars,
    ) {
    }

    /**
     * Export items to be rendered with a template.
     *
     * @param renderer_base $output The renderer.
     * @return array An array of value for template.
     */
    public function export_for_template(renderer_base $output): array {
        global $PAGE, $OUTPUT;
        /** @var edit_renderer $editrenderer */
        $editrenderer = $PAGE->get_renderer('mod_quiz', 'edit');

        $data = [];
        $heading = $OUTPUT->heading(get_string('questions', 'quiz'));
        $quizinformation = new \mod_quiz\output\quiz_information($this->structure);
        $quizinformation = $quizinformation->export_for_template($output);
        $data['heading'] = $heading;
        $data['warnings'] = $editrenderer->quiz_state_warnings($this->structure);
        $data['quizinformation'] = $quizinformation;

        $maximumgradeinput = new \mod_quiz\output\maximum_grade_input($this->structure, $this->pageurl);
        $maximumgradeinput = $maximumgradeinput->export_for_template($output);

        $data['maximumgradeinput'] = $maximumgradeinput;

        $repaginatebutton = new \mod_quiz\output\repaginate_button($this->structure, $this->pageurl);
        $repaginatebutton = $repaginatebutton->export_for_template($output);
        $data['repaginatebutton'] = $repaginatebutton;

        $selectmultiple_button = new \mod_quiz\output\selectmultiple_button($this->structure);
        $selectmultiple_button = $selectmultiple_button->export_for_template($output);
        $data['selectmultiplebutton'] = $selectmultiple_button;

        $totalmark = new \mod_quiz\output\total_marks($this->quizobj);
        $totalmark = $totalmark->export_for_template($output);
        $data['totalmarks'] = $totalmark;

        $selectmultiplecontrols = new \mod_quiz\output\selectmultiple_controls($this->structure);
        $selectmultiplecontrols = $selectmultiplecontrols->export_for_template($output);
        $data['selectmultiplecontrols'] = $selectmultiplecontrols;

        $section = new \mod_quiz\output\section($this->structure, $this->pageurl,
            $this->quizobj, $this->contexts, $this->pagevars);
        $section = $section->export_for_template($output);
        $data['section'] = $section;

        $this->initialise_editing_javascript($this->structure, $this->contexts, $this->pagevars, $this->pageurl);

        // Include the contents of any other popups required.
        if ($this->structure->can_be_edited()) {
            $thiscontext = $this->contexts->lowest();
            $PAGE->requires->js_call_amd('mod_quiz/modal_quiz_question_bank', 'init', [
                $thiscontext->id
            ]);

            $PAGE->requires->js_call_amd('mod_quiz/modal_add_random_question', 'init', [
                $thiscontext->id,
                $this->pagevars['cat'],
                $this->pageurl->out_as_local_url(true),
                $this->pageurl->param('cmid'),
                \core\plugininfo\qbank::is_plugin_enabled(\qbank_managecategories\helper::PLUGINNAME),
            ]);

            // Include the question chooser.
            $data['questionchooser'] = $editrenderer->question_chooser();
        }

        return $data;
    }

    /**
     * Initialise the JavaScript for the general editing. (JavaScript for popups
     * is handled with the specific code for those.)
     *
     * @param structure $structure object containing the structure of the quiz.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return bool Always returns true
     */
    protected function initialise_editing_javascript(structure $structure,
            \core_question\local\bank\question_edit_contexts $contexts, array $pagevars, \moodle_url $pageurl) {

        global $PAGE;
        $config = new \stdClass();
        $config->resourceurl = '/mod/quiz/edit_rest.php';
        $config->sectionurl = '/mod/quiz/edit_rest.php';
        $config->pageparams = [];
        $config->questiondecimalpoints = $structure->get_decimal_places_for_question_marks();
        $config->pagehtml = $this->new_page_template($structure, $contexts, $pagevars, $pageurl);
        $config->addpageiconhtml = $this->add_page_icon_template($structure);

        $PAGE->requires->yui_module('moodle-mod_quiz-toolboxes',
            'M.mod_quiz.init_resource_toolbox',
            [[
                'courseid' => $structure->get_courseid(),
                'quizid' => $structure->get_quizid(),
                'ajaxurl' => $config->resourceurl,
                'config' => $config,
            ]]
        );
        unset($config->pagehtml);
        unset($config->addpageiconhtml);

        $PAGE->requires->strings_for_js(['areyousureremoveselected'], 'quiz');
        $PAGE->requires->yui_module('moodle-mod_quiz-toolboxes',
            'M.mod_quiz.init_section_toolbox',
            [[
                'courseid' => $structure,
                'quizid' => $structure->get_quizid(),
                'ajaxurl' => $config->sectionurl,
                'config' => $config,
            ]]
        );

        $PAGE->requires->yui_module('moodle-mod_quiz-dragdrop', 'M.mod_quiz.init_section_dragdrop',
            [[
                'courseid' => $structure,
                'quizid' => $structure->get_quizid(),
                'ajaxurl' => $config->sectionurl,
                'config' => $config,
            ]], null, true);

        $PAGE->requires->yui_module('moodle-mod_quiz-dragdrop', 'M.mod_quiz.init_resource_dragdrop',
            [[
                'courseid' => $structure,
                'quizid' => $structure->get_quizid(),
                'ajaxurl' => $config->resourceurl,
                'config' => $config,
            ]], null, true);

        // Require various strings for the command toolbox.
        $PAGE->requires->strings_for_js([
            'clicktohideshow',
            'deletechecktype',
            'deletechecktypename',
            'edittitle',
            'edittitleinstructions',
            'emptydragdropregion',
            'hide',
            'move',
            'movecontent',
            'moveleft',
            'movesection',
            'page',
            'question',
            'selectall',
            'show',
            'tocontent',
        ], 'moodle');

        $PAGE->requires->strings_for_js([
            'addpagebreak',
            'cannotremoveallsectionslots',
            'cannotremoveslots',
            'confirmremovesectionheading',
            'confirmremovequestion',
            'dragtoafter',
            'dragtostart',
            'numquestionsx',
            'sectionheadingedit',
            'sectionheadingremove',
            'sectionnoname',
            'removepagebreak',
            'questiondependencyadd',
            'questiondependencyfree',
            'questiondependencyremove',
            'questiondependsonprevious',
        ], 'quiz');

        foreach (\question_bank::get_all_qtypes() as $qtype => $notused) {
            $PAGE->requires->string_for_js('pluginname', 'qtype_' . $qtype);
        }

        return true;
    }

    /**
     * HTML for a page, with ids stripped, so it can be used as a javascript template.
     *
     * @param structure $structure object containing the structure of the quiz.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML for a new page.
     */
    protected function new_page_template(structure $structure,
                                         \core_question\local\bank\question_edit_contexts $contexts, array $pagevars, \moodle_url $pageurl) {
        global $PAGE;
        /** @var edit_renderer $editrenderer */
        $editrenderer = $PAGE->get_renderer('mod_quiz', 'edit');
        if (!$structure->has_questions()) {
            return '';
        }

        $pagehtml = $editrenderer->page_row($structure, 1, $contexts, $pagevars, $pageurl);

        // Normalise the page number.
        $pagenumber = $structure->get_page_number_for_slot(1);
        $strcontexts = [];
        $strcontexts[] = 'page-';
        $strcontexts[] = get_string('page') . ' ';
        $strcontexts[] = 'addonpage%3D';
        $strcontexts[] = 'addonpage=';
        $strcontexts[] = 'addonpage="';
        $strcontexts[] = get_string('addquestionfrombanktopage', 'quiz', '');
        $strcontexts[] = 'data-addonpage%3D';
        $strcontexts[] = 'action-menu-';

        foreach ($strcontexts as $strcontext) {
            $pagehtml = str_replace($strcontext . $pagenumber, $strcontext . '%%PAGENUMBER%%', $pagehtml);
        }

        return $pagehtml;
    }

    /**
     * HTML for a page, with ids stripped, so it can be used as a javascript template.
     *
     * @param structure $structure object containing the structure of the quiz.
     * @return string HTML for a new icon
     */
    protected function add_page_icon_template(structure $structure) {
        global $PAGE;
        /** @var edit_renderer $editrenderer */
        $editrenderer = $PAGE->get_renderer('mod_quiz', 'edit');
        if (!$structure->has_questions()) {
            return '';
        }

        $html = $editrenderer->page_split_join_button($structure, 1);
        return str_replace('&amp;slot=1&amp;', '&amp;slot=%%SLOT%%&amp;', $html);
    }
}
