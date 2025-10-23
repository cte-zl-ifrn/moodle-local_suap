<?php

namespace local_suap;

require_once('../../../config.php');
require_once("../locallib.php");
require_once("servicelib.php");

class sync_user_preference_service extends \local_suap\service
{
    function do_call()
    {
        global $USER;

        // Parâmetros recebidos via GET
        $category = required_param('category', PARAM_ALPHANUM);
        $key      = required_param('key', PARAM_TEXT);
        $value    = required_param('value', PARAM_RAW); // true, false ou string

        $username = $USER->username;

        // URL do endpoint Django
        $url = 'http://painel/api/v1/set_user_preference/'
             . '?username=' . urlencode($username)
             . '&category=' . urlencode($category)
             . '&key=' . $key
             . '&value=' . urlencode($value);

        // 🔹 Faz a requisição
        $curl = new \curl();
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 10,
            'CURLOPT_HTTPHEADER' => ["Authorization: Token changeme"],
            'CURLOPT_FAILONERROR' => true
        ];

        $response = $curl->get($url, [], $options);

        if ($response === false) {
            throw new \Exception('Falha ao conectar ao painel Django', 500);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['status' => 'erro', 'mensagem' => 'Resposta inválida', 'resposta' => $response];
            return $url;
            // throw new \Exception('Resposta inválida do painel Django', 500);
        }

        // 🔹 Salva as preferências no Moodle
        // if (isset($data['settings']) && is_array($data['settings'])) {
        //     foreach ($data['settings'] as $category => $keys) {
        //         foreach ($keys as $key => $value) {
        //             set_user_preference($category . '_' . $key, $value, $USER->id);
        //         }
        //     }
        // }

        return ['status' => 'ok', 'data' => $data];
    }
}
