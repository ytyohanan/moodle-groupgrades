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
 * Moodle form for adding a group grade rule.
 *
 * Supports three rule types. Fields are shown/hidden via hideIf:
 *
 *  factor_multiplier / bonus_points
 *    - gradeitemid  (the item to watch AND override)
 *    - rule_value   (factor or bonus amount)
 *
 *  top_n_of_m
 *    - gradeitemid      (TARGET — where the averaged result is written)
 *    - pool_gradeitemids (multi-select — the pool of M items)
 *    - top_n_count      (N — how many top grades to average)
 *
 * @package   local_groupgrades
 * @copyright 2026 Talia Yohanan
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_groupgrades\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to add a new group grade rule.
 *
 * Custom data expected:
 *   'courseid' (int) — the course this rule belongs to.
 */
class rule_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition(): void {
        global $CFG;

        require_once($CFG->libdir . '/grade/grade_item.php');

        $mform    = $this->_form;
        $courseid = (int)$this->_customdata['courseid'];

        // Group selector.
        $groups    = groups_get_all_groups($courseid);
        $groupopts = [];
        foreach ($groups as $group) {
            $groupopts[$group->id] = format_string($group->name);
        }

        if (empty($groupopts)) {
            $mform->addElement('static', 'groupid_info', get_string('group', 'local_groupgrades'),
                get_string('nogroupsincourse', 'group'));
            $mform->addElement('hidden', 'groupid', 0);
        } else {
            $mform->addElement('select', 'groupid', get_string('group', 'local_groupgrades'), $groupopts);
            $mform->addRule('groupid', null, 'required', null, 'client');
        }
        $mform->setType('groupid', PARAM_INT);

        // Rule type (placed BEFORE dependent fields so hideIf can reference it).
        $ruletypes = [
            'factor_multiplier' => get_string('factor_multiplier', 'local_groupgrades'),
            'bonus_points'      => get_string('bonus_points', 'local_groupgrades'),
            'top_n_of_m'        => get_string('top_n_of_m', 'local_groupgrades'),
        ];
        $mform->addElement('select', 'rule_type', get_string('ruletype', 'local_groupgrades'), $ruletypes);
        $mform->setDefault('rule_type', 'factor_multiplier');
        $mform->setType('rule_type', PARAM_ALPHAEXT);

        // Target grade item (all rule types: item to override / write result to).
        $gradeitems     = \grade_item::fetch_all(['courseid' => $courseid]);
        $gradeitemsopts = [];
        if ($gradeitems) {
            foreach ($gradeitems as $item) {
                if (in_array($item->itemtype, ['mod', 'course', 'category'], true)) {
                    $gradeitemsopts[$item->id] = format_string($item->get_name());
                }
            }
        }

        $mform->addElement('select', 'gradeitemid',
            get_string('gradeitem', 'local_groupgrades'), $gradeitemsopts);
        $mform->addRule('gradeitemid', null, 'required', null, 'client');
        $mform->setType('gradeitemid', PARAM_INT);
        $mform->addHelpButton('gradeitemid', 'gradeitem', 'local_groupgrades');

        // Rule value: shown for factor_multiplier and bonus_points.
        $mform->addElement('text', 'rule_value', get_string('rulevalue', 'local_groupgrades'));
        $mform->setType('rule_value', PARAM_RAW);
        $mform->addHelpButton('rule_value', 'rulevalue', 'local_groupgrades');
        // Hide when top_n_of_m is selected.
        $mform->hideIf('rule_value', 'rule_type', 'eq', 'top_n_of_m');

        // Pool items + N count: shown only for top_n_of_m.
        // Use a plain div (not Moodle hideIf) so that disabled-attribute side-effects
        // never prevent submission. A multi-select is used instead of individual
        // advcheckbox elements because advcheckbox exportValues() is unreliable inside
        // dynamically-shown divs, causing $checkedcnt to appear as 0 in validation.
        $mform->addElement('html', '<div id="local-groupgrades-topn-section">');

