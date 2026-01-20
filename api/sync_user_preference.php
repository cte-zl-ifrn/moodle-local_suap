<?php
namespace local_suap;

// Desabilita verificação CSRF para esta API
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../config.php');
require_once("../locallib.php");

// Removido require_login() e require_sesskey() pois esta é uma API externa
// A autenticação é feita via token no código abaixo

$sync_up_auth_token = config('auth_token');
$painel_url = config('painel_url');

// força saída JSON limpa
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

// Função para saída JSON consistente
function output_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Captura todos os erros como exceção (para não poluir o JSON)
set_error_handler(function($severity, $message, $file, $line) {
    throw new \ErrorException($message, 500, $severity, $file, $line);
});

try {
    global $USER;

    // Parâmetros via GET
    $category = required_param('category',  PARAM_RAW);
    $key      = required_param('key', PARAM_RAW);
    $value    = required_param('value', PARAM_RAW);

    $username = $USER->username;

    $url = $painel_url . '/api/v1/set_user_preference/'
         . '?username=' . urlencode($username)
         . '&category=' . urlencode($category)
         . '&key=' . $key
         . '&value=' . urlencode($value);

    $curl = new \curl();
    $options = [
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_TIMEOUT' => 10,
        'CURLOPT_HTTPHEADER' => ["Authorization: Token $sync_up_auth_token"],
        'CURLOPT_FAILONERROR' => true
    ];

    $response = $curl->get($url, [], $options);
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        output_json([
            'status' => 'erro',
            'mensagem' => 'Resposta inválida do painel Django',
            'resposta' => $response
        ], 500);
    }

    output_json([
        'status' => 'ok',
        'data' => $data
    ]);

} catch (\Exception $e) {
    output_json([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ], $e->getCode() ?: 500);
}