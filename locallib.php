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

namespace local_suap;

require_once("$CFG->dirroot/course/externallib.php");
require_once("$CFG->dirroot/enrol/externallib.php");
require_once("$CFG->dirroot/message/externallib.php");
require_once("$CFG->dirroot/message/output/popup/externallib.php");


function get_last_sort_order($tablename)
{
    global $DB;
    $l = $DB->get_record_sql('SELECT coalesce(max(sortorder), 0) + 1 as sortorder from {' . $tablename . '}');
    return $l->sortorder;
}


function get_or_create($tablename, $keys, $values)
{
    global $DB;
    $record = $DB->get_record($tablename, $keys);
    if (!$record) {
        $record = (object)array_merge($keys, $values);
        $record->id = $DB->insert_record($tablename, $record);
    }
    return $record;
}


function create_or_update($tablename, $keys, $allways, $updates = [], $insert = [])
{
    global $DB;
    $record = $DB->get_record($tablename, $keys);
    if ($record) {
        foreach (array_merge($keys, $allways, $updates) as $attr => $value) {
            $record->{$attr} = $value;
        }
        $DB->update_record($tablename, $record);
    } else {
        $record = (object)array_merge($keys, $allways, $insert);
        $record->id = $DB->insert_record($tablename, $record);
    }
    return $record;
}

function dienow($message, $code)
{
    http_response_code($code);
    die(json_encode(["message" => $message, "code" => $code]));
}

function config($name)
{
    return get_config('local_suap', $name);
}

function aget($array, $key, $default = null)
{
    return \key_exists($key, $array) ? $array[$key] : $default;
}

function get_recordset_as_json($sql, $params)
{
    global $DB;

    $result = "[";
    $sep = '';
    foreach ($DB->get_recordset_sql($sql, $params) as $disciplina) {
        $result .= $sep . json_encode($disciplina);
        $sep = ',';
    }
    return $result . "]";
}

function get_recordset_as_array($sql, $params)
{
    global $DB;

    $result = [];
    foreach ($DB->get_recordset_sql($sql, $params) as $disciplina) {
        $result[] = $disciplina;
    }
    return $result;
}

function get_languages() {
    $languages = get_string_manager()->get_list_of_translations();
    $options = array_keys($languages);
    return implode("\n", $options);
}
