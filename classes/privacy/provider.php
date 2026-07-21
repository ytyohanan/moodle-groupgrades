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
 * Privacy API implementation for local_groupgrades.
 *
 * This plugin does not store personal data in its own tables.
 * It reads and modifies grade records via the core grades subsystem
 * when applying group-specific grade adjustment rules.
 *
 * @package   local_groupgrades
 * @copyright 2026 Talia Yohanan
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_groupgrades\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for local_groupgrades.
 *
 * The plugin's own tables (local_groupgrades, local_groupgrades_items) store
 * course-level configuration only — no userid fields.  Personal data is
 * processed exclusively through the core grades subsystem (grade_grades),
 * which is declared below via a subsystem link.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Declares what personal data this plugin touches.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link(
            'core_grades',
            [],
            'privacy:metadata:core_grades'
        );
        return $collection;
    }

    /**
     * Returns the contexts that contain personal data for the given user.
     * This plugin stores no user data in its own tables.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Returns users who have personal data in the given context.
     * This plugin stores no user data in its own tables.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        // No user data stored in this plugin's own tables.
    }

    /**
     * Exports personal data for the given approved contexts.
     * This plugin stores no user data in its own tables.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        // No user data stored in this plugin's own tables.
    }

    /**
     * Deletes all personal data for all users in the given context.
     * This plugin stores no user data in its own tables.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // No user data stored in this plugin's own tables.
    }

    /**
     * Deletes personal data for the given approved contexts and user.
     * This plugin stores no user data in its own tables.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // No user data stored in this plugin's own tables.
    }

    /**
     * Deletes personal data for the given users within the given context.
     * This plugin stores no user data in its own tables.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        // No user data stored in this plugin's own tables.
    }
}
