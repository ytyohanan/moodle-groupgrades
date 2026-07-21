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
 * Event observer: applies group-specific grade rules when a user's grade changes.
 *
 * ## Supported rule types
 *
 * factor_multiplier / bonus_points (Phase 1 — "direct" rules)
 * ─────────────────────────────────────────────────────────────
 * The watched grade item IS the item to override.
 * When that item changes for a student in the matching group, we compute the
 * adjusted value and write it as a teacher override.
 *
 * top_n_of_m (Phase 2 — "pool" rules)
 * ──────────────────────────────────────
 * The teacher defines a pool of M grade items (stored in local_groupgrades_items)
 * and a target grade item (local_groupgrades.gradeitemid) where the result is written.
 * When ANY pool item changes for a student in the group we:
 *   1. Collect all pool grades that have a non-null finalgrade.
 *   2. Sort descending and take the top N (local_groupgrades.rule_value).
 *   3. Average them: sum(top N) / N.
 *   4. Clamp to [target_gradeitem.grademin, target_gradeitem.grademax].
 *   5. Write as an overridden grade on the TARGET item.
 *
 * Because the pool items and the target are DIFFERENT items, Phase 2 recalculates
 * and rewrites the target every time any pool item changes — it deliberately does
 * NOT check is_overridden() on the target. This ensures that subsequent quiz
 * submissions always refresh the result.
 *
 * ## Infinite-loop prevention
 *
 * Both phases set self::$processing = true before calling $gradegrade->update(),
 * which fires user_graded again. The re-entrancy guard at the top of user_graded()
 * returns immediately when the flag is true, breaking the cycle.
 * try/finally ensures the flag is always reset, even on exception.
 *
 * @package   local_groupgrades
 * @copyright 2026 Talia Yohanan
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_groupgrades;

/**
 * Observes grade events and applies configured group grade rules.
 */
class observer {

    /**
     * Re-entrancy guard. True while we are writing our own grade override.
     *
     * @var bool
     */
    protected static bool $processing = false;

    // Public event handler.

    /**
     * Handles the \core\event\user_graded event.
     *
     * Dispatches to Phase 1 (direct rules) and Phase 2 (top_n_of_m pool rules).
     *
     * @param \core\event\user_graded $event
     * @return void
     */
    public static function user_graded(\core\event\user_graded $event): void {
        global $DB;

        if (self::$processing) {
            return;
        }

        $gradeitemid = $event->other['itemid'] ?? null;
        $userid      = $event->relateduserid;
        $courseid    = $event->courseid;

        if (!$gradeitemid || !$userid || !$courseid) {
            return;
        }

        // Resolve user's group membership once; used by both phases.
        $usergroups   = groups_get_user_groups($courseid, $userid);
        $usergroupids = !empty($usergroups[0]) ? array_map('intval', array_values($usergroups[0])) : [];

        if (empty($usergroupids)) {
            return;
        }

        // Phase 1: factor_multiplier / bonus_points — the changed item is the target.
        self::handle_direct_rules($gradeitemid, $userid, $courseid, $usergroupids);

        // Phase 2: top_n_of_m — the changed item is a pool item.
        self::handle_top_n_rules($gradeitemid, $userid, $courseid, $usergroupids);
    }

    // Backfill: apply a newly-created rule to all existing grades.

    /**
     * Applies a rule immediately to every group member who already has a grade,
     * so teachers don't have to wait for a grade event to trigger the rule.
     *
     * Called from manage.php right after a rule is saved.
     *
     * @param \stdClass $rule  Row from local_groupgrades (all fields populated).
     */
    public static function backfill_rule(\stdClass $rule): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_grade.php');

        $members = groups_get_members((int)$rule->groupid, 'u.id');
        if (empty($members)) {
            return;
        }

        if ($rule->rule_type === 'top_n_of_m') {
            foreach ($members as $member) {
                self::apply_top_n_rule($rule, (int)$member->id);
            }
            return;
        }

        // Factor_multiplier / bonus_points.
        $gradeitem = \grade_item::fetch(['id' => (int)$rule->gradeitemid]);
        if (!$gradeitem) {
            return;
        }

