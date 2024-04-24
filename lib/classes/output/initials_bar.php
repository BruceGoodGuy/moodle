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

use moodle_url;
use context;

/**
 * This class sets a general initial bar on the action bar menu.
 *
 * @package    core
 * @category   output
 * @copyright  2024 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class initials_bar {

    /**
     * Retrieve the initials selector data before render.
     *
     * @param object $course The course object.
     * @param context $context The context object.
     * @param string $slug The slug for the report that called this function.
     * @param array $params URL params array.
     * @param \stdClass $filter Filter object to support initials selector js.
     * @param string $reportmode Report type.
     * @param int|null $cmid Cmid data.
     * @return comboboxsearch The raw HTML to render.
     */
    public static function initials_selector(object $course, context $context, string $slug,
            array $params, \stdClass $filter, string $reportmode = 'grade', int $cmid = null): comboboxsearch {
        global $SESSION, $OUTPUT, $PAGE;
        // User search.
        $url = new moodle_url($slug, $params);

        $prefix = 'si';
        if (!is_null($cmid)) {
            // To reuse existing filter feature of flex table.
            $prefix = 'ti';
        }
        $firstinitial = $SESSION->{$reportmode . 'report'}["filterfirstname-{$context->id}"] ?? '';
        $lastinitial = $SESSION->{$reportmode . 'report'}["filtersurname-{$context->id}"] ?? '';

        $renderer = $PAGE->get_renderer('core_user');
        $initialsbar = $renderer->partial_user_search($url, $firstinitial, $lastinitial, true, $prefix);

        $currentfilter = '';
        if ($firstinitial !== '' && $lastinitial !== '') {
            $currentfilter = get_string('filterbothactive', 'grades',
                ['first' => $firstinitial, 'last' => $lastinitial]);
        } else if ($firstinitial !== '') {
            $currentfilter = get_string('filterfirstactive', 'grades', ['first' => $firstinitial]);
        } else if ($lastinitial !== '') {
            $currentfilter = get_string('filterlastactive', 'grades', ['last' => $lastinitial]);
        }

        $PAGE->requires->js_call_amd('core/searchwidget/initials', 'init',
            [$slug, $filter->userid, $filter->usersearch, $cmid, $params]);

        $formdata = (object) [
            'courseid' => $course->id,
            'cmid' => $cmid,
            'initialsbars' => $initialsbar,
        ];
        $dropdowncontent = $OUTPUT->render_from_template('core/initials_dropdown_form', $formdata);

        $buttoncontent = $currentfilter !== '' ? $currentfilter : get_string('filterbyname', 'core_grades');
        $buttonheader = $currentfilter !== '' ? get_string('name') : null;

        return new comboboxsearch(false, $buttoncontent, $dropdowncontent,
            'initials-selector', 'initialswidget', 'initialsdropdown', $buttonheader, true,
            get_string('filterbyname', 'core_grades'), 'nameinitials',
            json_encode(['first' => $firstinitial, 'last' => $lastinitial]));
    }
}
