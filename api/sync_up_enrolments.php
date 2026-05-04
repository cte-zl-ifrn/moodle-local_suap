<?php
namespace local_suap;

// Desabilita verificação CSRF para esta API
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once(\dirname(\dirname(\dirname(__DIR__))) . '/config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/local/suap/locallib.php');
require_once($CFG->dirroot . '/local/suap/classes/Jsv4/Validator.php');
require_once($CFG->dirroot . '/local/suap/api/servicelib.php');


function getattr($obj, $prop, $default = '') {
    $result = property_exists($obj, $prop) ? $obj->$prop : $default;
    return $result !== null ? $result : $default;
};

class sync_up_enrolments_service extends service {

    private $json;
    private $result = [];
    private $cursoCategory;
    private $turmaCategory;
    private $context;
    private $course;
    private $diario;
    private $coordenacao;
    private $isRoom;
    private $aluno_enrol;
    private $roles_mapping;
    private $professor_enrol;
    private $formador_enrol;
    private $tutor_enrol;
    private $docente_enrol;
    private $mediador_enrol;
    private $default_user_preferences;
    private $auths_mapping;
    private $alunos_sincronizados = [];
    private $ids_suspensos = [];


    function get_course_enrol_instance_by_enrol_type($enrol_type) {
        global $DB;
        foreach (\enrol_get_instances($this->course->id, FALSE) as $instance) {
            if ($instance->enrol === $enrol_type) {
                return $instance;
            }
        }
        $instance_id = $enrol_plugin->add_instance($this->course);
        return $DB->get_record('enrol', ['id' => $instanceid]);
    }


    function is_user_enrolled_in_role_via_enrolname($userid, $enrol_instance, $roleid): bool {
        global $DB;

        $sql = "
            SELECT      COUNT(*)
            FROM        {user_enrolments}                   ue
                            JOIN {enrol}                    e   ON (ue.enrolid  = e.id)
                                JOIN {context}              ctx ON (e.courseid  = ctx.instanceid AND ctx.contextlevel   = 50)
                                    JOIN {role_assignments} ra  ON (ctx.id      = ra.contextid   AND ue.userid          = ra.userid)
            WHERE       ue.userid         = :userid
              AND       e.id              = :enrolid
              AND       ra.roleid         = :roleid
              AND       ue.status         = 0
              AND       e.status          = 0

        ";

        return (int) $DB->get_field_sql($sql, ['userid' => $userid, 'enrolid' => $enrol_instance->id, 'roleid' => $roleid]) > 0;
    }


    function get_sala_tipo() {
        return match (true) {
            $this->isRoom => 'coordenacoes',
            getattr($this->json->curso, 'autoinscricao', false) => 'autoinscricoes',
            getattr($this->json->curso, 'praticas', false) => 'praticas',
            getattr($this->json->curso, 'modelos', false) => 'modelos',
            default => 'diarios',
        };
    }


    function get_componente_tipo() {
        /* 1:Regular, 2:Seminário, 3:Prática Profissional, 4:Trabalho de Conclusão de Curso, 5:Atividade de Extensão, 6:Prática como Componente Curricular, 7:Visita Técnica / Aula da Campo, 8:Componentes Extracurriculares */        
        $tipo = getattr($this->json->componente, 'tipo', '1');
        return match (true) {
            $tipo == '1' => 'Regular',
            $tipo == '2' => 'Seminário',
            $tipo == '3' => 'Prática Profissional',
            $tipo == '4' => 'Trabalho de Conclusão de Curso',
            $tipo == '5' => 'Atividade de Extensão',
            $tipo == '6' => 'Prática como Componente Curricular',
            $tipo == '7' => 'Visita Técnica / Aula da Campo',
            $tipo == '8' => 'Componentes Extracurriculares',
            default => 'Regular',
        };
    }


    function get_course_and_customfields_by_idnumber(string $courseidnumber) {
        global $DB, $CFG;
        require_once("$CFG->dirroot/course/lib.php");

        $course = $DB->get_record('course', ['idnumber' => $courseidnumber], '*');
        if (!$course) {
            return null;
        }

        $mappedfields = (array)$course;
        foreach (\core_customfield\handler::get_handler('core_course', 'course')->get_instance_data($course->id) as $d) {
            $mappedfields["customfield_{$d->get_field()->get('shortname')}"] = $d->get_value();
        }

        return (object)$mappedfields;
    }


