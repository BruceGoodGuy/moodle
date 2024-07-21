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

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use coding_exception;
use core_question_generator;
use externallib_advanced_testcase;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use required_capability_exception;
use stdClass;

/**
 * Test for the service save_overall_feedback_per_grade_item.
 *
 * @package   mod_quiz
 * @category  external
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\external\save_overall_feedback_per_grade_item
 */
final class grade_item_overall_feedbacks_test extends externallib_advanced_testcase {

    public function test_create_overall_feedback_works(): void {
        $quizobj = $this->create_quiz_with_grade_item();

        $structure = $quizobj->get_structure();
        $item = array_values($structure->get_grade_items());
        $data = [
            (object) [
                'boundary' => 11,
                'feedback' => (object) [
                    'itemid' => 0,
                    'format' => FORMAT_HTML,
                    'text' => "100% feedback",
                ],
            ],
            (object) [
                'boundary' => "80%",
                'feedback' => (object) [
                    'itemid' => 0,
                    'format' => FORMAT_HTML,
                    'text' => '80% feedback',
                ],
            ],
        ];

        $response = save_overall_feedback_per_grade_item::execute($quizobj->get_quizid(), $item[0]->id, json_encode($data));
        $structure = $quizobj->get_structure();
        $feedbacks = array_values($structure->get_grade_item_feedbacks());

        // There are no errors.
        $this->assertCount(0, json_decode($response->errors));
        // Two feedbacks have been created.
        $this->assertEquals(2, $response->total);

        $this->assertEquals('100% feedback', $feedbacks[0]->feedbacktext);
        $this->assertEquals('80% feedback', $feedbacks[1]->feedbacktext);
    }

    /**
     * Test service save_overall_feedback_per_grade_item.
     * @dataProvider save_value_provider
     *
     * @param array $expecteddata Expected data after executing.
     * @param array $formdata Form data to test.
     */
    public function test_create_overall_feedback_with_erorr(array $expecteddata, array $formdata): void {
        $quizobj = $this->create_quiz_with_grade_item();
        $structure = $quizobj->get_structure();
        $item = array_values($structure->get_grade_items());

        $response = save_overall_feedback_per_grade_item::execute($quizobj->get_quizid(), $item[0]->id, json_encode($formdata));
        $errors = json_decode($response->errors);
        $this->assertEquals(0, $response->total);
        foreach ($expecteddata as $ex) {
            $this->assertEquals($ex['text'], $errors->{$ex['place']});
        }
    }

    /**
     * Data provider for test_create_overall_feedback_with_erorr().
     *
     * @return array
     */
    public static function save_value_provider(): array {
        return [
            'Leaving a gap' => [
                'expected' => [
                    [
                        'place' => 'feedbackboundaries[1]',
                        'text' => 'You must fill in the feedback grade boundary boxes without leaving any gaps.',
                    ],
                    [
                        'place' => 'feedbacktext[2]',
                        'text' => 'You must fill in the feedback boxes without leaving any gaps.',
                    ],
                ],
                'formdata' => [
                    (object) [
                        'boundary' => 11,
                        'feedback' => (object) [
                            'itemid' => 0,
                            'format' => FORMAT_HTML,
                            'text' => "100% feedback",
                        ],
                    ],
                    (object) [
                        'boundary' => "",
                        'feedback' => (object) [
                            'itemid' => 0,
                            'format' => FORMAT_HTML,
                            'text' => "",
                        ],
                    ],
                    (object) [
                        'boundary' => "80%",
                        'feedback' => (object) [
                            'itemid' => 0,
                            'format' => FORMAT_HTML,
                            'text' => '80% feedback',
                        ],
                    ],
                ],
            ],
            'Invalid boundary data' => [
                'expected' => [
                    [
                        'place' => 'feedbackboundaries[0]',
                        'text' => 'Feedback grade boundaries must be between 0% and 100%. The value you entered in ' .
                            'boundary 1 is out of range.',
                    ],
                ],
                'formdata' => [
                    (object) [
                        'boundary' => 11,
                        'feedback' => (object) [
                            'itemid' => 0,
                            'format' => FORMAT_HTML,
                            'text' => "100% feedback",
                        ],
                    ],
                    (object) [
                        'boundary' => "Test",
                        'feedback' => (object) [
                            'itemid' => 0,
                            'format' => FORMAT_HTML,
                            'text' => "Wrong data",
                        ],
                    ],
                ],

            ],
        ];
    }

    /**
     * Create a quiz of a shortanswer question, each contributing to a different grade item.
     *
     * @return quiz_settings the newly created quiz.
     */
    protected function create_quiz_with_grade_item(): quiz_settings {
        global $SITE;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Make a quiz.
        /** @var \mod_quiz_generator $quizgenerator */
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        $quiz = $quizgenerator->create_instance(['course' => $SITE->id]);

        // Create question.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq1 = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);

        // Add them to the quiz.
        quiz_add_quiz_question($saq1->id, $quiz, 0, 1);

        // Create quiz grade items.
        $listeninggrade = $quizgenerator->create_grade_item(['quizid' => $quiz->id, 'name' => 'Listening']);

        // Set the questions to use those grade items.
        $quizobj = quiz_settings::create($quiz->id);
        $structure = $quizobj->get_structure();
        $structure->update_slot_grade_item($structure->get_slot_by_number(1), $listeninggrade->id);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        return $quizobj;
    }
}
