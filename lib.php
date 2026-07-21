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
 * Library functions for local_groupgrades.
 *
 * Contains the Moodle navigation hook that injects a "Group Grade Rules" link
 * into the course secondary navigation for users who have the
 * moodle/course:manageactivities capability.
 *
 * @package   local_groupgrades
 * @copyright 2026 Talia Yohanan
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a "Group Grade Rules" link to the course navigation.
 *
 * Called by Moodle's navigation API when building the course navigation tree.
 * The link is only added when the current user has editing-teacher level access.
 *
 * @param navigation_node $navigation The course navigation node to extend.
 * @param stdClass        $course     The current course record.
 * @param context         $context    The course context.
 * @return void
 */
function local_groupgrades_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context $context
): void {
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    $url  = new moodle_url('/local/groupgrades/manage.php', ['id' => $course->id]);
    $node = $navigation->add(
        get_string('managegroupgrades', 'local_groupgrades'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'groupgrades',                    // Key — allows theming/CSS targeting.
        new pix_icon('i/grades', '')
    );

    // Make the node visible in the flat (boost) navigation drawer.
    $node->showinflatnavigation = true;
}