        foreach ($members as $member) {
            $userid     = (int)$member->id;
            $gradegrade = \grade_grade::fetch(['itemid' => (int)$rule->gradeitemid, 'userid' => $userid]);

            if (!$gradegrade || $gradegrade->finalgrade === null || $gradegrade->is_overridden()) {
                continue;
            }

            $raw      = (float)$gradegrade->finalgrade;
            $adjusted = ($rule->rule_type === 'factor_multiplier')
                ? $raw * (float)$rule->rule_value
                : $raw + (float)$rule->rule_value;
            $adjusted = max((float)$gradeitem->grademin, min((float)$gradeitem->grademax, $adjusted));

            self::write_override($gradegrade, $adjusted);
        }
    }

    // Phase 1: direct rules (factor_multiplier / bonus_points).

    /**
     * Applies factor_multiplier or bonus_points rules where the changed grade
     * item IS the item to override.
     */
    protected static function handle_direct_rules(
        int $gradeitemid,
        int $userid,
        int $courseid,
        array $usergroupids
    ): void {
        global $CFG, $DB;

        // Find direct rules for this exact grade item (excluding top_n_of_m).
        $rules = $DB->get_records_select(
            'local_groupgrades',
            "courseid = :courseid AND gradeitemid = :gradeitemid AND rule_type != 'top_n_of_m'",
            ['courseid' => $courseid, 'gradeitemid' => $gradeitemid]
        );

        if (empty($rules)) {
            return;
        }

        // Find the first rule whose group the user belongs to.
        $matchingrule = null;
        foreach ($rules as $rule) {
            if (in_array((int)$rule->groupid, $usergroupids, true)) {
                $matchingrule = $rule;
                break;
            }
        }

        if ($matchingrule === null) {
            return;
        }

        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_grade.php');

        $gradeitem  = \grade_item::fetch(['id' => $gradeitemid]);
        $gradegrade = \grade_grade::fetch(['itemid' => $gradeitemid, 'userid' => $userid]);

        if (!$gradeitem || !$gradegrade || $gradegrade->finalgrade === null) {
            return;
        }

        // Skip if already overridden — avoids double-applying factor and
        // respects manual teacher overrides.
        if ($gradegrade->is_overridden()) {
            return;
        }

        $rawgrade = (float)$gradegrade->finalgrade;
        $adjusted = ($matchingrule->rule_type === 'factor_multiplier')
            ? $rawgrade * (float)$matchingrule->rule_value
            : $rawgrade + (float)$matchingrule->rule_value;

        $adjusted = max((float)$gradeitem->grademin, min((float)$gradeitem->grademax, $adjusted));

        self::write_override($gradegrade, $adjusted);
    }

    // Phase 2: top_n_of_m rules.

    /**
     * Handles top_n_of_m rules where the changed item is one of the pool items.
     * The result is written to the rule's target grade item (gradeitemid).
     */
    protected static function handle_top_n_rules(
        int $gradeitemid,
        int $userid,
        int $courseid,
        array $usergroupids
    ): void {
        global $CFG, $DB;

        // Find top_n_of_m rules whose pool contains the changed item.
        $sql = "SELECT r.*
                  FROM {local_groupgrades} r
                  JOIN {local_groupgrades_items} ri ON ri.ruleid = r.id
                 WHERE ri.gradeitemid = :gradeitemid
                   AND r.courseid    = :courseid
                   AND r.rule_type   = 'top_n_of_m'";

        $rules = $DB->get_records_sql($sql, ['gradeitemid' => $gradeitemid, 'courseid' => $courseid]);

        if (empty($rules)) {
            return;
        }

        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_grade.php');

        foreach ($rules as $rule) {
            if (!in_array((int)$rule->groupid, $usergroupids, true)) {
                continue;
            }

            self::apply_top_n_rule($rule, $userid);
            // Apply only the first matching rule per group.
            break;
        }
    }

    /**
     * Calculates and writes the "average of top N" grade for a single rule.
     *
     * @param stdClass $rule   Row from local_groupgrades (rule_type = top_n_of_m).
     * @param int      $userid The student to recalculate for.
     */
    protected static function apply_top_n_rule(\stdClass $rule, int $userid): void {
        global $DB;

        $n = max(1, (int)$rule->rule_value);

        // Collect all pool grade item IDs for this rule.
        $poolids = $DB->get_fieldset_select(
            'local_groupgrades_items',
            'gradeitemid',
            'ruleid = :ruleid',
            ['ruleid' => $rule->id]
        );

        if (empty($poolids)) {
            return;
        }

        // Fetch finalgrade for each pool item for this user (nulls excluded).
        [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $poolids), SQL_PARAMS_NAMED, 'gitem');
        $inparams['userid'] = $userid;

        $sql = "SELECT gg.finalgrade
                  FROM {grade_grades} gg
                 WHERE gg.itemid $insql
                   AND gg.userid = :userid
                   AND gg.finalgrade IS NOT NULL";

        $grades = $DB->get_fieldset_sql($sql, $inparams);

        if (empty($grades)) {
            return;
        }

        // Sort descending; take top N.
        rsort($grades, SORT_NUMERIC);
        $topgrades = array_slice($grades, 0, $n);
        $adjusted  = array_sum($topgrades) / count($topgrades);

        // Clamp to the TARGET grade item's bounds.
        $targetitem = \grade_item::fetch(['id' => (int)$rule->gradeitemid]);
        if (!$targetitem) {
            return;
        }

        $adjusted = max((float)$targetitem->grademin, min((float)$targetitem->grademax, $adjusted));

        // Fetch or create the target grade_grade record.
        $targetgrade = \grade_grade::fetch(['itemid' => (int)$rule->gradeitemid, 'userid' => $userid]);
        if (!$targetgrade) {
            // Grade record doesn't exist yet — create a shell so we can override it.
            $targetgrade         = new \grade_grade();
            $targetgrade->itemid = (int)$rule->gradeitemid;
            $targetgrade->userid = $userid;
            $targetgrade->insert();
        }

        // Always update — unlike direct rules, we recalculate on every pool change
        // so the target stays in sync even after multiple quiz submissions.
        self::write_override($targetgrade, $adjusted);
    }

    // Shared helper.

    /**
     * Writes an overridden finalgrade and sets overridden = time().
     * Wrapped in the re-entrancy guard so the resulting user_graded event
     * is silently ignored.
     *
     * @param \grade_grade $gradegrade The record to update.
     * @param float        $value      The adjusted grade to write.
     */
    protected static function write_override(\grade_grade $gradegrade, float $value): void {
        self::$processing = true;
        try {
            $gradegrade->finalgrade = $value;
            $gradegrade->overridden = time();
            $gradegrade->update('local_groupgrades');
        } finally {
            self::$processing = false;
        }
    }
}
