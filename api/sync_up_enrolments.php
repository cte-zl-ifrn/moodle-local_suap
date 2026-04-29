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
    private $suapIssuer;
    private $diarioCategory;
    private $campusCategory;
    private $cursoCategory;
    private $semestreCategory;
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
    private $studentAuth;
    private $teacherAuth;
    private $assistantAuth;
    private $default_user_preferences;
    private $roles_not_found = [];


    function do_call() {
        $jsonstring = file_get_contents('php://input');
        $result = $this->process($jsonstring, false);
        $this->insertSyncDB($jsonstring);
        return $result;
    }


    function insertSyncDB($jsonstring) {
        global $DB;

        $DB->insert_record(
            "suap_enrolment_to_sync",
            (object)[
                'json' => $jsonstring,
                'timecreated' => time(),
                'processed' => 0
            ]
        );
    }


    function process($jsonstring, $inBackground) {
        global $CFG;
        $prefix = "{$CFG->wwwroot}/course/view.php";

        // return [
        //     "url" => "$prefix?id=2",
        //     "url_sala_coordenacao" => "$prefix?id=3",
        //     "roles_not_found" => []
        // ];

        // TODO: Verificar a efetividade da validação do JSON
        // TODO: alterar código para sincronizar cada caso se tiver presente no json

        $this->validate_json($jsonstring);

        $this->sync_categories();
        // if ($inBackground) {
            $this->sync_oauth_issuer();
            $this->sync_auths();
            $this->sync_users();
        // }

        $this->isRoom = false;
        $this->sync_course($this->turmaCategory->id);
        $this->diario = $this->course;
        // if ($inBackground) {
            $this->sync_enrols();
            $this->sync_enrolments();
        //     $this->sync_groups();
        //     $this->sync_cohorts();
        // }

        $this->isRoom = true;
        $this->sync_course($this->cursoCategory->id);
        $this->coordenacao = $this->course;
        // if ($inBackground) {
            $this->sync_enrols();
            $this->sync_enrolments();
        //     $this->sync_groups();
        //     $this->sync_cohorts();
        // }

        return [
            "url" => "$prefix?id={$this->diario->id}",
            "url_sala_coordenacao" => "$prefix?id={$this->coordenacao->id}",
            "roles_not_found" => $this->roles_not_found
        ];
    }


    function validate_json($jsonstring) {
        global $CFG;

        $this->json = json_decode($jsonstring);

        if (!$this->json) {
            throw new \Exception("Erro ao decodificar o JSON, favor corrigir.");
        }

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


    function sync_oauth_issuer() {
        $this->suapIssuer = create_or_update(
            'oauth2_issuer',
            [
                'name' => 'suap'
            ],
            [
                'image' => 'https://ead.ifrn.edu.br/portal/wp-content/uploads/2020/08/SUAP.png',
                'loginscopes' => 'identificacao email',
                'loginscopesoffline' => 'identificacao email documentos_pessoais',
                'baseurl' => '',
                'loginparams' => '',
                'loginparamsoffline' => '',
                'alloweddomains' => '',
                'enabled' => 1,
                'showonloginpage' => 1,
                'basicauth' => 0,
                'sortorder' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
                'usermodified' => 2
            ],
            [
                'requireconfirmation' => 0
            ],
            [
                'clientid' => 'changeme',
                'clientsecret' => 'changeme'
            ]
        );
    }


    function sync_auths() {
        global $DB;

        $this->studentAuth = config('default_student_auth');
        $this->teacherAuth = config('default_teacher_auth');
        $this->assistantAuth = config('default_assistant_auth');
        $this->default_user_preferences = config('default_user_preferences');
    }


    function sync_users() {
        global $CFG, $DB;

        $professores = isset($this->json->professores) ? $this->json->professores : [];
        $equipe = isset($this->json->equipe) ? $this->json->equipe : [];
        $alunos = isset($this->json->alunos) ? $this->json->alunos : [];

        foreach (array_merge($professores, $equipe, $alunos) as $usuario) {
            $usuario->isProfessor = isset($usuario->login);
            $usuario->isAluno = isset($usuario->matricula);
            $this->sync_user($usuario);
        }
    }


    function sync_user($usuario) {
        global $DB;

        $username = strtolower($usuario->isAluno ? $usuario->matricula : $usuario->login);
        $email = !empty($usuario->email) ? $usuario->email : $usuario->email_secundario;
        $status = $usuario->ativo ?? $usuario->situacao ?? $usuario->status ?? 'false';
        $suspended = in_array(strtolower($status), ['ativo', 'true']) ? 0 : 1;
        $nome_parts = explode(' ', $usuario->nome);
        $firstname = implode(' ', array_slice($nome_parts, 0, -1));
        $lastname = end($nome_parts);

        if ($usuario->isAluno) {
            $auth = $this->studentAuth;
        } else {
            $auth = $usuario->tipo == 'Principal' ? $this->teacherAuth : $this->assistantAuth;
        }

        $insert_only = ['username' => $username, 'password' => '!aA1' . uniqid(), 'timezone' => '99', 'confirmed' => 1, 'mnethostid' => 1];
        $insert_or_update = ['firstname' => $firstname, 'lastname' => $lastname, 'auth' => $auth, 'email' => $email, 'suspended' => $suspended];

        $usuario->user = $DB->get_record("user", ["username" => $username]);
        if ($usuario->user) {
            \user_update_user(array_merge(['id' => $usuario->user->id], $insert_or_update));
        } else {
            \user_create_user(array_merge($insert_or_update, $insert_only));
            $usuario->user = $DB->get_record("user", ["username" => $username]);
            foreach (preg_split('/\r\n|\r|\n/', $this->default_user_preferences) as $preference) {
                $parts = explode("=", $preference);
                if (count($parts) == 2) {
                    \set_user_preference($parts[0], $parts[1], $usuario->user);
                }
            }

            get_or_create(
                'auth_oauth2_linked_login',
                ['userid' => $usuario->user->id, 'issuerid' => $this->suapIssuer->id, 'username' => $username],
                ['email' => $email, 'timecreated' => time(), 'usermodified' => 0, 'confirmtoken' => '', 'confirmtokenexpires' => 0, 'timemodified' => time()],
            );
        }

        if ($usuario->isAluno) {
            $modalidade = getattr($this->json->curso, 'modalidade', (object)[]);
            $nivel_ensino = getattr($modalidade, 'nivel_ensino', (object)[]);
            $polo = getattr($usuario, 'polo', (object)[]);
            $tipo_doc_certificado = getattr($usuario, 'cpf') == '' ?  'passaporte' : 'cpf';
            
            $custom_fields = [
                // SUAP
                'tipo_usuario' => getattr($usuario, 'tipo_usuario'),
                'eh_servidor' => getattr($usuario, 'eh_servidor'),
                'eh_aluno' => getattr($usuario, 'eh_aluno'),
                'eh_prestador' => getattr($usuario, 'eh_prestador'),
                'eh_usuarioexterno' => getattr($usuario, 'eh_usuarioexterno'),
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
                'campus_id' => getattr($this->json->campus, 'id'),
                'campus_descricao' => getattr($this->json->campus, 'descricao'),
                'campus_sigla' => getattr($this->json->campus, 'sigla'),

                // Curso
                'curso_id' => getattr($this->json->curso, 'id'),
                'curso_codigo' => getattr($this->json->curso, 'codigo'),
                'curso_descricao' => getattr($this->json->curso, 'nome'),
                'curso_modalidade_id' => getattr($modalidade, 'id'),
                'curso_modalidade_descricao' => getattr($modalidade, 'descricao'),
                'curso_nivel_ensino_id' => getattr($nivel_ensino, 'id'),
                'curso_nivel_ensino_descricao' => getattr($nivel_ensino, 'descricao'),

                // Turma
                'turma_id' => getattr($this->json->turma, 'id'),
                'turma_codigo' => getattr($this->json->turma, 'codigo'),

            ];
            
            // Filtra apenas campos com conteúdo
            $custom_fields = array_filter($custom_fields, function($v) {return $v !== '';});
            
            \profile_save_custom_fields($usuario->user->id, $custom_fields);
        }
    }


    function sync_categories() {
        $this->diarioCategory = $this->sync_category(
            config('top_category_idnumber') ?: 'diarios',
            config('top_category_name') ?: 'Diários',
            config('top_category_parent') ?: 0
        );

        $this->campusCategory = $this->sync_category(
            $this->json->campus->sigla,
            $this->json->campus->descricao,
            $this->diarioCategory->id
        );

        $this->cursoCategory = $this->sync_category(
            $this->json->curso->codigo,
            $this->json->curso->nome,
            $this->campusCategory->id
        );

        $ano_periodo = substr($this->json->turma->codigo, 0, 4) . "." . substr($this->json->turma->codigo, 4, 1);
        $this->semestreCategory = $this->sync_category(
            "{$this->json->curso->codigo}.{$ano_periodo}",
            $ano_periodo,
            $this->cursoCategory->id
        );

        $this->turmaCategory = $this->sync_category(
            $this->json->turma->codigo,
            $this->json->turma->codigo,
            $this->semestreCategory->id
        );
    }


    function sync_category($idnumber, $name, $parent) {
        global $DB;

        $category = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
        if (empty($category)) {
            $category = \core_course_category::create(['name' => $name, 'idnumber' => $idnumber, 'parent' => $parent]);
        }

        return $category;
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


    function sync_enrols() {
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

        $mappings = [];
        foreach (explode("\n", config('roles_mapping')) as $mapping_line) {
            $parts = array_map('trim', explode(':', $mapping_line));
            if (count($parts) === 5) {
                [$sala_tipo, $papel_suap, $auth, $role, $enrol] = $parts;
                $mappings["$sala_tipo:$papel_suap"] = [
                    "auth_method" => $auth,
                    "role_shortname" => $role,
                    "enrol_type" => $enrol
                ];
            }
        }

        foreach ($prefixes as $prefix => $keys) {
            if (!isset($mappings[$prefix])) {
                $this->roles_not_found[] = "$prefix:prefix";
                continue;
            }

            ['auth_method' => $auth, 'role_shortname' => $role_name, 'enrol_type' => $enrol_type] = $mappings[$prefix];

            if (!$role = $DB->get_record('role', ['shortname' => $role_name])) {
                $this->roles_not_found[] = "$prefix:role($role_name)";
                continue;
            }

            if (!$enrol_plugin = enrol_get_plugin($enrol_type)) {
                $this->roles_not_found[] = "$prefix:enrol($enrol_type)";
                continue;
            }

            $enrol_instance = $this->get_course_enrol_instance($enrol_type);
            if (!$enrol_instance) {
                $enrol_plugin->add_instance($this->course);
                $enrol_instance = $this->get_course_enrol_instance($enrol_type);
                if (!$enrol_instance) {
                    $this->roles_not_found[] = "$prefix:enrol_instance({$enrol_type}, {$this->course->id})";
                    continue;
                }
            }

            $this->roles_mapping[$prefix] = (object)[
                "auth_method" => $auth,
                "role" => $role,
                "enrol_plugin" => $enrol_plugin,
                "enrol_instance" => $enrol_instance
            ];
        }
    }

    function get_course_enrol_instance($enrol_type) {
        foreach (\enrol_get_instances($this->course->id, FALSE) as $instance) {
            if ($instance->enrol === $enrol_type) {
                return $instance;
            }
        }
    }


    function sync_enrolments() {
        $professores = getattr($this->json, 'professores', []);
        $equipe = getattr($this->json, 'equipe', []);
        $alunos = getattr($this->json, 'alunos', []);
        foreach (array_merge($professores, $equipe, $alunos) as $usuario) {
            $prefix = $this->get_sala_tipo() . ":" .  getattr($usuario, 'tipo', 'Aluno');
            $m = $this->roles_mapping[$prefix];
            $status = strtolower(getattr($usuario, 'situacao_diario', getattr($usuario, 'status', 'inativo'))) === 'ativo' ? 1 : 0;

            var_export([$usuario->user->id, $this->course->id, $m->enrol_instance, $m->role->id]);
            die();

            if ($this->is_user_enrolled_in_role_via_enrol($usuario->user->id, $this->course->id, $m->enrol_instance->enrol->id, $m->role->id)) {
                $m->enrol_plugin->update_user_enrol($m->enrol_instance, $usuario->user->id, $status);
            } else {
                $m->enrol_plugin->enrol_user($m->enrol_instance, $usuario->user->id, $m->role->id, time(), 0, $status);
            }
            die();
        }

        // if (!$this->isRoom) {
        //     // Inativa no diário os ALUNOS que não vieram na sicronização
        //     // Isso não é feito na sala de coordenação porque ele é a concatenação de vários diários
        //     // então um aluno pode não estar em um diário mas estar em outro
        //     return;
        //     foreach ($DB->get_records_sql("SELECT ra.userid FROM {role_assignments} ra WHERE ra.roleid = {$this->aluno_enrol->roleid} AND ra.contextid={$this->context->id}") as $userid => $ra) {
        //         if (!in_array($userid, $alunos_sincronizados)) {
        //             $this->aluno_enrol->enrol->update_user_enrol($this->aluno_enrol->instance, $userid, \ENROL_USER_SUSPENDED);
        //         }
        //     }
        // }
    }

    function is_user_enrolled_in_role_via_enrol($userid, $courseid, $enrolid, $roleid): bool {
        global $DB;

        $sql = "
            SELECT      COUNT(*)
            FROM        {user_enrolments} ue
                            JOIN {enrol} e ON (e.id = ue.enrolid)
                                JOIN {role_assignments} ra ON (ra.contextid = e.contextid AND ra.userid = ue.userid)
                                JOIN {context} ctx ON (ctx.id = e.contextid)
            WHERE       ue.userid        = :userid
              AND       e.id             = :enrolid
              AND       ra.roleid        = :roleid
              AND       ctx.contextlevel = 50
              AND       ue.status        = 0
              AND       e.status         = 0
        ";

        return (int)$DB->get_field_sql($sql, ['userid' => $userid, 'enrolid' => $enrolid, 'roleid' => $roleid]) > 0;
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

            foreach ($grupos as $group_name => $alunos) {
                $group = $this->sync_group($group_name);
                $idDosAlunosFaltandoAgrupar = $this->getIdDosAlunosFaltandoAgrupar($group, $alunos);
                foreach ($alunos as $group_name => $usuario) {
                    if (!in_array($usuario->user->id, $idDosAlunosFaltandoAgrupar)) {
                        \groups_add_member($group->id, $usuario->user->id);
                    }
                }
            }
        }
    }


    function sync_group($group_name) {
        global $DB;
        $data = ['courseid' => $this->course->id, 'name' => $group_name];
        $group = $DB->get_record('groups', $data);
        $custom_fields_metadata = \core_course\customfield\course_handler::create()->export_instance_data_object($this->course->id, true);
        $synchronized_groups = $custom_fields_metadata->grupos_sincronizados == '' ? [] : explode(',', $custom_fields_metadata->grupos_sincronizados);
        $is_group_synchronized = in_array($group_name, $synchronized_groups);

        if (!$group && !$is_group_synchronized) {
            $groupid = \groups_create_group((object)$data);
            $group = $DB->get_record('groups', ['id' => $groupid]);
        }
        $synchronized_groups[] = $group_name;

        update_course($this->course);
        $this->course->customfield_grupos_sincronizados = implode(',', array_unique($synchronized_groups));

        return $group;
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


    function sync_cohorts() {
        global $DB;

        $roles = [];
        $instances = [];
        $coortesid = [];
        $enrol = enrol_get_plugin("cohort");
        if (isset(($this->json->coortes))) {
            foreach ($this->json->coortes as $coorte) {
                if (!isset($instances[$coorte->role])) {
                    $instance = $DB->get_record('cohort', ['idnumber' => $coorte->idnumber]);
                    if (!$instance) {
                        $coortesid[$coorte->role] = \cohort_add_cohort(
                            (object)[
                                "name" => $coorte->nome,
                                "idnumber" => $coorte->idnumber,
                                "description" => $coorte->descricao,
                                "visible" => $coorte->ativo,
                                "contextid" => 1
                            ]
                        );
                    } else {
                        $instance->name = $coorte->nome;
                        $instance->idnumber = $coorte->idnumber;
                        $instance->description = $coorte->descricao;
                        $instance->visible = $coorte->ativo;
                        \cohort_update_cohort($instance);
                        $coortesid[$coorte->role] = $instance->id;
                    }
                }
                $cohortid = $coortesid[$coorte->role];

                foreach ($coorte->colaboradores as $usuario) {
                    $usuario->isAluno = False;
                    $usuario->isProfessor = False;
                    $usuario->isColaborador = True;
                    $usuario->tipo = "Staff";
                    $this->sync_user($usuario);
                    \cohort_add_member($cohortid, $usuario->user->id);
                }

                if (!isset($roles[$coorte->role])) {
                    $roles[$coorte->role] = $DB->get_record('role', ['shortname' => $coorte->role]);
                }
                $role = $roles[$coorte->role];

                if ($role) {
                    if (!isset($instances[$cohortid])) {
                        $instances[$cohortid] = $DB->get_record('enrol', ["enrol" => "cohort", "customint1" => $cohortid, "courseid" => $this->course->id]);
                        if (!$instance) {
                            $enrol->add_instance($this->course, ["customint1" => $cohortid, "roleid" => $role->id, "customint2" => 0]);
                        }
                    }
                    $instance = $instances[$cohortid];
                } else {
                    $this->roles_not_found[] = $coorte->role;
                }
            }
        }
    }

}