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
 * Block definition class for the block_recompletion_due plugin.
 *
 * @package   block_recompletion_due
 * @copyright 2025, David Pesce <david.pesce@exputo.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class block_recompletion_due extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_recompletion_due');
    }

    public function specialization() {
        if ($this->config) {
            $this->title = $this->config->title;
        } else {
            $this->title = get_string('pluginname', 'block_recompletion_due');
        }
    }

    public function instance_allow_config(): bool {
        return true;
    }

    public function get_content() {
        global $USER, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $userid = $USER->id;

        // Get newhirewindow from instance config, fallback to default if not set.
        $newhirewindow = isset($this->config->newhirewindow) && is_numeric($this->config->newhirewindow)
            ? (int)$this->config->newhirewindow
            : 5011200;

        $recompletionwindow = isset($this->config->recompletionwindow) && is_numeric($this->config->recompletionwindow)
            ? (int)$this->config->recompletionwindow
            : 5011200;

        $sql = "
            SELECT
            c.id AS courseid,
            c.fullname AS course,
            CASE
                WHEN GREATEST(COALESCE(cc.timecompleted, 0), COALESCE(rcc.timecompleted, 0)) != 0
                THEN FROM_UNIXTIME(GREATEST(COALESCE(cc.timecompleted, 0), COALESCE(rcc.timecompleted, 0)) + CAST(rcfg.value AS UNSIGNED) + $recompletionwindow, '%Y-%m-%d')
                ELSE FROM_UNIXTIME(ue.timecreated + $newhirewindow, '%Y-%m-%d')
            END AS next_due,
            CASE
                WHEN GREATEST(COALESCE(cc.timecompleted, 0), COALESCE(rcc.timecompleted, 0)) = 0
                THEN DATEDIFF(FROM_UNIXTIME(ue.timecreated + $newhirewindow, '%Y-%m-%d'), NOW())
                ELSE DATEDIFF(
                FROM_UNIXTIME(GREATEST(COALESCE(cc.timecompleted, 0), COALESCE(rcc.timecompleted, 0)) + CAST(rcfg.value AS UNSIGNED) + $recompletionwindow, '%Y-%m-%d'),
                NOW()
                )
            END AS days_til_due

            FROM mdl_course c
            JOIN mdl_course_categories ccat ON c.category = ccat.id
            JOIN mdl_context ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            JOIN mdl_role_assignments ra ON ctx.id = ra.contextid
            JOIN mdl_enrol e ON c.id = e.courseid AND e.id = ra.itemid AND e.status = 0
            JOIN mdl_user u ON ra.userid = u.id
            JOIN mdl_user_enrolments ue ON e.id = ue.enrolid AND u.id = ue.userid AND ue.status = 0
            LEFT JOIN mdl_course_completions cc ON c.id = cc.course AND u.id = cc.userid
            LEFT JOIN (
            SELECT userid, course, MAX(timecompleted) AS timecompleted
            FROM mdl_local_recompletion_cc
            GROUP BY userid, course
            ) AS rcc ON c.id = rcc.course AND u.id = rcc.userid
            LEFT JOIN mdl_local_recompletion_config rcfg ON c.id = rcfg.course AND rcfg.name = 'recompletionduration'
            WHERE u.suspended = 0
            AND u.id = :userid
            AND c.category != 3
            AND rcfg.value IS NOT NULL AND CAST(rcfg.value AS UNSIGNED) > 0
        ";

        $records = $DB->get_records_sql($sql, ['userid' => $userid]);

        $overdue = array_filter($records, fn($r) => $r->days_til_due < 0);
        $upcomingwindow = isset($this->config->upcomingwindow) && is_numeric($this->config->upcomingwindow)
            ? (int)$this->config->upcomingwindow
            : 178;
        $upcoming = array_filter($records, fn($r) => $r->days_til_due >= 0 && $r->days_til_due <= $upcomingwindow);

        $output = '';


        // Overdue Items
        $output .= html_writer::tag('h4', get_string('overduetabletitle', 'block_recompletion_due'));
        $overduetable = new html_table();
        $overduetable->head = [
            get_string('course'),
            get_string('duedate', 'block_recompletion_due'),
            get_string('daysremaining', 'block_recompletion_due')
        ];
        if (!empty($overdue)) {
            foreach ($overdue as $item) {
                $courselink = html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $item->courseid]),
                    format_string($item->course)
                );
                $overduetable->data[] = [$courselink, $item->next_due, $item->days_til_due];
            }
        } else {
            $overduetable->data[] = [
                html_writer::span(get_string('nooverdue', 'block_recompletion_due'), 'nooverdue-message'),
                '',
                ''
            ];
        }
        $output .= html_writer::table($overduetable);

        // Upcoming Items
        $output .= html_writer::tag('h4', get_string('upcomingtabletitle', 'block_recompletion_due'));
        $upcomingtable = new html_table();
        $upcomingtable->head = [
            get_string('course'),
            get_string('duedate', 'block_recompletion_due'),
            get_string('daysremaining', 'block_recompletion_due')
        ];
        if (!empty($upcoming)) {
            foreach ($upcoming as $item) {
                $courselink = html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $item->courseid]),
                    format_string($item->course)
                );
                $upcomingtable->data[] = [$courselink, $item->next_due, $item->days_til_due];
            }
        } else {
            $upcomingtable->data[] = [
                html_writer::span(get_string('noupcoming', 'block_recompletion_due'), 'noupcoming-message'),
                '',
                ''
            ];
        }
        $output .= html_writer::table($upcomingtable);

        $this->content->text = $output;

        $this->content->footer = '';
        return $this->content;
    }

    public function applicable_formats() {
        return [
            'my' => true,
            'site' => false
        ];
    }
}