    function getIdDosAlunosFaltandoAgrupar($group, $alunos) {
        global $DB;
        $alunoIds = array_map(function ($x) {
            return $x->user->id;
        }, $alunos);
        list($insql, $inparams) = $DB->get_in_or_equal($alunoIds);
        $sql = "SELECT userid FROM {groups_members} WHERE groupid = ? and userid $insql";
        $ja_existem = $DB->get_records_sql($sql, array_merge([$group->id], $inparams));
        return array_map(function ($x) {
            return $x->userid;
        }, $ja_existem);
    }


    function parse_auths_mapping() {
        $this->auths_mapping = [];
        foreach (explode("\n", config('auths_mapping')) as $mapping_line) {
            $parts = array_map('trim', explode(':', $mapping_line));
            if (count($parts) === 2) {
                [$papel_suap, $auth] = $parts;
                $this->auths_mapping[$papel_suap] = $auth;
            }
        }
    }


    function validate_json($jsonstring) {
        global $CFG;

        $this->json = json_decode($jsonstring);

        if (!$this->json) {
            throw new \Exception("Erro ao decodificar o JSON, favor corrigir.");
        }

        // TODO: Verificar a efetividade da validação do JSON
        // $schema = json_decode(file_get_contents($CFG->dirroot . '/local/suap/schemas/sync_up_enrolments.schema.json'));
        // $validation = \Jsv4\Validator::validate($this->json, $schema);
        // if (!\Jsv4\Validator::isValid($this->json, $schema)) {
        //     $errors = "";

        //     foreach ($validation->errors as $error) {
        //         $errors .= "{$error->message}";
        //     }
        //     throw new \Exception("Erro ao validar o JSON, favor corrigir." . $errors);
        // }
    }


    function do_call() {
        global $DB;

        $jsonstring = file_get_contents('php://input');

        $this->validate_json($jsonstring);
        $result = $this->process($jsonstring, false);
        $DB->insert_record(
            "suap_enrolment_to_sync",
            (object)['json' => $jsonstring, 'timecreated' => time(), 'processed' => 0]
        );
        return $result;
    }


    function process($jsonstring, $inBackground) {
        global $CFG;

        $result = ["url" => null, "url_sala_coordenacao" => null, "ids_suspensos" => []];

        $this->sync_categories();
        if ($inBackground || $this->json->sincrono) {
            $this->sync_users();
            $this->sync_cohorts();
        }

        $prefix = "{$CFG->wwwroot}/course/view.php";
        foreach ([false, true] as $isRoom) {
            $this->isRoom = $isRoom;
            $this->sync_course($isRoom ? $this->cursoCategory->id : $this->turmaCategory->id);
            $result[$isRoom ? 'url_sala_coordenacao' : 'url'] = "$prefix?id={$this->course->id}";
            if ($inBackground || $this->json->sincrono) {
                $this->sync_enrols_cohorts();
                $this->sync_enrols_manuals();
                $this->sync_enrolments();
                $this->suspend_students_not_in_list_all_enrols();
                $this->sync_groups();

            }
        }
        $result["ids_suspensos"] = array_unique($this->ids_suspensos);

        return $result;
    }


    function sync_categories() {
        function sync_category($idnumber, $name, $parent) {
            global $DB;

            $category = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
            if (empty($category)) {
                $category = \core_course_category::create(['name' => $name, 'idnumber' => $idnumber, 'parent' => $parent]);
            }

            return $category;
        }        
        $diarioCategory = sync_category(
            config('top_category_idnumber') ?: 'diarios',
            config('top_category_name') ?: 'Diários',
            config('top_category_parent') ?: 0
        );

        $campusCategory = sync_category(
            $this->json->campus->sigla,
            $this->json->campus->descricao,
            $diarioCategory->id
        );

        $this->cursoCategory = sync_category(
            $this->json->curso->codigo,
            $this->json->curso->nome,
            $campusCategory->id
        );

        $ano_periodo = substr($this->json->turma->codigo, 0, 4) . "." . substr($this->json->turma->codigo, 4, 1);
        $semestreCategory = sync_category(
            "{$this->json->curso->codigo}.{$ano_periodo}",
            $ano_periodo,
            $this->cursoCategory->id
        );

        $this->turmaCategory = sync_category(
            $this->json->turma->codigo,
            $this->json->turma->codigo,
            $semestreCategory->id
        );
    }


