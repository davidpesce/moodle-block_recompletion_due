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

        $this->content = new stdClass;
        $this->content->text = '';

        $userid = $USER->id;
        $now = time();
        $sixmonths = $now + (86400 * 30 * 6); // Approximate 6 months

        $sql = "
            WITH valid_recompletion AS (
                SELECT course, CAST(value AS UNSIGNED) AS recompletionduration
                FROM {local_recompletion_config}
                WHERE name = 'recompletionduration' AND CAST(value AS UNSIGNED) > 0
            ),
            latest_cc AS (
                SELECT course, userid, MAX(timecompleted) AS timecompleted
                FROM {course_completions}
                WHERE userid = :userid
                GROUP BY course, userid
            ),
            latest_rcc AS (
                SELECT course, userid, MAX(timecompleted) AS timecompleted
                FROM {local_recompletion_cc}
                WHERE userid = :userid2
                GROUP BY course, userid
            ),
            latest_enrol AS (
                SELECT e.courseid AS course, ue.userid, MAX(ue.timecreated) AS timecreated
                FROM {user_enrolments} ue
                JOIN {enrol} e ON ue.enrolid = e.id
                WHERE ue.userid = :userid3 AND ue.status = 0 AND e.status = 0
                  AND (ue.timeend = 0 OR ue.timeend > UNIX_TIMESTAMP())
                GROUP BY e.courseid, ue.userid
            )
            SELECT 
                c.id AS courseid,
                c.fullname,
                le.timecreated AS enrol_time,
                cc.timecompleted AS completed_time,
                rcc.timecompleted AS last_recompletion_time,
                rc.recompletionduration AS recompletion_interval_seconds,

                CASE
                    WHEN cc.timecompleted IS NULL AND rcc.timecompleted IS NULL THEN le.timecreated
                    ELSE GREATEST(
                        COALESCE(cc.timecompleted, 0), 
                        COALESCE(rcc.timecompleted, 0),
                        le.timecreated
                    )
                END AS base_ts,

                CASE
                    WHEN cc.timecompleted IS NULL AND rcc.timecompleted IS NULL THEN le.timecreated + 604800
                    ELSE GREATEST(
                        COALESCE(cc.timecompleted, 0), 
                        COALESCE(rcc.timecompleted, 0),
                        le.timecreated
                    ) + rc.recompletionduration
                END AS next_due_ts,

                FROM_UNIXTIME(
                    CASE
                        WHEN cc.timecompleted IS NULL AND rcc.timecompleted IS NULL THEN le.timecreated + 604800
                        ELSE GREATEST(
                            COALESCE(cc.timecompleted, 0), 
                            COALESCE(rcc.timecompleted, 0),
                            le.timecreated
                        ) + rc.recompletionduration
                    END
                ) AS next_due

            FROM latest_enrol le
            JOIN {course} c ON c.id = le.course AND c.visible = 1
            JOIN valid_recompletion rc ON rc.course = c.id
            LEFT JOIN latest_cc cc ON cc.course = c.id AND cc.userid = le.userid
            LEFT JOIN latest_rcc rcc ON rcc.course = c.id AND rcc.userid = le.userid
            ORDER BY next_due_ts ASC
        ";

        $params = [
            'userid' => $userid,
            'userid2' => $userid,
            'userid3' => $userid
        ];

        $records = $DB->get_records_sql($sql, $params);

        $overdue = [];
        $upcoming = [];

        foreach ($records as $record) {
            if ($record->next_due_ts < $now) {
                $overdue[] = $record;
            } elseif ($record->next_due_ts <= $sixmonths) {
                $upcoming[] = $record;
            }
        }

        if (empty($overdue) && empty($upcoming)) {
            $this->content->text .= get_string('nothingdue', 'block_recompletion_due');
            return $this->content;
        }

        $make_table = function($title, $items) {
            $output = html_writer::tag('h4', $title);
            $output .= html_writer::start_tag('table', ['class' => 'generaltable']);
            $output .= html_writer::start_tag('thead');
            $output .= html_writer::tag('tr',
                html_writer::tag('th', 'Course') .
                html_writer::tag('th', 'Due Date')
            );
            $output .= html_writer::end_tag('thead');
            $output .= html_writer::start_tag('tbody');
            foreach ($items as $item) {
                $url = new moodle_url('/course/view.php', ['id' => $item->courseid]);
                $link = html_writer::link($url, format_string($item->fullname));
                $due = userdate($item->next_due_ts);
                $output .= html_writer::tag('tr',
                    html_writer::tag('td', $link) .
                    html_writer::tag('td', $due)
                );
            }
            $output .= html_writer::end_tag('tbody');
            $output .= html_writer::end_tag('table');
            return $output;
        };

        if (!empty($overdue)) {
            $this->content->text .= $make_table('Overdue Training', $overdue);
        }

        if (!empty($upcoming)) {
            $this->content->text .= $make_table('Training Due Within 6 Months', $upcoming);
        }

        return $this->content;
    }

    public function applicable_formats() {
        return [
            'my' => true,
            'site' => false
        ];
    }
}