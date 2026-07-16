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
 * English language strings for local_groupgrades.
 *
 * @package   local_groupgrades
 * @copyright 2025 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions']              = 'Actions';
$string['addrule']              = 'Add Rule';
$string['bonus_points']         = 'Bonus Points (add to grade)';
$string['confirmdelete']        = 'Are you sure you want to delete this rule?';
$string['deleterule']           = 'Delete';
$string['factor_multiplier']    = 'Factor Multiplier (multiply grade by value)';
$string['gradeitem']            = 'Target Grade Item';
$string['gradeitem_help']       = 'The grade item whose final grade will be overridden by this rule (e.g. Course Total). For Top N rules this is where the calculated average will be written.';
$string['group']                = 'Group';
$string['invalidrulevalue']     = 'Rule value must be a positive number.';
$string['invalidtopn']          = 'N must be a positive integer and cannot exceed the number of selected pool items.';
$string['manage']               = 'Manage Group Grade Rules';
$string['managegroupgrades']    = 'Group Grade Rules';
$string['missingfields']        = 'Please fill in all required fields.';
$string['norules']              = 'No group grade rules have been defined yet for this course.';
$string['pluginname']           = 'Group Grade Rules';
$string['pool_gradeitemids']    = 'Pool Grade Items';
$string['pool_gradeitemids_help'] = 'Use Ctrl (or Command on Mac) to select multiple items, or Shift to select a range. Select the grade items that form the pool (e.g. the 7 assignments). The plugin will recalculate the target grade every time any of these items changes for a student in the group.';
$string['poolitemsdetail']      = 'Top {$a->n} of: {$a->items}';
$string['ruleadded']            = 'Rule added successfully.';
$string['ruledeleted']          = 'Rule deleted successfully.';
$string['ruletype']             = 'Rule Type';
$string['rulevalue']            = 'Rule Value';
$string['rulevalue_help']       = 'For Factor Multiplier: a multiplier such as 1.5 (multiply grade by 1.5). For Bonus Points: points to add (e.g. 10).';
$string['top_n_count']          = 'Number of top grades to use (N)';
$string['top_n_count_help']     = 'How many of the highest-scoring pool items should be averaged to produce the final grade. Must be between 1 and the number of selected pool items.';
$string['top_n_of_m']           = 'Top N of M (average the N highest grades from selected items)';
