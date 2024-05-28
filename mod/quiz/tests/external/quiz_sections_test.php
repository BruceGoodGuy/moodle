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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../webservice/tests/helpers.php');

use coding_exception;
use core_question_generator;
use externallib_advanced_testcase;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use required_capability_exception;
use stdClass;

/**
 * Test for the grade_items CRUD service.
 *
 * @package   mod_quiz
 * @category  external
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\external\create_grade_items
 * @covers \mod_quiz\external\delete_grade_items
 * @covers \mod_quiz\external\update_grade_items
 * @covers \mod_quiz\structure
 */
class quiz_sections_test extends externallib_advanced_testcase {

    public function test_get_section_title(): void {
        $quizobj = $this->create_quiz_with_two_grade_items();

        $structure = $quizobj->get_structure();
        $defaultsection = array_values($structure->get_sections())[0];
        $result = get_section_title::execute($defaultsection->id, $quizobj->get_quizid());
        $this->assertEmpty($result['instancesection']);

        // Update the section heading.
        $structure->set_section_heading($defaultsection->id, 'Updated');
        $result = get_section_title::execute($defaultsection->id, $quizobj->get_quizid());
        $this->assertEquals('Updated', $result['instancesection']);

    }

    public function test_update_section_title() {
        $quizobj = $this->create_quiz_with_two_grade_items();
        $structure = $quizobj->get_structure();
        $defaultsection = array_values($structure->get_sections())[0];
        $result = update_section_title::execute($defaultsection->id, $quizobj->get_quizid(), 'New Heading');
        $this->assertEquals('New Heading', $result['instancesection']);

    }

    public function test_update_shuffle_questions() {
        $quizobj = $this->create_quiz_with_two_grade_items();
        $structure = $quizobj->get_structure();
        $defaultsection = array_values($structure->get_sections())[0];
        $result = update_shuffle_questions::execute($defaultsection->id, $quizobj->get_quizid(), '0');
        $this->assertEquals('0', $result['instancesection']);
    }

    /**
     * Create a quiz of two shortanswer questions, each contributing to a different grade item.
     *
     * @return quiz_settings the newly created quiz.
     */
    protected function create_quiz_with_two_grade_items(): quiz_settings {
        global $SITE;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Make a quiz.
        /** @var \mod_quiz_generator $quizgenerator */
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        $quiz = $quizgenerator->create_instance(['course' => $SITE->id]);

        // Create two question.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq1 = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $saq2 = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);

        // Add them to the quiz.
        quiz_add_quiz_question($saq1->id, $quiz, 0, 1);
        quiz_add_quiz_question($saq2->id, $quiz, 0, 1);

        // Create two quiz grade items.
        $listeninggrade = $quizgenerator->create_grade_item(['quizid' => $quiz->id, 'name' => 'Listening']);
        $readinggrade = $quizgenerator->create_grade_item(['quizid' => $quiz->id, 'name' => 'Reading']);

        // Set the questions to use those grade items.
        $quizobj = quiz_settings::create($quiz->id);
        $structure = $quizobj->get_structure();
        $structure->update_slot_grade_item($structure->get_slot_by_number(1), $listeninggrade->id);
        $structure->update_slot_grade_item($structure->get_slot_by_number(2), $readinggrade->id);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        return $quizobj;
    }
}
