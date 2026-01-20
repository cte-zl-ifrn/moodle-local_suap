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
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function exception_handler($exception) {
    /*
        200 – 208, 226, 
        300 – 305, 307, 308
        400 – 417, 422 – 424, 426, 428 – 429, 431
        500 – 508, 510 – 511
    */
    $error_code = $exception->getCode() ?: 500;
    http_response_code($error_code);
    die(json_encode(["error"=>["message"=>$exception->getMessage(), "code"=>$error_code]]));
}

try {
    // Desabilita verificação CSRF para esta API
    define('NO_MOODLE_COOKIES', true);
    
    require_once('../../../config.php');
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    set_exception_handler('\local_suap\exception_handler');
    
    $whitelist = [
        'get_diarios',
        'get_atualizacoes_counts',

        'set_favourite_course',
        'set_visible_course',
        'set_user_preference',
        
        'sync_user_preference',
        'sync_up_enrolments',
        // 'sync_down_attendances',
        'sync_down_grades'
    ];
    $params = explode('&', $_SERVER["QUERY_STRING"]);
    $service_name = $params[0];

    if ( (!in_array($service_name, $whitelist)) ) {
        throw new \Exception("Serviço não existe", 404);       
    }
    require_once "$service_name.php";

    $service_class = "\local_suap\\$service_name"."_service";
    $service = new $service_class();
    $service->call();
} catch (\Exception $e) {   
    /*
        200 – 208, 226, 
        300 – 305, 307, 308
        400 – 417, 422 – 424, 426, 428 – 429, 431
        500 – 508, 510 – 511
    */
    exception_handler($e);
}