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
use core\external\moodlenet_send_activity;
use core_external\external_api;
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
 */
class quiz_sections_test extends externallib_advanced_testcase {

    /**
     * Test the behavior of get section title.
     *
     * @covers \mod_quiz\external\get_section_title
     */
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

    /**
     * Test the behavior of update section title.
     *
     * @covers \mod_quiz\external\update_section_title
     */
    public function test_update_section_title() {
        $quizobj = $this->create_quiz_with_two_grade_items();
        $structure = $quizobj->get_structure();
        $defaultsection = array_values($structure->get_sections())[0];
        $result = update_section_title::execute($defaultsection->id, $quizobj->get_quizid(), 'New Heading');
        $this->assertEquals('New Heading', $result['instancesection']);

    }

    /**
     * Test the behavior of update shuffle questions.
     *
     * @covers \mod_quiz\external\update_shuffle_questions
     */
    public function test_update_shuffle_questions() {
        $this->setAdminUser();

        $quizobj = $this->create_quiz_with_two_grade_items();
        $structure = $quizobj->get_structure();
        $defaultsection = array_values($structure->get_sections())[0];
        $result = update_shuffle_questions::execute($defaultsection->id, $quizobj->get_quizid(), 1);

        $this->assertEquals('1', $result['instanceshuffle']);
    }

    /**
     * Test the behavior of delete section.
     *
     * @covers \mod_quiz\external\delete_section
     */
    public function test_delete_section() {
        $quizobj = $this->create_quiz_with_two_grade_items();
        $structure = $quizobj->get_structure();

        // Add section heading for section 2.
        $structure->add_section_heading(2, 'Section 2');

        $structure = $quizobj->get_structure();
        $sections = $structure->get_sections();
        $section2 = array_values($sections)[1];

        $this->assertCount(2, $sections);
        $this->assertEquals('Section 2', $section2->heading);

        $result = delete_section::execute($section2->id, $quizobj->get_quizid());
        $this->assertArrayHasKey('deleted', $result);
        $this->assertTrue($result['deleted']);

        $structure = $quizobj->get_structure();
        $sections = $structure->get_sections();
        $section1 = array_values($sections)[0];

        // The section when delete we just have section 1 left
        $this->assertCount(1, $sections);
        $this->assertEquals('', $section1->heading);
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
