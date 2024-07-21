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
 * Defines the editing form for overall feedbacks.
 *
 * @package    mod_quiz
 * @copyright  2024 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Class overallfeedback_form.
 *
 * @package    mod_quiz
 * @copyright  2024 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overallfeedback_form extends \moodleform {

    /**
     * Form definiton.
     */
    public function definition() {
        global $OUTPUT;
        $mform = $this->_form;
        $customdata = $this->_customdata;
        $context = $customdata['context'];
        $feedbacks = $customdata['feedbacks'] ?? [];
        $gradeitemid = $customdata['gradeItemId'];
        $mform->addElement('static', 'gradeboundarystatic1',
            get_string('gradeboundary', 'quiz'), '100%');
        $mform->addElement('editor',
            "feedbacktext[0][".$gradeitemid."]", get_string('feedback', 'quiz'), ['rows' => 3],
            ['maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true,
                'context' => $context]);
        $dividertemplate = $OUTPUT->render_from_template('mod_quiz/divider_feedback', ['afterindex' => 0]);
        $mform->addElement('html', $dividertemplate);

        $index = 0;
        foreach ($feedbacks as $feedback) {
            if ($feedback->maxgrade > $customdata['grade']) {
                continue;
            }
            $mform->addElement('text', "feedbackboundaries[$index]",
                get_string('gradeboundary', 'quiz'), ['size' => 10]);
            $mform->addElement('editor',
                'feedbacktext[' . ($index + 1) . '][' . $gradeitemid . ']',
                get_string('feedback', 'quiz'), ['rows' => 3],
                ['maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true,
                    'context' => $context]);
            $dividertemplate = $OUTPUT->render_from_template('mod_quiz/divider_feedback', ['afterindex' => $index + 1]);
            $mform->addElement('html', $dividertemplate);
            $index++;
        }

        $mform->addElement('static',
            'gradeboundarystatic2', get_string('gradeboundary', 'quiz'), '0%');
    }

    public function set_data($defaultvalues) {
        if (is_object($defaultvalues)) {
            $defaultvalues = (array) $defaultvalues;
        }

        $this->data_preprocessing($defaultvalues);
        parent::set_data($defaultvalues);
    }

    /**
     * Process the data and set it to the form before rendering the form data.
     *
     * @param array $toform The form data to be processed.
     */
    public function data_preprocessing(array &$toform) {
        $feedbacks = $this->_customdata['feedbacks'];
        if (count($feedbacks)) {
            $key = 0;
            $firstfeedback = reset($feedbacks);
            if ($firstfeedback->maxgrade < $toform['grade']) {
                // This means the feedback for the boundary 100% is not filled.
                // Therefore, we need to add a default feedback for the boundary 100%.
                $a = new \stdClass();
                $a->id = $firstfeedback->id - 1;
                $a->quizid = $firstfeedback->quizid;
                $a->gradeitemid = $firstfeedback->gradeitemid;
                $a->feedbacktext = '';
                $a->mingrade = $firstfeedback->maxgrade;
                $a->maxgrade = $toform['grade'] + 1;
                $a->feedbacktextformat = $firstfeedback->feedbacktextformat;
                array_unshift($feedbacks, $a);
            }
            foreach ($feedbacks as $feedback) {
                $feedbackname = "feedbacktext[$key][$feedback->gradeitemid]";
                $draftid = file_get_submitted_draft_itemid($feedbackname);
                $toform[$feedbackname]['text'] = file_prepare_draft_area(
                    $draftid,
                    $this->_customdata['context']->id,
                    'mod_quiz',
                    'grade_item_feedback',
                    !empty($feedback->id) ? (int) $feedback->id : null,
                    null,
                    $feedback->feedbacktext,
                );
                $toform[$feedbackname]['format'] = $feedback->feedbacktextformat;
                $toform[$feedbackname]['itemid'] = $draftid;

                if ($feedback->mingrade > 0) {
                    $toform['feedbackboundaries['.$key.']'] =
                        round(100.0 * $feedback->mingrade / $toform['grade'], 6) . '%';
                }
                $key++;
            }
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $grade = $data['grade'];
        $formdata = $data['formdata'];

        $index = 0;
        foreach ($formdata as $key => &$data) {
            $boundary = trim($data->boundary);
            if (empty($boundary)) {
                break;
            }

            if (strlen($boundary) > 0) {
                if ($boundary[strlen($boundary) - 1] == '%') {
                    $boundary = trim(substr($boundary, 0, -1));
                    if (is_numeric($boundary)) {
                        $boundary = $boundary * $grade / 100.0;
                    } else {
                        $errors["feedbackboundaries[$key]"] =
                            get_string('feedbackerrorboundaryformat', 'quiz', $key + 1);
                    }
                } else if (!is_numeric($boundary)) {
                    $errors["feedbackboundaries[$key]"] =
                        get_string('feedbackerrorboundaryformat', 'quiz', $key + 1);
                }
            }
            if (is_numeric($boundary) && $boundary <= 0 || $boundary >= $grade ) {
                $errors["feedbackboundaries[$key]"] =
                    get_string('feedbackerrorboundaryoutofrange', 'quiz', $key + 1);
            }
            if (is_numeric($boundary) && $key > 0 && $boundary >= $formdata[$key - 1]->boundary) {
                $errors["feedbackboundaries[$key]"] =
                    get_string('feedbackerrororder', 'quiz', $key + 1);
            }
            $data->boundary = $boundary;
            $index++;
        }
        $numboundaries = $index;
        // Check there is nothing in the remaining unused fields.
        for ($i = $numboundaries; $i < count($formdata); $i += 1) {
            if (!empty($formdata[$i]->boundary) &&
                trim($formdata[$i]->boundary) != '') {
                $errors["feedbackboundaries[$i]"] =
                    get_string('feedbackerrorjunkinboundary', 'quiz', $i + 1);
            }
        }
        for ($i = $numboundaries; $i < count($formdata); $i += 1) {
            if (!empty($formdata[$i]->feedback->text) &&
                trim($formdata[$i]->feedback->text) != '') {
                $errors["feedbacktext[" . ($i + 1) . "]"] =
                    get_string('feedbackerrorjunkinfeedback', 'quiz', $i + 1);
            }
        }

        return [$errors, $formdata];
    }
}
