<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade helper functions are defined here.
 *
 * @package     local_suap
 * @category    upgrade
 * @copyright   2022 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see         https://docs.moodle.org/dev/Data_definition_API
 * @see         https://docs.moodle.org/dev/XMLDB_creating_new_DDL_functions
 * @see         https://docs.moodle.org/dev/Upgrade_API
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/suap/locallib.php');

function save_course_custom_category($name) {
    global $DB;

    return \local_suap\get_or_create(
        'customfield_category',
        ['name' => $name, 'component' => 'core_course', 'area' => 'course'],
        ['sortorder' => \local_suap\get_last_sort_order('customfield_category'), 'itemid' => 0, 'contextid' => 1, 'descriptionformat' => 0, 'timecreated' => time(), 'timemodified' => time()]
    )->id;
}

function suap_bulk_course_custom_field() {
    global $DB;
    $campus = save_course_custom_category('Campus');
    \local_suap\save_course_custom_field($campus, 'campus_id', 'ID do campus');
    \local_suap\save_course_custom_field($campus, 'campus_sigla', 'Sigla do campus');
    \local_suap\save_course_custom_field($campus, 'campus_descricao', 'Descrição do campus');

    $curso = save_course_custom_category('Curso');
    \local_suap\save_course_custom_field($curso, 'curso_id', 'ID do curso');
    \local_suap\save_course_custom_field($curso, 'curso_codigo', 'Código do curso');
    \local_suap\save_course_custom_field($curso, 'curso_nome', 'Nome do curso');
    \local_suap\save_course_custom_field($curso, 'curso_descricao', 'Descrição do curso');
    \local_suap\save_course_custom_field($curso, 'curso_descricao_historico', 'Descrição do curso que constará no histórico');
    \local_suap\save_course_custom_field($curso, 'curso_titulo_certificado_masculino', 'Título do certificado masculino');
    \local_suap\save_course_custom_field($curso, 'curso_titulo_certificado_feminino', 'Título do certificado feminino');
    \local_suap\save_course_custom_field($curso, 'curso_ch_total', 'Carga horária total do curso');
    \local_suap\save_course_custom_field($curso, 'curso_ch_aula', 'Carga horária da aula');
    \local_suap\save_course_custom_field($curso, 'curso_autoinstrucional', 'Curso é autoinstrucional', 'checkbox');
    \local_suap\save_course_custom_field($curso, 'curso_programa', 'Programa do curso');
    \local_suap\save_course_custom_field($curso, 'curso_modalidade_id', 'ID da modalidade do curso');
    \local_suap\save_course_custom_field($curso, 'curso_modalidade_descricao', 'Descrição da modalidade do curso');
    \local_suap\save_course_custom_field($curso, 'curso_nivel_ensino_id', 'ID do nível de ensino do curso');
    \local_suap\save_course_custom_field($curso, 'curso_nivel_ensino_descricao', 'Descrição do nível de ensino do curso');
    \local_suap\save_course_custom_field($curso, 'curso_conteudo', 'Conteúdo do curso');
    \local_suap\save_course_custom_field($curso, 'curso_restricoes', 'Restrições do curso');
    \local_suap\save_course_custom_field($curso, 'curso_sala_coordenacao', 'É sala de coordenação do curso');

    $componente = save_course_custom_category('Disciplina/Componente curricular');
    \local_suap\save_course_custom_field($componente, 'disciplina_id', 'ID da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_tipo', 'Tipo da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_sigla', 'Sigla da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_descricao', 'Descrição da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_descricao_historico', 'Descrição da disciplina que constará no histórico');
    \local_suap\save_course_custom_field($componente, 'disciplina_periodo', 'Período da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_optativo', 'Optativo da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_qtd_avaliacoes', 'Quantidade de avaliações da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_is_seminario_estagio_docente', 'É disciplina de seminário ou estágio docente', 'checkbox');
    \local_suap\save_course_custom_field($componente, 'disciplina_ch_presencial', 'Carga horária presencial da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_ch_pratica', 'Carga horária prática da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_ch_extensao', 'Carga horária de extensão da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_ch_pcc', 'Carga horária de PCC da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_ch_visita_tecnica', 'Carga horária de visita técnica da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_ch_semanal_1s', 'Carga horária semanal do 1º semestre da disciplina');
    \local_suap\save_course_custom_field($componente, 'disciplina_ch_semanal_2s', 'Carga horária semanal do 2º semestre da disciplina');

    $turma = save_course_custom_category('Turma');
    \local_suap\save_course_custom_field($turma, 'turma_id', 'ID da turma');
    \local_suap\save_course_custom_field($turma, 'turma_codigo', 'Código da turma');
    \local_suap\save_course_custom_field($turma, 'turma_ano_periodo', 'Ano/Semestre da turma');
    \local_suap\save_course_custom_field($turma, 'turma_data_inicio', 'Data de início da turma');
    \local_suap\save_course_custom_field($turma, 'turma_data_fim', 'Data de fim da turma');
    \local_suap\save_course_custom_field($turma, 'turma_gerar_matricula', 'Gerar matrícula na turma', 'checkbox');
    \local_suap\save_course_custom_field($turma, 'turma_nota_minima', 'Nota mínima da turma');
    \local_suap\save_course_custom_field($turma, 'turma_completude_minima', 'Completude mínima da turma');
    \local_suap\save_course_custom_field($turma, 'turma_modelo_padrao', 'Modelo padrão da turma');

    $diario = save_course_custom_category('Diário');
    \local_suap\save_course_custom_field($diario, 'diario_id', 'ID do diário');
    \local_suap\save_course_custom_field($diario, 'diario_tipo', 'Tipo de diário');
    \local_suap\save_course_custom_field($diario, 'diario_situacao', 'Situação do diário');
    \local_suap\save_course_custom_field($diario, 'diario_descricao', 'Descrição do diário');
    \local_suap\save_course_custom_field($diario, 'diario_descricao_historico', 'Descrição do diário que constará no histórico');

    $aberto = save_course_custom_category('Aberto');
    $linguagens = json_encode([
        "required" => "0",
        "uniquevalues" => "0",
        "options" => \local_suap\get_languages(),
        "defaultvalue" => "pt_br",
        "locked" => "0",
        "visibility" => "2"
    ]);
    \local_suap\save_course_custom_field($aberto, 'carga_horaria', 'Carga horária');
    \local_suap\save_course_custom_field($aberto, 'tem_certificado', 'Tem certificado', 'checkbox');
    \local_suap\save_course_custom_field($aberto, 'linguagem_conteudo', 'Linguagem do conteúdo', 'select', $linguagens);

    $integrador_ava = save_course_custom_category('Integrador AVA');
    \local_suap\save_course_custom_field($integrador_ava, 'grupos_sincronizados', 'Grupos sincronizados pelo Integrador AVA');

    $painel_ava = save_course_custom_category('Painel AVA');
    \local_suap\save_course_custom_field($painel_ava, 'curso_autoinscricao', 'Curso aceita autoinscrição', 'checkbox');
    \local_suap\save_course_custom_field($painel_ava, 'sala_tipo', 'Tipo de sala');
    // $sql = "       SELECT 'diarios'        AS id, 'Diários'        AS data"
    //      . " UNION SELECT 'autoinscricoes' AS id, 'Autoinscrições' AS data"
    //      . " UNION SELECT 'coordenacoes'   AS id, 'Coordenações'   AS data"
    //      . " UNION SELECT 'praticas'       AS id, 'Práticas'       AS data"
    //      . " UNION SELECT 'modelos'        AS id, 'Modelos'        AS data";    
    // $configdata = json_encode([
    //     "required" => "0",
    //     "uniquevalues" => "0",
    //     "dynamicsql" => $sql,
    //     "autocomplete" => "0",
    //     "defaultvalue" => "",
    //     "multiselect" => "0",
    //     "locked" => "1",
    //     "visibility" => "0"
    // ]);    
    // \local_suap\save_course_custom_field($painel_ava, 'sala_tipo', 'Tipo de sala', 'dynamic', $configdata);
}


