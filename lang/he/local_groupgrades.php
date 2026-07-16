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
 * מחרוזות שפה בעברית עבור local_groupgrades.
 *
 * @package   local_groupgrades
 * @copyright 2025 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions']              = 'פעולות';
$string['addrule']              = 'הוסף כלל';
$string['bonus_points']         = 'נקודות בונוס (הוספה לציון)';
$string['confirmdelete']        = 'האם אתה בטוח שברצונך למחוק כלל זה?';
$string['deleterule']           = 'מחק';
$string['factor_multiplier']    = 'מקדם כפל (כפל הציון בערך)';
$string['gradeitem']            = 'פריט ציון יעד';
$string['gradeitem_help']       = 'פריט הציון שהציון הסופי שלו יידרס על ידי כלל זה (למשל: סך הכל של הקורס). עבור כלל "N הגבוהים מתוך M" — זהו המקום שבו הממוצע המחושב ייכתב.';
$string['group']                = 'קבוצה';
$string['invalidrulevalue']     = 'ערך הכלל חייב להיות מספר חיובי.';
$string['invalidtopn']          = 'N חייב להיות מספר שלם חיובי ואינו יכול לעלות על מספר פריטי הציון שנבחרו.';
$string['manage']               = 'ניהול כללי ציון לפי קבוצה';
$string['managegroupgrades']    = 'כללי ציון לפי קבוצה';
$string['missingfields']        = 'אנא מלא את כל השדות הנדרשים.';
$string['norules']              = 'טרם הוגדרו כללי ציון לפי קבוצה עבור קורס זה.';
$string['pluginname']           = 'כללי ציון לפי קבוצה';
$string['pool_gradeitemids']    = 'פריטי ציון לקבוצת המטלות';
$string['pool_gradeitemids_help'] = 'השתמש ב-Ctrl (או ב-Command ב-Mac) כדי לבחור מספר פריטים בו-זמנית, או ב-Shift לבחירת טווח. בחר את פריטי הציון המרכיבים את קבוצת המטלות (למשל: 7 מטלות). התוסף יחשב מחדש את ציון היעד בכל פעם שאחד מפריטים אלה ישתנה עבור תלמיד בקבוצה.';
$string['poolitemsdetail']      = '{$a->n} הגבוהים מתוך: {$a->items}';
$string['ruleadded']            = 'הכלל נוסף בהצלחה.';
$string['ruledeleted']          = 'הכלל נמחק בהצלחה.';
$string['ruletype']             = 'סוג הכלל';
$string['rulevalue']            = 'ערך הכלל';
$string['rulevalue_help']       = 'עבור מקדם כפל: ערך כגון 1.5 (הציון יוכפל ב-1.5). עבור נקודות בונוס: מספר הנקודות להוספה (למשל: 10).';
$string['top_n_count']          = 'מספר הציונים הגבוהים לשימוש (N)';
$string['top_n_count_help']     = 'כמה מפריטי קבוצת המטלות בעלי הציון הגבוה ביותר ייכנסו לחישוב הממוצע. חייב להיות בין 1 למספר הפריטים שנבחרו בקבוצת המטלות.';
$string['top_n_of_m']           = 'N הגבוהים מתוך M (ממוצע N הציונים הגבוהים מהפריטים שנבחרו)';
