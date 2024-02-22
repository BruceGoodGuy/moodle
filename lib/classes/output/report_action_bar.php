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
namespace core\output;

use moodle_page;
use core_grades\output\general_action_bar;
use core\output\comboboxsearch;
use \core_grades\output\action_bar;
use core_message\helper;
use core_message\api;
use moodle_url;

/**
 * Renderer class for the report pages.
 *
 * @package    core
 * @copyright  2021 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_action_bar implements \renderable, \templatable {
    /** @var moodle_page $page The current page. */
    private $page;
    /** @var object $courseid The courseid we are dealing with. */
    private $courseid;
    /** @var object $cm The cm we are dealing with. */
    private $cm;
    /** @var string $mode The report mode we are dealing with. */
    private $mode;
    /** @var string $usersearch The content that the current user is looking for. */
    protected string $usersearch = '';
    private object $context;
    protected moodle_url $urlroot;
    protected mixed $menus;

    /**
     * Constructor report_action_bar
     * @param int|null $courseid The course that we are generating the nav for.
     * @param moodle_page $page The page object.
     * @param string $mode The report mode.
     * @param object|null $cm The cmid of the activity that we are generating the nav for.
     */
    public function __construct(?int $courseid, moodle_page $page, string $mode, ?object $cm, $menus, $urlroot = null) {
        $this->courseid = $courseid;
        $this->page = $page;
        $this->mode = $mode;
        $this->cm = $cm;
        $this->menus = $menus;

        if (is_null($cm)) {
            $this->context = \context_course::instance($this->courseid);
        } else {
            $this->context = \context_module::instance($cm->id);
        }

        if (!is_null($urlroot)) {
            $this->urlroot = $urlroot;
        }
    }

    private function get_content_for_initial_bar(): comboboxsearch {
        $initialscontent = $this->initials_selector(
            $this->cm->id,
            $this->context,
            '/mod/quiz/report.php',
            $this->mode,
            [],
        );

        return new comboboxsearch(
            false,
            $initialscontent->buttoncontent,
            $initialscontent->dropdowncontent,
            'initials-selector',
            'initialswidget',
            'initialsdropdown',
            $initialscontent->buttonheader,
        );
    }

    /**
     * Build the data to render the initials bar filter within the gradebook.
     * Using this initials selector means you'll have to retain the use of the templates & JS to handle form submission.
     * If a simple redirect on each selection is desired the standard user_search() within the user renderer is what you are after.
     *
     * @param object $course The course object.
     * @param context $context Our current context.
     * @param string $slug The slug for the report that called this function.
     * @return stdClass The data to output.
     */
    private function initials_selector(
        int $id,
        \context $context,
        string $slug,
        string $searchprefix = 'gpr',
        array $urlparams = [],
    ): \stdClass {
        global $SESSION, $COURSE, $OUTPUT;
        // User search.
        $searchvalue = optional_param($searchprefix . '_search', null, PARAM_NOTAGS);
        $userid = optional_param($searchprefix . '_userid', null, PARAM_INT);
        $url = new moodle_url($slug, ['id' => $id]);
        $firstinitial = $SESSION->{$this->mode}["filterfirstname-{$context->id}"] ?? '';
        $lastinitial  = $SESSION->{$this->mode}["filtersurname-{$context->id}"] ?? '';

        $renderer = $this->page->get_renderer('core_user');
        $initialsbar = $renderer->partial_user_search($url, $firstinitial, $lastinitial, true);

        $currentfilter = '';
        if ($firstinitial !== '' && $lastinitial !== '') {
            $currentfilter = get_string('filterbothactive', 'grades', ['first' => $firstinitial, 'last' => $lastinitial]);
        } else if ($firstinitial !== '') {
            $currentfilter = get_string('filterfirstactive', 'grades', ['first' => $firstinitial]);
        } else if ($lastinitial !== '') {
            $currentfilter = get_string('filterlastactive', 'grades', ['last' => $lastinitial]);
        }

        $this->page->requires->js_call_amd('core_grades/searchwidget/initials', 'init', [$slug, $userid, $searchvalue, $urlparams]);

        $formdata = (object) [
            'courseid' => $COURSE->id,
            'initialsbars' => $initialsbar,
        ];
        $dropdowncontent = $OUTPUT->render_from_template('core_grades/initials_dropdown_form', $formdata);

        return (object) [
            'buttoncontent' => $currentfilter !== '' ? $currentfilter : get_string('filterbyname', 'core_grades'),
            'buttonheader' => $currentfilter !== '' ? get_string('name') : null,
            'dropdowncontent' => $dropdowncontent,
        ];
    }

    /**
     * Renders the group selector trigger element.
     *
     * @param string|null $groupactionbaseurl The base URL for the group action.
     * @return object|null The raw HTML to render.
     */
    public function group_selector(?string $groupactionbaseurl = null): ?object {
        global $USER, $OUTPUT;

        // Make sure that group mode is enabled.
        if (!$groupmode = groups_get_activity_groupmode($this->cm)) {
            return null;
        }

        // Groups are being used, so output the group selector if we are not downloading.
        $sbody = $OUTPUT->render_from_template('core_group/comboboxsearch/searchbody', [
            'courseid' => $this->courseid ?? 0,
            'cmid' => $this->cm->id,
            'currentvalue' => optional_param('groupsearchvalue', '', PARAM_NOTAGS),
        ]);

        $label = $groupmode == VISIBLEGROUPS ? get_string('selectgroupsvisible') :
            get_string('selectgroupsseparate');

        $data = [
            'name' => 'group',
            'label' => $label,
            'courseid' => $this->courseid ?? 0,
            'groupactionbaseurl' => $groupactionbaseurl
        ];

        $aag = has_capability('moodle/site:accessallgroups', $this->context);

        if ($groupmode == VISIBLEGROUPS or $aag) {
            $allowedgroups = groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid, 'g.*', false, true); // Any group in grouping.
        } else {
            // Only assigned groups.
            $allowedgroups = groups_get_all_groups($this->cm->course, $USER->id, $this->cm->groupingid, 'g.*', false, true);
        }

        $activegroup = groups_get_activity_group($this->cm, true, $allowedgroups);

        $data['group'] = $activegroup;

        if ($activegroup) {
            $group = groups_get_group($activegroup);
            $data['selectedgroup'] = format_string($group->name, true, ['context' => $this->context]);
        } elseif ($activegroup === 0) {
            $data['selectedgroup'] = get_string('allparticipants');
        }

        return (object) new comboboxsearch(
            false,
            $OUTPUT->render_from_template('core_group/comboboxsearch/group_selector', $data),
            $sbody,
            'group-search',
            'groupsearchwidget',
            'groupsearchdropdown overflow-auto w-100',
        );
    }

    private function search_user() {
        global $OUTPUT;
        $resetlink = new moodle_url('/grade/report/grader/index.php', ['id' => 1]);
        $searchinput = $OUTPUT->render_from_template('core_user/comboboxsearch/user_selector', [
            'currentvalue' => $this->usersearch,
            'courseid' => 1,
            'resetlink' => $resetlink->out(false),
            'group' => 0,
        ]);

        return new comboboxsearch(
            true,
            $searchinput,
            null,
            'user-search dropdown d-flex',
            null,
            'usersearchdropdown overflow-auto',
            null,
            false,
        );
    }

    /**
     * Export the content to be displayed on the participants page.
     *
     * @param \renderer_base $output
     * @return array Consists of the following:
     *              - navigation A stdclass representing the standard navigation options to be fed into a urlselect
     *              - renderedcontent Rendered content to be displayed in line with the tertiary nav
     */
    public function export_for_template(\renderer_base $output) {
//        $groupdropdown = $this->group_selector();
//        if (!is_null($groupdropdown)) {
//            $groupdropdown = $output->render_from_template($groupdropdown->get_template(),
//                $groupdropdown->export_for_template($output));
//        }
//        $generalnavselector = $this->get_content_for_action_selector();
        $generalnavselector = [];
        $vvv = $this->menus->export_for_template($output);
        if (!empty($this->menus)) {
            $generalnavselector = $this->menus->export_for_template($output);
        }
        if (is_array($generalnavselector) && isset($generalnavselector['generalnavselector'])) {
            $generalnavselector = $generalnavselector['generalnavselector'];
        }

//        $x = $this->
        return $vvv;
        return [
            'generalnavselector' => $generalnavselector,
            'initialselector' => $this->get_content_for_initial_bar()->export_for_template($output),
//            'groupselector' => $groupdropdown,
//            'searchdropdown' => $this->search_user()->export_for_template($output),
        ];
    }
}