    function sync_users() {
        $this->default_user_preferences = array_map(
            fn($line) => explode('=', trim($line), 2),
            array_filter(
                preg_split('/[\r\n]+/', config('default_user_preferences'), -1, PREG_SPLIT_NO_EMPTY),
                fn($line) => count(explode('=', trim($line), 2)) === 2
            )
        );

        $this->parse_auths_mapping();

        $professores = getattr($this->json, 'professores', []);
        $equipe = getattr($this->json, 'equipe', []);
        $coortes_colaboradores = array_reduce(
            getattr($this->json, 'coortes', []),
            fn($carry, $coorte) => array_merge($carry, $coorte->colaboradores ?? []),
            []
        );

        $time = array_values(array_reduce(
            array_merge($coortes_colaboradores, $professores, $equipe),
            fn($carry, $usuario) => !isset($carry[$usuario->login]) 
                ? [...$carry, $usuario->login => $usuario]
                : $carry,
            []
        ));

        $alunos = getattr($this->json, 'alunos', []);
        foreach ($alunos as $aluno) {
            $aluno->tipo_usuario = $aluno->tipo_usuario ?: "Aluno";
        }

        foreach (array_merge($time, $alunos) as $usuario) {
            $this->sync_user($usuario);
            $this->sync_profile_custom_fields($usuario);
        }
    }


    function sync_user($usuario) {
        global $DB;

        $usuario->username = strtolower(getattr($usuario, 'username', getattr($usuario, 'matricula', getattr($usuario, 'login'))));
        $nome_parts = explode(' ', $usuario->nome);
        $tipo = getattr($usuario, 'tipo', 'Aluno');

        $insert_only = [
            'username' => $usuario->username,
            'password' => '!aA1' . uniqid(),
            'timezone' => '99',
            'confirmed' => 1,
            'mnethostid' => 1,
            'suspended' => 0,
        ];
        $insert_or_update = [
            'firstname' => implode(' ', array_slice($nome_parts, 0, -1)),
            'lastname' => end($nome_parts),
            'auth' => isset($this->auths_mapping[$tipo]) ? $this->auths_mapping[$tipo] : config('default_auth'),
            'email' => $usuario->email ?: $usuario->email_secundario,
        ];

        $usuario->user = $DB->get_record("user", ["username" => $usuario->username]);
        if ($usuario->user) {
            \user_update_user(array_merge(['id' => $usuario->user->id], $insert_or_update));
        } else {
            \user_create_user(array_merge($insert_or_update, $insert_only));
            $usuario->user = $DB->get_record("user", ["username" => $usuario->username]);
            foreach ($this->default_user_preferences as $parts) {
                \set_user_preference($parts[0], $parts[1], $usuario->user);
            }
        }
    }


    function sync_cohorts() {
        global $DB;

        if (isset(($this->json->coortes))) {
            foreach ($this->json->coortes as $coorte) {
                $cohort = $DB->get_record('cohort', ['idnumber' => $coorte->idnumber]);
                if (!$cohort) {
                    $cohort = (object)[
                        "name" => $coorte->nome,
                        "idnumber" => $coorte->idnumber,
                        "description" => $coorte->descricao,
                        "visible" => $coorte->ativo,
                        "contextid" => 1
                    ];
                    $cohort->id = \cohort_add_cohort($cohort);
                } else {
                    $cohort->name = $coorte->nome;
                    $cohort->idnumber = $coorte->idnumber;
                    $cohort->description = $coorte->descricao;
                    $cohort->visible = $coorte->ativo;
                    \cohort_update_cohort($cohort);
                }

                foreach ($coorte->colaboradores as $usuario) {
                    \cohort_add_member($cohort->id, $usuario->user->id);
                }
                $coorte->cohort = $cohort;
            }
        }
    }