        $poolselect = $mform->addElement('select', 'pool_gradeitemids',
            get_string('pool_gradeitemids', 'local_groupgrades'), $gradeitemsopts,
            ['size' => min(10, max(4, count($gradeitemsopts)))]
        );
        $poolselect->setMultiple(true);
        $mform->setType('pool_gradeitemids', PARAM_INT);
        $mform->addHelpButton('pool_gradeitemids', 'pool_gradeitemids', 'local_groupgrades');

        $mform->addElement('text', 'top_n_count', get_string('top_n_count', 'local_groupgrades'));
        $mform->setType('top_n_count', PARAM_INT);
        $mform->addHelpButton('top_n_count', 'top_n_count', 'local_groupgrades');

        $mform->addElement('html', '</div>');

        // JS: toggle div visibility + keep pool list in sync with target grade item.
        $mform->addElement('html', '<script>
(function() {
    var allItems = ' . json_encode($gradeitemsopts, JSON_HEX_TAG | JSON_HEX_AMP) . ';

    // Remove the currently-selected target item from the pool multi-select.
    function filterPool() {
        var targetSel = document.getElementById("id_gradeitemid");
        var poolSel   = document.getElementById("id_pool_gradeitemids");
        if (!targetSel || !poolSel) { return; }

        var targetId = parseInt(targetSel.value, 10);

        // Remember which pool items were selected before we rebuild.
        var selected = {};
        Array.prototype.forEach.call(poolSel.options, function(o) {
            if (o.selected) { selected[parseInt(o.value, 10)] = true; }
        });

        // Rebuild options without the target item.
        poolSel.innerHTML = "";
        Object.keys(allItems).forEach(function(id) {
            var numId = parseInt(id, 10);
            if (numId === targetId) { return; }
            var opt = document.createElement("option");
            opt.value = numId;
            opt.textContent = allItems[id];
            opt.selected = !!selected[numId];
            poolSel.appendChild(opt);
        });
    }

    function syncTopN() {
        var rt  = document.querySelector("[name=\'rule_type\']");
        var div = document.getElementById("local-groupgrades-topn-section");
        if (rt && div) {
            div.style.display = (rt.value === "top_n_of_m") ? "" : "none";
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        var rt = document.querySelector("[name=\'rule_type\']");
        if (rt) { rt.addEventListener("change", syncTopN); }
        syncTopN();

        var targetSel = document.getElementById("id_gradeitemid");
        if (targetSel) { targetSel.addEventListener("change", filterPool); }
        filterPool();
    });
})();
</script>');

        // Hidden course id.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Submit button.
        $this->add_action_buttons(false, get_string('addrule', 'local_groupgrades'));
    }

    /**
     * Server-side validation.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $ruletype = $data['rule_type'] ?? '';

        $allowed = ['factor_multiplier', 'bonus_points', 'top_n_of_m'];
        if (!in_array($ruletype, $allowed, true)) {
            $errors['rule_type'] = get_string('missingfields', 'local_groupgrades');
        }

        if ($ruletype === 'top_n_of_m') {
            // Count how many pool items are selected in the multi-select.
            $poolids    = $data['pool_gradeitemids'] ?? [];
            $poolids    = is_array($poolids) ? $poolids : (array)$poolids;
            $checkedcnt = count(array_filter($poolids));

            if ($checkedcnt === 0) {
                $errors['pool_gradeitemids'] = get_string('missingfields', 'local_groupgrades');
            }

            // Validate N.
            $n = (int)($data['top_n_count'] ?? 0);
            if ($n <= 0 || $n > $checkedcnt) {
                $errors['top_n_count'] = get_string('invalidtopn', 'local_groupgrades');
            }
        } else {
            // Validate rule_value for factor/bonus.
            $val = $data['rule_value'] ?? '';
            if (!is_numeric($val) || (float)$val <= 0) {
                $errors['rule_value'] = get_string('invalidrulevalue', 'local_groupgrades');
            }
        }

        return $errors;
    }
}