function suap_bulk_user_custom_field()
{
    global $DB;

    $suap = \local_suap\get_or_create('user_info_category', ['name' => 'SUAP'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;
    \local_suap\save_user_custom_field($suap, 'tipo_usuario', 'Tipo de usuário');
    \local_suap\save_user_custom_field($suap, 'eh_servidor', 'É servidor', 'checkbox');
    \local_suap\save_user_custom_field($suap, 'eh_aluno', 'É aluno', 'checkbox');
    \local_suap\save_user_custom_field($suap, 'eh_prestador', 'É prestador', 'checkbox');
    \local_suap\save_user_custom_field($suap, 'eh_usuarioexterno', 'É usuário externo', 'checkbox');
    \local_suap\save_user_custom_field($suap, 'eh_docente', 'É docente', 'checkbox');
    \local_suap\save_user_custom_field($suap, 'eh_tecnico_administrativo', 'É técnico administrativo', 'checkbox');
    \local_suap\save_user_custom_field($suap, 'last_login', 'JSON do último login', 'textarea', 0);

    $pessoais = \local_suap\get_or_create('user_info_category', ['name' => 'Dados pessoais'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;
    \local_suap\save_user_custom_field($pessoais, 'nome_apresentacao', 'Nome de apresentação');
    \local_suap\save_user_custom_field($pessoais, 'nome_completo', 'Nome completo');
    \local_suap\save_user_custom_field($pessoais, 'nome_social', 'Nome social');
    \local_suap\save_user_custom_field($pessoais, 'data_de_nascimento', 'Data de nascimento');
    \local_suap\save_user_custom_field($pessoais, 'sexo', 'Sexo');
    \local_suap\save_user_custom_field($pessoais, 'cpf', 'CPF');
    \local_suap\save_user_custom_field($pessoais, 'passaporte', 'Passaporte');
    \local_suap\save_user_custom_field($pessoais, 'id_doc_certificado', 'ID do documento para certificado');
    \local_suap\save_user_custom_field($pessoais, 'tipo_doc_certificado', 'Tipo de documento para certificado');
    \local_suap\save_user_custom_field($pessoais, 'eh_estrangeiro', 'É estrangeiro', 'checkbox');

    $contatos = \local_suap\get_or_create('user_info_category', ['name' => 'Dados de contato'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;
    \local_suap\save_user_custom_field($contatos, 'email_google_classroom', 'E-mail @escolar (Google Classroom)');
    \local_suap\save_user_custom_field($contatos, 'email_academico', 'E-mail @academico (Microsoft)');
    \local_suap\save_user_custom_field($contatos, 'email_secundario', 'Secundário (servidores)');

    $matricula = \local_suap\get_or_create('user_info_category', ['name' => 'Matrícula'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;
    \local_suap\save_user_custom_field($matricula, 'programa_nome', 'Nome do programa');
    \local_suap\save_user_custom_field($matricula, 'ingresso_periodo', 'Período de ingresso');
    \local_suap\save_user_custom_field($matricula, 'outras_matriculas', 'Outras matrículas');    

    $polo = \local_suap\get_or_create('user_info_category', ['name' => 'Polo'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;
    \local_suap\save_user_custom_field($polo, 'polo_id', 'ID do polo');
    \local_suap\save_user_custom_field($polo, 'polo_nome', 'Nome do polo');
    \local_suap\save_user_custom_field($polo, 'polo_sigla', 'Sigla do polo');

    $campus = \local_suap\get_or_create('user_info_category', ['name' => 'Campus'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;
    \local_suap\save_user_custom_field($campus, 'campus_id', 'ID do campus');
    \local_suap\save_user_custom_field($campus, 'campus_descricao', 'Descrição do campus');
    \local_suap\save_user_custom_field($campus, 'campus_sigla', 'Sigla do campus');

    $curso = \local_suap\get_or_create('user_info_category', ['name' => 'Curso'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;
    \local_suap\save_user_custom_field($curso, 'curso_id', 'ID do curso');
    \local_suap\save_user_custom_field($curso, 'curso_codigo', 'Código do curso');
    \local_suap\save_user_custom_field($curso, 'curso_descricao', 'Descrição do curso');
    \local_suap\save_user_custom_field($curso, 'curso_modalidade_id', 'Id da modalidade');
    \local_suap\save_user_custom_field($curso, 'curso_modalidade_descricao', 'Descrição da modalidade');
    \local_suap\save_user_custom_field($curso, 'curso_nivel_ensino_id', 'Id do nível de ensino');
    \local_suap\save_user_custom_field($curso, 'curso_nivel_ensino_descricao', 'Descrição do nível de ensino');

    $turma = \local_suap\get_or_create('user_info_category', ['name' => 'Turma'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;
    \local_suap\save_user_custom_field($turma, 'turma_id', 'ID da última turma');
    \local_suap\save_user_custom_field($turma, 'turma_codigo', 'Código última da turma');

    $DB->execute("INSERT INTO {user_preferences} (userid, name, value) SELECT id, 'visual_preference', 1 FROM {user} ON CONFLICT DO NOTHING");
}


function local_suap_migrate($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    $suap_enrolment_to_sync = new xmldb_table("suap_enrolment_to_sync");
    $suap_enrolment_to_sync->add_field("id",             XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
    $suap_enrolment_to_sync->add_field("json",           XMLDB_TYPE_TEXT,    'medium',   XMLDB_UNSIGNED, null,          null,            null, null, null);
    $suap_enrolment_to_sync->add_field("timecreated",    XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_enrolment_to_sync->add_field("processed",      XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

    $suap_enrolment_to_sync->add_key("primary",      XMLDB_KEY_PRIMARY,  ["id"],         null,       null);
    if (!$dbman->table_exists($suap_enrolment_to_sync)) {
        $dbman->create_table($suap_enrolment_to_sync);
    }

    $suap_learning_path = new xmldb_table("suap_learning_path");
    $suap_learning_path->add_field("id",             XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
    $suap_learning_path->add_field("name",           XMLDB_TYPE_CHAR,    '255',      null,           XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("description",    XMLDB_TYPE_TEXT,    'medium',   XMLDB_UNSIGNED, null,          null,            null, null, null);
    $suap_learning_path->add_field("descriptionformat", XMLDB_TYPE_INTEGER, '2',     XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("slug",           XMLDB_TYPE_CHAR,    '255',      null,           XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("timecreated",    XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("timemodified",   XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("visible",        XMLDB_TYPE_INTEGER, '1',        XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("sortorder",      XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

    $suap_learning_path->add_key("primary",      XMLDB_KEY_PRIMARY,  ["id"],         null,       null);
    if (!$dbman->table_exists($suap_learning_path)) {
        $dbman->create_table($suap_learning_path);
    }

    $suap_learning_path_course = new xmldb_table("suap_learning_path_course");
    $suap_learning_path_course->add_field("id",             XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
    $suap_learning_path_course->add_field("learningpathid", XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("courseid",       XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("timecreated",    XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("timemodified",   XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("visible",        XMLDB_TYPE_INTEGER, '1',        XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("sortorder",      XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

    $suap_learning_path_course->add_key("primary",      XMLDB_KEY_PRIMARY,  ["id"],         null,       null);
    $suap_learning_path_course->add_key("learningpathid", XMLDB_KEY_FOREIGN, ["learningpathid"], "suap_learning_path", ["id"]);
    $suap_learning_path_course->add_key("courseid",       XMLDB_KEY_FOREIGN, ["courseid"],       "course",            ["id"]);
    if (!$dbman->table_exists($suap_learning_path_course)) {
        $dbman->create_table($suap_learning_path_course);
    }

    return true;
}