    function sync_profile_custom_fields($usuario) {
        $campus = getattr($this->json, 'campus', (object)[]);
        $curso = getattr($this->json, 'curso', (object)[]);
        $modalidade = getattr($curso, 'modalidade', (object)[]);
        $nivel_ensino = getattr($modalidade, 'nivel_ensino', (object)[]);
        $turma = getattr($this->json, 'turma', (object)[]);
        $polo = getattr($usuario, 'polo', (object)[]);
        $tipo_doc_certificado = getattr($usuario, 'cpf') == '' ?  'passaporte' : 'cpf';

        $custom_fields = [
            // SUAP
            'tipo_usuario' => getattr($usuario, 'tipo_usuario'),
            'eh_servidor' => getattr($usuario, 'eh_servidor', strlen($usuario->username) < 11 ? true : false),
            'eh_aluno' => getattr($usuario, 'eh_aluno', strlen($usuario->username) > 11 ? true : false),
            'eh_prestador' => getattr($usuario, 'eh_prestador', strlen($usuario->username) == 11 ? true : false),
            'eh_usuarioexterno' => getattr($usuario, 'eh_usuarioexterno', strlen($usuario->username) == 11 ? true : false),
            'eh_docente' => getattr($usuario, 'eh_docente'),
            'eh_tecnico_administrativo' => getattr($usuario, 'eh_tecnico_administrativo'),

            // Dados pessoais
            'nome_apresentacao' => getattr($usuario, 'nome_usual'),
            'nome_completo' => getattr($usuario, 'nome_registro'),
            'nome_social' => getattr($usuario, 'nome_social'),
            'data_de_nascimento' => getattr($usuario, 'data_de_nascimento'),
            'sexo' => getattr($usuario, 'sexo'),
            'cpf' => getattr($usuario, 'cpf'),
            'passaporte' => getattr($usuario, 'passaporte'),
            'tipo_doc_certificado' => $tipo_doc_certificado,
            'id_doc_certificado' => getattr($usuario, $tipo_doc_certificado),
            'eh_estrangeiro' => getattr($usuario, 'eh_estrangeiro'),

            // Dados de contato
            'email_google_classroom' => getattr($usuario, 'email_google_classroom'),
            'email_academico' => getattr($usuario, 'email_academico'),
            'email_secundario' => getattr($usuario, 'email_secundario'),

            // Matrícula
            'programa_nome' => getattr($usuario, 'programa', "Institucional"),
            'ingresso_periodo' => getattr($usuario, 'ingresso_periodo'),
            'outras_matriculas' => json_encode(getattr($usuario, 'outras_matriculas', [])),

            // Polo
            'polo_id' => getattr($polo, 'id'),
            'polo_sigla' => getattr($polo, 'sigla'),
            'polo_nome' => getattr($polo, 'descricao'),

            // Campus
            'campus_id' => getattr($campus, 'id'),
            'campus_descricao' => getattr($campus, 'descricao'),
            'campus_sigla' => getattr($campus, 'sigla'),

            // Curso
            'curso_id' => getattr($curso, 'id'),
            'curso_codigo' => getattr($curso, 'codigo'),
            'curso_descricao' => getattr($curso, 'nome'),
            'curso_modalidade_id' => getattr($modalidade, 'id'),
            'curso_modalidade_descricao' => getattr($modalidade, 'descricao'),
            'curso_nivel_ensino_id' => getattr($nivel_ensino, 'id'),
            'curso_nivel_ensino_descricao' => getattr($nivel_ensino, 'descricao'),

            // Turma
            'turma_id' => getattr($turma, 'id'),
            'turma_codigo' => getattr($turma, 'codigo'),
        ];

        // Filtra apenas campos com conteúdo
        $custom_fields = array_filter($custom_fields, function($v) {return $v !== '';});
        
        \profile_save_custom_fields($usuario->user->id, $custom_fields);
    }


