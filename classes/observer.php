<?php
/**
 * Local stuff for category enrolment plugin.
 *
 * @package    local_suap
 * @copyright  2022 kelson Medeiros {@link https://github.com/kelsoncm}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


class local_suap_observer {
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        global $DB;
    }

    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;
    }
    
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event) {
        global $DB;
    }

    public static function user_created(\core\event\user_created $event) {
        global $DB;
        $default_user_preferences = get_config('local_suap', 'default_user_preferences');
        $data = $event->get_data();
        $user = $DB->get_record("user", ["id" => $data['objectid']]);

        foreach (preg_split('/\r\n|\r|\n/', $default_user_preferences) as $preference) {
            $parts = explode("=", $preference);
            \set_user_preference($parts[0], $parts[1], $user);
        }
    }

    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;
    }

    public static function user_updated(\core\event\user_updated $event) {
        global $DB;
    }
}
