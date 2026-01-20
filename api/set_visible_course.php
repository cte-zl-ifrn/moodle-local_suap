<?php

namespace local_suap;

// Desabilita verificação CSRF para esta API
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../config.php');
require_once('../../../course/externallib.php');
require_once('../locallib.php');
require_once("servicelib.php");

class set_visible_course_service extends \local_suap\service
{

    function do_call()
    {
        global $DB, $USER;

        $USER = $DB->get_record('user', ['username' => strtolower($_GET['username'])]);

        $coursecontext = \context_course::instance($_GET['courseid']);
        if (!has_capability('moodle/course:visibility', $coursecontext, $USER)) {
            throw new \Exception('Sem permissão de alterar a visibilidade deste curso.', 403);
        }

        $course = $DB->get_record('course', ['id' => $_GET['courseid']]);

        $visible = $_GET['visible'];

        return $this->execute($course, $visible);
    }

    function execute($course, $visible)
    {
        global $DB;

        $course->visible = $visible;
        $DB->update_record('course', $course);
        return ["error" => false];
    }
}