    function sync_course($categoryid) {
        global $DB;

        $course_code = $this->isRoom ? "{$this->json->campus->sigla}.{$this->json->curso->codigo}" : "{$this->json->turma->codigo}.{$this->json->componente->sigla}";
        $course_code_long = $this->isRoom ? $course_code : "{$course_code}#{$this->json->diario->id}";
        $modalidade = getattr($this->json->curso, 'modalidade', (object)[]);
        $nivelensino = getattr($modalidade, 'nivel_ensino', (object)[]);

        $data = [
            "category" => $categoryid,
            "fullname" => $this->isRoom ? "Sala de coordenação do curso {$this->json->curso->nome}" : $this->json->componente->descricao,
            "shortname" => $course_code_long,
            "idnumber" => $course_code_long,

            /* Fixo */
            "customfield_curso_sala_coordenacao" => $this->isRoom ? 'Sim' : 'Não',
            "visible" => 0,
            "enablecompletion" => 1,
            // "startdate"=>time(),
            "showreports" => 1,
            "completionnotify" => 1,

            /* Obrigatório - Painel AVA */
            "customfield_sala_tipo" => $this->get_sala_tipo(),
            "customfield_curso_autoinscricao" => $this->isRoom ? '' : (getattr($this->json->curso, 'autoinscricao') == 'true' ? '1' : '0'),

            /* Obrigatórios - Campus */
            "customfield_campus_id" => $this->json->campus->id,
            "customfield_campus_sigla" => $this->json->campus->sigla,
            "customfield_campus_descricao" => $this->json->campus->descricao,

            /* Obrigatórios - Curso */
            "customfield_curso_id" => $this->json->curso->id,
            "customfield_curso_codigo" => $this->json->curso->codigo,
            "customfield_curso_nome" => $this->json->curso->nome,

            /* Opcionais - Curso */
            "customfield_curso_descricao" => getattr($this->json->curso, 'descricao'),
            "customfield_curso_descricao_historico" => getattr($this->json->curso, 'descricao_historico'),
            "customfield_curso_titulo_certificado_masculino" => getattr($this->json->curso, 'titulo_certificado_masculino'),
            "customfield_curso_titulo_certificado_feminino" => getattr($this->json->curso, 'titulo_certificado_feminino'),
            "customfield_curso_ch_total" => getattr($this->json->curso, 'ch_total'),
            "customfield_curso_ch_aula" => getattr($this->json->curso, 'ch_aula'),
            "customfield_curso_autoinstrucional" => getattr($this->json->curso, 'autoinstrucional') == 'true' ? '1' : '0',
            "customfield_curso_programa" => getattr($this->json->curso, 'programa'),
            "customfield_curso_modalidade_id" => getattr($modalidade, 'id'),
            "customfield_curso_modalidade_descricao" => getattr($modalidade, 'descricao'),
            "customfield_curso_nivel_ensino_id" => getattr($nivelensino, 'id'),
            "customfield_curso_nivel_ensino_descricao" => getattr($nivelensino, 'descricao'),
            "customfield_curso_conteudo" => json_encode(getattr($this->json->curso, 'conteudo', [])),
            "customfield_curso_restricoes" => json_encode(getattr($this->json->curso, 'restricoes', [])),

            /* Obrigatórios - Componente Curricular */
            "customfield_disciplina_id" => $this->isRoom ? '' : $this->json->componente->id ,
            "customfield_disciplina_sigla" => $this->isRoom ? '' : $this->json->componente->sigla ,
            "customfield_disciplina_descricao" => $this->isRoom ? '' : $this->json->componente->descricao ,

            /* Opcionais - Componente Curricular */
            "customfield_disciplina_descricao_historico" => $this->isRoom ? '' : getattr($this->json->componente, 'descricao_historico'),
            "customfield_disciplina_periodo" => $this->isRoom ? '' : getattr($this->json->componente, 'periodo'),
            "customfield_disciplina_tipo" => $this->isRoom ? '' : $this->get_componente_tipo(),
            "customfield_disciplina_optativo" => $this->isRoom ? '' : getattr($this->json->componente, 'optativo'),
            "customfield_disciplina_qtd_avaliacoes" => $this->isRoom ? '' : getattr($this->json->componente, 'qtd_avaliacoes'),
            "customfield_disciplina_is_seminario_estagio_docente" => $this->isRoom ? '' : getattr($this->json->componente, 'is_seminario_estagio_docente'),
            "customfield_disciplina_ch_presencial" => $this->isRoom ? '' : getattr($this->json->componente, 'ch_presencial'),
            "customfield_disciplina_ch_pratica" => $this->isRoom ? '' : getattr($this->json->componente, 'ch_pratica'),
            "customfield_disciplina_ch_extensao" => $this->isRoom ? '' : getattr($this->json->componente, 'ch_extensao'),
            "customfield_disciplina_ch_pcc" => $this->isRoom ? '' : getattr($this->json->componente, 'ch_pcc'),
            "customfield_disciplina_ch_visita_tecnica" => $this->isRoom ? '' : getattr($this->json->componente, 'ch_visita_tecnica'),
            "customfield_disciplina_ch_semanal_1s" => $this->isRoom ? '' : getattr($this->json->componente, 'ch_semanal_1s'),
            "customfield_disciplina_ch_semanal_2s" => $this->isRoom ? '' : getattr($this->json->componente, 'ch_semanal_2s'),

            /* Obrigatórios - Turma */
            "customfield_turma_id" => $this->isRoom ? '' : $this->json->turma->id ,
            "customfield_turma_codigo" => $this->isRoom ? '' : $this->json->turma->codigo ,

            /* Opcionais - Turma */
            "customfield_turma_ano_periodo" => $this->isRoom ? '' : substr(getattr($this->json->turma, 'codigo'), 0, 4) . "." . substr(getattr($this->json->turma, 'codigo'), 4, 1),
            "customfield_turma_data_inicio" => $this->isRoom ? '' : getattr($this->json->turma, 'data_inicio'),
            "customfield_turma_data_fim" => $this->isRoom ? '' : getattr($this->json->turma, 'data_fim'),
            "customfield_turma_gerar_matricula" => $this->isRoom ? '' : getattr($this->json->turma, 'gerar_matricula'),
            "customfield_turma_nota_minima" => $this->isRoom ? '' : getattr($this->json->turma, 'nota_minima'),
            "customfield_turma_completude_minima" => $this->isRoom ? '' : getattr($this->json->turma, 'completude_minima'),
            "customfield_turma_modelo_padrao" => $this->isRoom ? '' : getattr($this->json->turma, 'modelo_padrao'),

            /* Obrigatórios - Diário */
            "customfield_diario_id" => $this->isRoom ? '' : $this->json->diario->id,

            /* Opcionais - Diário */
            "customfield_diario_tipo" => $this->isRoom ? '' : getattr($this->json->diario, 'tipo', 'regular'),
            "customfield_diario_situacao" => $this->isRoom ? '' : getattr($this->json->diario, 'situacao'),
            "customfield_diario_descricao" => $this->isRoom ? '' : getattr($this->json->diario, 'descricao'),
            "customfield_diario_descricao_historico" => $this->isRoom ? '' : getattr($this->json->diario, 'descricao_historico'),
        ];

        $this->course = $this->get_course_and_customfields_by_idnumber($course_code_long);
        if (!$this->course) {
            $this->course = create_course((object)$data);
        } elseif (!$this->isRoom) {
            $data['id'] = $this->course->id;
            $this->course = (object)$data;
            update_course($this->course);
        }

        $this->context = \context_course::instance($this->course->id);
    }


