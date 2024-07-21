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

/**
 * Defines the editing form for single overall feedbacks.
 *
 * @package    mod_quiz
 * @copyright  2024 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Class single_overallfeedback_form.
 *
 * @package    mod_quiz
 * @copyright  2024 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class single_overallfeedback_form extends \moodleform {

    /**
     * Form definiton.
     */
    public function definition() {
        global $OUTPUT;
        $mform = $this->_form;
        $customdata = $this->_customdata;
        $after = $customdata['after'];
        $context = $customdata['context'];
        $editornumber = $customdata['editorno'];
        $gradeitemid = $customdata['gradeitemid'];

        $mform->addElement('text', "feedbackboundaries[$after]",
            get_string('gradeboundary', 'quiz'), ['size' => 10]);
        // We need to create a unique ID for the text editor because it is necessary for the editor
        // JavaScript to set events based on that ID.
        // Since the ID of the editor is attached to JavaScript, we cannot change it.
        $mform->addElement('editor',
            'feedbacktext[' . $editornumber . '][' . $gradeitemid . ']', get_string('feedback', 'quiz'),
            ['rows' => 3, 'data-position' => $after],
            ['maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true,
                'context' => $context]);
        $dividertemplate = $OUTPUT->render_from_template('mod_quiz/divider_feedback', ['afterindex' => $after]);
        $mform->addElement('html', $dividertemplate);
    }
}
