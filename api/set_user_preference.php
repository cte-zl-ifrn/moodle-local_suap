<?php

namespace local_suap;

require_once('../../../config.php');
require_once('../locallib.php');
require_once("servicelib.php");

class set_user_preference_service extends \local_suap\service
{
    function do_call()
    {
        global $DB, $USER;

        // 🔍 1. Buscar usuário pelo username informado
        $username = optional_param('username', null, PARAM_USERNAME);
        if ($username === null) {
            throw new \Exception("Parâmetro 'username' é obrigatório", 400);
        }

        $USER = $DB->get_record('user', ['username' => strtolower($_GET['username'])]);
        if (!$USER) {
            throw new \Exception('Usuário não encontrado.', 404);
        }

        require_capability('moodle/user:editownprofile', \context_user::instance($USER->id));

        // 🧰 2. Pega os parâmetros enviados
        $name = optional_param('name', null, PARAM_ALPHANUMEXT);
        $value = optional_param('value', null, PARAM_RAW);

        if ($name === null || $value === null) {
            throw new \Exception("Parâmetros 'name' e 'value' são obrigatórios", 400);
        }

        // ✅ 3. Salva a preferência usando a API oficial
        $value = in_array($value, [true, 'true', 1, '1'], true) ? '1' : '0';
        set_user_preference($name, $value, $USER->id);

        // 📬 4. Retorna uma resposta simples em JSON
        return [
            'error' => false,
            'message' => 'Preferência atualizada com sucesso',
            'user' => [
                'id' => $USER->id,
                'username' => $USER->username,
                'fullname' => fullname($USER)
            ],
            'preference' => [
                'name' => $name,
                'value' => $value
            ]
        ];
    }
}