    function sync_enrols_cohorts() {
        global $DB;

        $enrol_plugin = enrol_get_plugin("cohort");
        foreach (getattr($this->json, 'coortes', []) as $coorte) {
            if (!$cohort = $DB->get_record('cohort', ['idnumber' => $coorte->idnumber])) {
                throw new \Exception("Não localizei a coorte '{$coorte->nome}' ( / {$coorte->idnumber}) ", 555);
            }
            if (!$role = $DB->get_record('role', ['shortname' => $coorte->role])) {
                throw new \Exception("Não localizei a role({$coorte->role})", 555);
            }
            if (!$instance = $DB->get_record('enrol', ["enrol" => "cohort", "customint1" => $cohort->id, "courseid" => $this->course->id])) {
                $enrol_plugin->add_instance($this->course, ["customint1" => $cohort->id, "roleid" => $role->id, "customint2" => 0]);
            }
        }
    }


    function sync_enrols_manuals() {
        global $DB;

        $alunos = getattr($this->json, 'alunos', []);
        $professores = getattr($this->json, 'professores', []);
        $equipe = getattr($this->json, 'equipe', []);
        $sala_tipo = $this->get_sala_tipo();

        $prefixes = [];
        foreach (array_merge($alunos, $professores, $equipe) as $usuario) {
            $papel_suap = getattr($usuario, "tipo", "Aluno");
            $prefix = "$sala_tipo:$papel_suap";
            $prefixes[$prefix] ??= ["sala_tipo" => $sala_tipo, "papel_suap" => $papel_suap];
        }

        $mappings = json_decode(config('roles_mapping'));

        foreach ($prefixes as $prefix => $keys) {
            $sala_tipo = $keys["sala_tipo"];
            $papel_suap = $keys["papel_suap"];
            if (!isset($mappings->$sala_tipo) || !isset($mappings->$sala_tipo->$papel_suap)) {
                throw new \Exception("Nas salas do tipo '{$sala_tipo}' não existe mapeamento para o papel '{$papel_suap}'.", 551);
            }
            $m = $mappings->$sala_tipo->$papel_suap;

            if (!isset($m->role_instance)) {
                if (!$role_instance = $DB->get_record('role', ['shortname' => $m->role])) {
                    throw new \Exception("Não localizei a role({$m->role}) nas salas do tipo '{$sala_tipo}', papel '{$papel_suap}'.", 552);
                }
                $m->role_instance = $role_instance;
            }

            if (!isset($m->enrol_plugin)) {
                if (!$enrol_plugin = enrol_get_plugin($m->enrol)) {
                    throw new \Exception("Não localizei a enrol({$m->enrol}) nas salas do tipo '{$sala_tipo}', papel '{$papel_suap}'.", 553);
                }
                $m->enrol_plugin = $enrol_plugin;
            }
            if (!isset($m->enrol_instance)) {
                if (!$enrol_instance = $this->get_course_enrol_instance_by_enrol_type($m->enrol)) {
                    throw new \Exception("Não consegui criar/recurperar a instância do enrol ({$m->enrol}) no curso '{$this->json->course->id}'.", 554);
                }
                $m->enrol_instance = $enrol_instance;
            }

            $this->roles_mapping[$prefix] = $m;
        }
    }


