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
 * Management page for local_groupgrades.
 *
 * URL: /local/groupgrades/manage.php?id=<courseid>
 *
 * @package   local_groupgrades
 * @copyright 2026 Talia Yohanan
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->dirroot . '/local/groupgrades/classes/form/rule_form.php');

// Parameters.
$courseid = required_param('id', PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);
$ruleid   = optional_param('ruleid', 0, PARAM_INT);

// Context + capability check.
$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('moodle/course:manageactivities', $context);

// Page setup.
$pageurl = new moodle_url('/local/groupgrades/manage.php', ['id' => $courseid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manage', 'local_groupgrades'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Handle DELETE action.
if ($action === 'delete' && $ruleid > 0) {
    require_sesskey();

    if ($DB->record_exists('local_groupgrades', ['id' => $ruleid, 'courseid' => $courseid])) {
        // Delete pool items first (cascade not guaranteed in all DBs).
        $DB->delete_records('local_groupgrades_items', ['ruleid' => $ruleid]);
        $DB->delete_records('local_groupgrades', ['id' => $ruleid, 'courseid' => $courseid]);

        redirect($pageurl, get_string('ruledeleted', 'local_groupgrades'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($pageurl);
}

// Handle ADD RULE form.
$mform = new \local_groupgrades\form\rule_form($pageurl, ['courseid' => $courseid]);

if ($mform->is_cancelled()) {
    redirect($pageurl);

} else if ($formdata = $mform->get_data()) {

    $ruletype = $formdata->rule_type;
    $allowed  = ['factor_multiplier', 'bonus_points', 'top_n_of_m'];
    if (!in_array($ruletype, $allowed, true)) {
        $ruletype = 'factor_multiplier';
    }

    $record               = new stdClass();
    $record->courseid     = $courseid;
    $record->groupid      = (int)$formdata->groupid;
    $record->gradeitemid  = (int)$formdata->gradeitemid;
    $record->rule_type    = $ruletype;
    $record->timemodified = time();

    if ($ruletype === 'top_n_of_m') {
        $record->rule_value = max(1, (int)$formdata->top_n_count);
        $ruleid = $DB->insert_record('local_groupgrades', $record);

        // Save pool items from the multi-select field.
        $poolids = $formdata->pool_gradeitemids ?? [];
        $poolids = is_array($poolids) ? $poolids : (array)$poolids;
        foreach ($poolids as $poolitemid) {
            $poolitemid = (int)$poolitemid;
            if ($poolitemid > 0) {
                $poolrecord              = new stdClass();
                $poolrecord->ruleid      = $ruleid;
                $poolrecord->gradeitemid = $poolitemid;
                $DB->insert_record('local_groupgrades_items', $poolrecord);
            }
        }
    } else {
        $record->rule_value = (float)$formdata->rule_value;
        $ruleid = $DB->insert_record('local_groupgrades', $record);
    }

    // Apply the new rule immediately to all existing grades for this group.
    $savedrule = $DB->get_record('local_groupgrades', ['id' => $ruleid]);
    if ($savedrule) {
        \local_groupgrades\observer::backfill_rule($savedrule);
    }

    redirect($pageurl, get_string('ruleadded', 'local_groupgrades'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Render page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage', 'local_groupgrades'));

// Clear backfill log if present (no longer displayed).
unset($SESSION->groupgrades_backfill_log);

// Existing rules table.
$rules = $DB->get_records('local_groupgrades', ['courseid' => $courseid], 'id ASC');

if (empty($rules)) {
    echo $OUTPUT->notification(get_string('norules', 'local_groupgrades'),
        \core\output\notification::NOTIFY_INFO);
} else {
    $table                      = new html_table();
    $table->id                  = 'local-groupgrades-rules';
    $table->attributes['class'] = 'generaltable';
    $table->head                = [
        get_string('group',     'local_groupgrades'),
        get_string('ruletype',  'local_groupgrades'),
        get_string('gradeitem', 'local_groupgrades'),
        get_string('rulevalue', 'local_groupgrades'),
        get_string('actions',   'local_groupgrades'),
    ];

    foreach ($rules as $rule) {
        $group      = groups_get_group($rule->groupid);
        $targetitem = \grade_item::fetch(['id' => $rule->gradeitemid]);

        $deleteurl  = new moodle_url($pageurl, [
            'action'  => 'delete',
            'ruleid'  => $rule->id,
            'sesskey' => sesskey(),
        ]);
        $confirmmsg = get_string('confirmdelete', 'local_groupgrades');
        $deletelink = html_writer::link($deleteurl,
            get_string('deleterule', 'local_groupgrades'),
            [
                'class'   => 'btn btn-sm btn-danger',
                'onclick' => 'return confirm(' . json_encode($confirmmsg) . ');',
            ]
        );

        // Build the "rule value / details" cell.
        if ($rule->rule_type === 'top_n_of_m') {
            // Collect pool item names.
            $poolitemids = $DB->get_fieldset_select(
                'local_groupgrades_items', 'gradeitemid', 'ruleid = :rid', ['rid' => $rule->id]);
            $poolnames = [];
            foreach ($poolitemids as $pid) {
                $item = \grade_item::fetch(['id' => $pid]);
                $poolnames[] = $item ? format_string($item->get_name()) : "#{$pid}";
            }
            $detailcell = get_string('poolitemsdetail', 'local_groupgrades', (object)[
                'n'     => (int)$rule->rule_value,
                'items' => implode(', ', $poolnames),
            ]);
        } else {
            $detailcell = format_float((float)$rule->rule_value, 5, true);
        }

        $table->data[] = [
            $group ? format_string($group->name) : '-',
            get_string($rule->rule_type, 'local_groupgrades'),
            $targetitem ? format_string($targetitem->get_name()) : '-',
            $detailcell,
            $deletelink,
        ];
    }

    echo html_writer::table($table);
}

// Add rule form.
echo $OUTPUT->heading(get_string('addrule', 'local_groupgrades'), 4);
$mform->display();

echo $OUTPUT->footer();
