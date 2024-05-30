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
 * General groups bar on the action bar menu.
 *
 * @package    core_group
 * @copyright  2024 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_group\output;


use renderable;
use templatable;
use stdClass;
use renderer_base;
use core\output\comboboxsearch;

/**
 * Group details page class.
 *
 * @package    core_group
 * @copyright  2024 The Open University.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_selector implements renderable, templatable {

    /** @var stdClass $course An object with the course information. */
    protected $course;
    /** @var \cm_info|null $cm An cm object. */
    protected $cm;
    /** @var ?string $groupactionbaseurl The base URL for the group action. */
    protected $groupactionbaseurl;

    /**
     * Group action bar constructor.
     *
     * @param stdClass $course The course object.
     * @param \cm_info|null $cm CM info object.
     * @param string|null $groupactionbaseurl The base URL for the group action.
     */
    public function __construct(stdClass $course, \cm_info $cm = null,
            ?string $groupactionbaseurl = null) {
        $this->course = $course;
        if (!is_null($cm)) {
            $this->cm = $cm;
        }
        if (!is_null($groupactionbaseurl)) {
            $this->groupactionbaseurl = $groupactionbaseurl;
        }
    }

    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return ?array
     */
    public function export_for_template(renderer_base $output): ?array {
        if ($this->groupactionbaseurl !== null) {
            debugging(
                'The $groupactionbaseurl argument has been deprecated. Please remove it from your method calls.',
                DEBUG_DEVELOPER,
            );
        }

        if (is_null($this->cm)) {
            $groupmode = $this->course->groupmode;
        } else {
            $groupmode = groups_get_activity_groupmode($this->cm);
        }
        // Make sure that group mode is enabled.
        if (!$groupmode) {
            return null;
        }

        $sbody = $output->render_from_template('core_group/comboboxsearch/searchbody', [
            'courseid' => $this->course->id,
            'cmid' => $this->cm->id ?? null,
            'currentvalue' => optional_param('groupsearchvalue', '', PARAM_NOTAGS),
            'instance' => rand(),
        ]);

        $label = $groupmode == VISIBLEGROUPS ? get_string('selectgroupsvisible') :
            get_string('selectgroupsseparate');

        $buttondata = ['label' => $label];

        [$context, $activegroup] = $this->get_group_info($this->course, $this->cm, $groupmode);

        $buttondata['group'] = $activegroup;

        if ($activegroup) {
            $group = groups_get_group($activegroup);
            $buttondata['selectedgroup'] = format_string($group->name, true, ['context' => $context]);
        } else if ($activegroup === 0) {
            $buttondata['selectedgroup'] = get_string('allparticipants');
        }

        $groupdropdown = new comboboxsearch(
            false,
            $output->render_from_template('core_group/comboboxsearch/group_selector', $buttondata),
            $sbody,
            'group-search',
            'groupsearchwidget',
            'groupsearchdropdown overflow-auto',
            null,
            true,
            $label,
            'group',
            $activegroup,
        );

        return $groupdropdown->export_for_template($output);
    }

    /**
     * Retrieve group info contains context (course or module) and group active.
     *
     * @param \stdClass $course The course object.
     * @param null|\cm_info $cm Course module info.
     * @param int $groupmode Group mode data.
     * @return array Group info data context (course or module) and group active.
     */
    private function get_group_info(\stdClass $course, ?\cm_info $cm, int $groupmode): array {
        global $USER;

        // Determine the context based on $cm.
        if (is_null($cm)) {
            $context = \context_course::instance($course->id);
        } else {
            $context = \context_module::instance($cm->id);
        }

        // Check if the user can access all groups.
        $canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
        $groupingid = ($cm === null) ? $course->defaultgroupingid : $cm->groupingid;

        // Determine the allowed groups based on $cm and $groupmode.
        if ($groupmode == VISIBLEGROUPS || $canaccessallgroups) {
            $allowedgroups = groups_get_all_groups($course->id, 0, $groupingid, 'g.*', false, !is_null($cm));
        } else {
            $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $groupingid, 'g.*', false, !is_null($cm));
        }

        // Determine the active group based on $cm.
        if (is_null($cm)) {
            $activegroup = groups_get_course_group($course, true, $allowedgroups);
        } else {
            $activegroup = groups_get_activity_group($cm, true, $allowedgroups);
        }

        return [$context, $activegroup];
    }
}