    function sync_enrolments(): array {
        $professores = getattr($this->json, 'professores', []);
        $equipe = getattr($this->json, 'equipe', []);
        $alunos = getattr($this->json, 'alunos', []);
        $primeiro_aluno_role = null;
        foreach (array_merge($professores, $equipe, $alunos) as $usuario) {
            $prefix = $this->get_sala_tipo() . ":" .  getattr($usuario, 'tipo', 'Aluno');
            $m = $this->roles_mapping[$prefix];
            if (!$m) {
                continue;
            }
            if (getattr($usuario, 'tipo', 'Aluno') === 'Aluno' && !$primeiro_aluno_role) {
                $primeiro_aluno_role = $m->role;
            }

            $status_str = strtolower(getattr($usuario, 'situacao_diario', getattr($usuario, 'status', 'inativo')));
            $status = $status_str === 'ativo' ? \ENROL_USER_ACTIVE : \ENROL_USER_SUSPENDED;

            if ($this->is_user_enrolled_in_role_via_enrolname($usuario->user->id, $m->enrol_instance, $m->role_instance->id)) {
                $m->enrol_plugin->update_user_enrol($m->enrol_instance, $usuario->user->id, $status);
            } else {
                $m->enrol_plugin->enrol_user($m->enrol_instance, $usuario->user->id, $m->role_instance->id, time(), 0, $status);
            }
            $this->alunos_sincronizados[] = $usuario->user->id;
        }
        return $this->alunos_sincronizados;
    }


