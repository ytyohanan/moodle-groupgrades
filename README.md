# Group Grade Rules — local_groupgrades

A Moodle local plugin that lets teachers define **group-specific grade adjustment rules**.
Designed for institutions that need to give certain student groups (e.g. IDF reservists / Miluim students) a modified grade automatically, without changing the original submission.

## Features

Three rule types are supported:

| Rule Type | What it does |
|-----------|--------------|
| **Factor Multiplier** | Multiplies the student's grade by a factor (e.g. `1.5` turns 60 into 90) |
| **Bonus Points** | Adds a fixed number of points to the grade (e.g. `+10`) |
| **Top N of M** | Averages the N highest grades from a pool of M grade items and writes the result to a target grade item |

All adjustments are applied as **teacher overrides** so they are visible in the gradebook and do not silently alter the original grade record.

## Requirements

- Moodle 4.4 or later (tested on 4.5)
- PHP 8.1+
- Groups must be configured in the course before rules can be created

## Installation

### Option A — Upload via Moodle admin UI

1. Download the repository as a ZIP file (GitHub → **Code → Download ZIP**).
2. Log in to Moodle as administrator.
3. Go to **Site administration → Plugins → Install plugins**.
4. Upload the ZIP and follow the on-screen steps.
5. Click **Upgrade Moodle database now** when prompted.

### Option B — Manual install (server/SSH)

```bash
# Copy the plugin folder into Moodle's local directory
cp -r local_groupgrades /path/to/moodle/local/

# Visit the Moodle upgrade page to install the DB tables
# https://your-moodle-site/admin/index.php
```

### Option C — Docker (development)

```bash
docker cp local_groupgrades moodle_app:/bitnami/moodle/local/
docker exec -u daemon moodle_app /opt/bitnami/php/bin/php \
    /bitnami/moodle/admin/cli/upgrade.php --non-interactive
```

## Usage

1. Go to any course where you have **Teacher** or **Manager** role.
2. In the course navigation, click **Group Grade Rules**.
3. Click **Add Rule** and fill in:
   - **Group** — the student group to target.
   - **Rule Type** — Factor Multiplier, Bonus Points, or Top N of M.
   - **Target Grade Item** — the gradebook item whose final grade will be overridden.
   - **Rule Value** — the multiplier, bonus points, or N (for Top N of M).
   - **Pool Grade Items** *(Top N of M only)* — hold Ctrl/Cmd to select multiple items.
4. Save. The rule applies immediately to all existing grades and automatically to future grades.

### Example: Miluim bonus

| Field | Value |
|-------|-------|
| Group | Miluim students |
| Rule Type | Bonus Points |
| Target Grade Item | Course Total |
| Rule Value | 10 |

Every time a student in the "Miluim students" group receives a grade on the Course Total, 10 points are added automatically.

### Example: Top 3 of 5 assignments

| Field | Value |
|-------|-------|
| Group | All students |
| Rule Type | Top N of M |
| Pool Grade Items | Assignment 1, Assignment 2, Assignment 3, Assignment 4, Assignment 5 |
| N | 3 |
| Target Grade Item | Final Assignment Grade |

The plugin averages the 3 highest assignment scores and writes the result to "Final Assignment Grade" every time any assignment is graded.

## Important Notes

- **Overrides are permanent** until cleared manually. Once a grade is overridden by this plugin, Moodle will not recalculate it automatically if the source grade changes later (for Factor Multiplier / Bonus Points rules). Use **Top N of M** rules if you need the result to update dynamically.
- **Top N of M** rules always recalculate whenever any pool item changes, so the target stays in sync.
- A student must be a member of the group **at the time of grading** for the rule to apply.
- Rules are applied in order; only the first matching rule per grade item is applied (for direct rules).

## File Structure

```
local/groupgrades/
├── classes/
│   ├── form/
│   │   └── rule_form.php       # Moodle form for adding rules
│   └── observer.php            # Core logic: handles grade events and applies rules
├── db/
│   ├── events.php              # Registers the user_graded event observer
│   ├── install.xml             # Database schema (2 tables)
│   └── upgrade.php             # DB upgrade steps for future versions
├── lang/
│   ├── en/local_groupgrades.php  # English strings
│   └── he/local_groupgrades.php  # Hebrew strings
├── lib.php                     # Navigation hook (adds link in course menu)
├── manage.php                  # Teacher management page (list + add + delete rules)
├── version.php                 # Plugin version metadata
└── README.md
```

## Database Tables

| Table | Purpose |
|-------|---------|
| `local_groupgrades` | One row per rule (group, grade item, rule type, value) |
| `local_groupgrades_items` | Pool grade items for Top N of M rules (many rows per rule) |

## Bug Reports

Please open an issue on the [GitHub Issues](../../issues) page.
Include your Moodle version, PHP version, and a description of the steps to reproduce the problem.

## License

This plugin is licensed under the [GNU General Public License v3 or later](https://www.gnu.org/licenses/gpl-3.0.html).
