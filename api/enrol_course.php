<?php

namespace local_suap;

if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../config.php');
require_once('../locallib.php');
require_once("servicelib.php");

class enrol_course_service extends \local_suap\service
{

    function do_call()
    {
        global $DB, $USER;

        $username = strtolower($_GET['username']);
        $courseid = $_GET['courseid'];

        $USER = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        if (is_enrolled(\context_course::instance($courseid), $USER->id)) {
            return ["status" => "already_enrolled"];
        }

        $enrol = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual',
            'status' => 0
        ], '*', MUST_EXIST);

        $plugin = enrol_get_plugin('manual');

        $plugin->enrol_user($enrol, $USER->id, 5);

        return [
            "status" => "enrolled",
            "courseid" => $courseid
        ];
    }

    function execute($userid, $courseid)
    {
        global $DB;

        // busca método de inscrição manual
        $enrol = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual'
        ], '*', MUST_EXIST);

        $plugin = enrol_get_plugin('manual');

        // matricula usuário como estudante (roleid = 5)
        $plugin->enrol_user($enrol, $userid, 5);

        return [
            "status" => "ok",
            "userid" => $userid,
            "courseid" => $courseid
        ];
    }
}