    function suspend_students_not_in_list_all_enrols(): array {
        global $DB;

        [$notinsql, $params] = $DB->get_in_or_equal(
            $this->alunos_sincronizados ?: [0],
            SQL_PARAMS_NAMED,
            'uid',
            false
        );

        $sql = "
            SELECT  DISTINCT ue.userid, ue.enrolid, ue.status, e.enrol
            FROM    {user_enrolments}               ue
                        JOIN {enrol}                e   ON (ue.enrolid = e.id)
                            JOIN {role_assignments} ra  ON (ue.userid = ra.userid)
            AND     ra.contextid= :contextid
            AND     ra.roleid   = :roleid
            WHERE   e.courseid  = :courseid
            AND     e.status    = :enrolactive
            AND     ue.status   = :useractive
            AND     ue.userid   $notinsql
        ";

        $studentroleid = 5;

        $params = array_merge(
            $params, 
            [
                'contextid'   => $this->context->id,
                'roleid'      => $studentroleid,
                'courseid'    => $this->course->id,
                'enrolactive' => ENROL_INSTANCE_ENABLED,
                'useractive'  => ENROL_USER_ACTIVE,
            ]
        );

        $records = $DB->get_records_sql($sql, $params);
        $instances = [];
        foreach ($records as $record) {
            if (!isset($instances[$record->enrolid])) {
                $instances[$record->enrolid] = $DB->get_record('enrol', ['id' => $record->enrolid], '*', MUST_EXIST);
            }

            $plugin = enrol_get_plugin($record->enrol);
            if (!$plugin) {
                continue;
            }

            $plugin->update_user_enrol(
                $instances[$record->enrolid],
                $record->userid,
                \ENROL_USER_SUSPENDED
            );

            $this->ids_suspensos[] = $record->userid;
        }

        return $this->ids_suspensos;
    }


    function sync_groups() {
        global $CFG, $DB;
        if ($this->isRoom) {
            $group_entrada = config('room_group_entrada');
            $group_turma = config('room_group_turma');
            $group_polo = config('room_group_polo');
            $group_programa = config('room_group_programa');
        } else {
            $group_entrada = config('course_group_entrada');
            $group_turma = config('course_group_turma');
            $group_polo = config('course_group_polo');
            $group_programa = config('course_group_programa');
        }

        if (isset($this->json->alunos)) {
            $grupos = [];
            foreach ($this->json->alunos as $usuario) {
                if ($group_entrada) {
                    $entrada = substr($usuario->user->username, 0, 5);
                    if (!isset($grupos[$entrada])) {
                        $grupos[$entrada] = [];
                    }
                    $grupos[$entrada][] = $usuario;
                }

                if ($group_turma) {
                    $turma = $this->json->turma->codigo;
                    if (!isset($grupos[$turma])) {
                        $grupos[$turma] = [];
                    }
                    $grupos[$turma][] = $usuario;
                }

                if ($group_polo) {
                    $polo = isset($usuario->polo) && isset($usuario->polo->descricao) ? $usuario->polo->descricao : '--Sem polo--';
                    if (!isset($grupos[$polo])) {
                        $grupos[$polo] = [];
                    }
                    $grupos[$polo][] = $usuario;
                }

                if ($group_programa) {
                    $programa = isset($usuario->programa) && $usuario->programa != null ? $usuario->programa : "Institucional";
                    if (!isset($grupos[$programa])) {
                        $grupos[$programa] = [];
                    }
                    $grupos[$programa][] = $usuario;
                }
            }

            $custom_fields_metadata = \core_course\customfield\course_handler::create()->export_instance_data_object($this->course->id, true);
            $this->course->synchronized_groups = $custom_fields_metadata->grupos_sincronizados == '' ? [] : explode(',', $custom_fields_metadata->grupos_sincronizados);
            foreach ($grupos as $group_name => $alunos) {
                $group = $this->sync_group($group_name);
                $idDosAlunosFaltandoAgrupar = $this->getIdDosAlunosFaltandoAgrupar($group, $alunos);
                foreach ($alunos as $group_name => $usuario) {
                    if (!in_array($usuario->user->id, $idDosAlunosFaltandoAgrupar)) {
                        \groups_add_member($group->id, $usuario->user->id);
                    }
                }
            }
            $this->course->customfield_grupos_sincronizados = implode(',', array_keys($grupos));
            update_course($this->course);
        }
    }


    function sync_group($group_name) {
        global $DB;
        $data = ['courseid' => $this->course->id, 'name' => $group_name];
        $group = $DB->get_record('groups', $data);
        
        if (!$group && !in_array($group_name, $this->course->synchronized_groups)) {
            $groupid = \groups_create_group((object)$data);
            $group = $DB->get_record('groups', ['id' => $groupid]);
        }
        return $group;
    }
}
