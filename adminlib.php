<?php

/**
 * SUAP Integration
 *
 * This module provides extensive analytics on a platform of choice
 * Currently support Google Analytics and Piwik
 *
 * @package     local_suap
 * @category    upgrade
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class suap_admin_settingspage extends admin_settingpage
{

    public function __construct($admin_mode)
    {
        $plugin_name = 'local_suap';
        parent::__construct($plugin_name, get_string('pluginname', $plugin_name), 'moodle/site:config', false, NULL);
        $this->setup($admin_mode);
    }

    function _($str, $args = null, $lazyload = false)
    {
        return get_string($str, $this->name);
    }

    function add_heading($name)
    {
        $this->add(new admin_setting_heading("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc")));
    }

    function add_configtext($name, $default = '')
    {
        $this->add(new admin_setting_configtext("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function add_configtextarea($name, $default = '')
    {
        $this->add(new admin_setting_configtextarea("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function add_configcheckbox($name, $default = 0)
    {
        $this->add(new admin_setting_configcheckbox("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function setup($admin_mode)
    {
        global $CFG;
        if ($admin_mode) {
            $default_enrol = is_dir(dirname(__FILE__) . '/../../enrol/suap/') ? 'suap' : 'manual';
            $this->add_heading('auth_token_header');
            $this->add_configtext("auth_token", 'changeme');

            $this->add_heading('top_category_header');
            $this->add_configtext("top_category_idnumber", 'diarios');
            $this->add_configtext("top_category_name", 'Diários');
            $this->add_configtext("top_category_parent", '0');

            $this->add_heading('user_and_enrolment_header');
            $this->add_configtextarea("default_user_preferences", "auth_forcepasswordchange=0\nhtmleditor=0\nemail_bounce_count=1\nemail_send_count=1\nemail_bounce_count=0\nvisual_preference=1");

            $this->add_configtext("default_student_auth", 'oauth2');
            $this->add_configtext("default_student_role_id", 5);
            $this->add_configtext("default_student_enrol_type", $default_enrol);

            $this->add_configtext("default_teacher_auth", 'oauth2');
            $this->add_configtext("default_teacher_role_id", 3);
            $this->add_configtext("default_teacher_enrol_type", $default_enrol);

            $this->add_configtext("default_assistant_auth", 'oauth2');
            $this->add_configtext("default_assistant_role_id", 4);
            $this->add_configtext("default_assistant_enrol_type", $default_enrol);

            $this->add_configtext("default_instructor_auth", 'oauth2');
            $this->add_configtext("default_instructor_role_id", 4);
            $this->add_configtext("default_instructor_enrol_type", $default_enrol);

            $this->add_heading('notes_to_sync_header');
            $this->add_configtext("notes_to_sync", "'N1', 'N2', 'N3' , 'N4', 'NAF'");

            $this->add_heading('groups_in_course_header');
            $this->add_configcheckbox("course_group_entrada", 1);
            $this->add_configcheckbox("course_group_turma", 1);
            $this->add_configcheckbox("course_group_polo", 1);
            $this->add_configcheckbox("course_group_programa", 1);

            $this->add_heading('groups_in_room_header');
            $this->add_configcheckbox("room_group_entrada", 1);
            $this->add_configcheckbox("room_group_turma", 1);
            $this->add_configcheckbox("room_group_polo", 1);
            $this->add_configcheckbox("room_group_programa", 1);
        }
    }
}
