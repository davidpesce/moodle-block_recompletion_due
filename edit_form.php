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
 * Block edit form class for the block_recompletion_due plugin.
 *
 * @package   block_recompletion_due
 * @copyright 2025, David Pesce <david.pesce@exputo.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class block_recompletion_due_edit_form
 *
 * @package   block_recompletion_due
 * @author    David Pesce <david.pesce@exputo.com>
 */
class block_recompletion_due_edit_form extends block_edit_form {

    /**
     * specific_definition
     *
     * @param moodleform $mform
     * @return void
     */
    protected function specific_definition($mform) {
    global $DB;

        // Section heading.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Title field.
        $mform->addElement('text', 'config_title', get_string('blocktitle', 'block_recompletion_due'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_recompletion_due'));

        // Upcoming window field (days).
        $mform->addElement('text', 'config_upcomingwindow', get_string('upcomingwindow', 'block_recompletion_due'));
        $mform->setType('config_upcomingwindow', PARAM_INT);
        $mform->setDefault('config_upcomingwindow', 178);
        $mform->addHelpButton('config_upcomingwindow', 'upcomingwindow', 'block_recompletion_due');

        // New hire courses multi-select.
        $courses = $DB->get_records_menu('course', null, 'fullname', 'id, fullname');
        $mform->addElement('autocomplete', 'config_newhirecourses', get_string('newhirecourses', 'block_recompletion_due'), $courses, ['multiple' => true]);
        $mform->setType('config_newhirecourses', PARAM_INT);
        $mform->addHelpButton('config_newhirecourses', 'newhirecourses', 'block_recompletion_due');

        // New hire window field (seconds).
        $mform->addElement('text', 'config_newhirewindow', get_string('newhirewindow', 'block_recompletion_due'));
        $mform->setType('config_newhirewindow', PARAM_INT);
        $mform->setDefault('config_newhirewindow', 5011200);
        $mform->addHelpButton('config_newhirewindow', 'newhirewindow', 'block_recompletion_due');

        // Initial training window field (seconds).
        $mform->addElement('text', 'config_initialtrainingwindow', get_string('initialtrainingwindow', 'block_recompletion_due'));
        $mform->setType('config_initialtrainingwindow', PARAM_INT);
        $mform->setDefault('config_initialtrainingwindow', 30);
        $mform->addHelpButton('config_initialtrainingwindow', 'initialtrainingwindow', 'block_recompletion_due');

        // Recompletion window field (seconds).
        $mform->addElement('text', 'config_recompletionwindow', get_string('recompletionwindow', 'block_recompletion_due'));
        $mform->setType('config_recompletionwindow', PARAM_INT);
        $mform->setDefault('config_recompletionwindow', 5011200);
        $mform->addHelpButton('config_recompletionwindow', 'recompletionwindow', 'block_recompletion_due');

    }
}