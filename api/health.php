<?php
/**
 * SUAP Integration - Health check service
 *
 * Validates the authentication token without performing any side effects.
 * Returns 200 OK when the token is valid, 401 Unauthorized otherwise.
 *
 * @package     local_suap
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_suap;

require_once("servicelib.php");

class health_service extends service
{
    function do_call()
    {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/local/suap/version.php');
        return [
            "status"          => "ok",
            "moodle_version"  => $CFG->version,
            "moodle_release"  => $CFG->release,
            "plugin_version"  => $plugin->version,
            "plugin_release"  => $plugin->release,
        ];
    }
}
