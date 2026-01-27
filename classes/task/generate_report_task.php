<?php
namespace local_suap\task;

defined('MOODLE_INTERNAL') || die();

class generate_report_task extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('generate_report_task', 'local_suap');
    }
    
    public function execute() {
        global $DB, $CFG;
        
        mtrace('=== INICIANDO RELATÓRIO DE CURSOS AUTOINSTRUCIONAIS ===');
        
        $now = time();
        mtrace('Data de execução: ' . date('Y-m-d H:i:s', $now));
                
        $sql = "
        SELECT
            c.id,
            c.fullname AS curso_nome,
            cfd_campus.value AS campus
        FROM {course} c

        JOIN {customfield_field} cff_diario
        ON cff_diario.shortname = 'diario_tipo'
        JOIN {customfield_data} cfd_diario
        ON cfd_diario.fieldid = cff_diario.id
        AND cfd_diario.instanceid = c.id

        LEFT JOIN {customfield_field} cff_campus
        ON cff_campus.shortname = 'campus_descricao'
        LEFT JOIN {customfield_data} cfd_campus
        ON cfd_campus.fieldid = cff_campus.id
        AND cfd_campus.instanceid = c.id

        WHERE LOWER(cfd_diario.value) = 'minicurso'
        AND c.visible = 1
        AND c.startdate < :nowstart
        AND (c.enddate = 0 OR c.enddate > :nowend)

        ORDER BY c.fullname, campus
        ";

        $courses = $DB->get_records_sql($sql, [
            'nowstart' => $now,
            'nowend' => $now,
        ]);

        if (!$courses) {
            mtrace('Nenhum minicurso encontrado.');
            return;
        }

        $grouped = [];

        foreach ($courses as $course) {
            $curso_nome = $course->curso_nome;
            $campus = !empty($course->campus) ? $course->campus : 'Não informado';

            $key = $curso_nome . '|' . $campus;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'curso_nome' => $curso_nome,
                    'campus' => $campus,
                    'quantidade_cursos' => 0,
                    'totals' => (object)[
                        'total_enrolled' => 0,
                        'accessed' => 0,
                        'no_access' => 0,
                        'final_exam_takers' => 0,
                        'passed' => 0,
                        'failed' => 0,
                        'with_certificate' => 0,
                        'without_certificate' => 0,
                        'completed' => 0,
                        'grade_sum' => 0,
                        'grade_count' => 0,
                    ]
                ];
            }

            $enrolled   = $this->count_enrolled($course->id);
            $accessed   = $this->count_accessed($course->id);
            $exam_takers = $this->count_final_exam_takers($course->id);
            $passed     = $this->count_passed($course->id);
            $avg_grade  = $this->get_average_grade($course->id);
            $with_cert  = $this->count_with_certificate($course->id);
            $eligible = max(0, min($passed, $exam_takers) - $with_cert);
            
            $completed = $this->count_completed($course->id);

            if ($completed == 0 && ($with_cert + $eligible) > 0) {
                $completed = $with_cert + $eligible;
            }

            // Pega a referência dos totais do grupo atual
            $t = $grouped[$key]['totals'];

            // Incrementa a contagem de turmas nesse grupo
            $grouped[$key]['quantidade_cursos']++;

            // Soma os valores nas estatísticas gerais do grupo
            $t->total_enrolled += $enrolled;
            $t->accessed       += $accessed;
            $t->completed      += $completed;
            $t->final_exam_takers += $exam_takers;
            $t->passed         += $passed;
            $t->with_certificate += $with_cert;
            $t->without_certificate += $eligible;

            // Cálculos derivados (que não vêm direto do banco)
            // Quem nunca acessou = Inscritos - Acessaram
            $no_access_calc = ($enrolled - $accessed);
            if ($no_access_calc < 0) $no_access_calc = 0; // Prevenção contra erros de log
            $t->no_access += $no_access_calc;

            // Reprovados = Quem fez a atividade final - Quem passou
            // (Ou você pode definir reprovado como quem acessou e não passou, depende da regra)
            $failed_calc = ($exam_takers - $passed);
            if ($failed_calc < 0) $failed_calc = 0;
            $t->failed += $failed_calc;

            // Acumula notas para média ponderada depois
            // Se a média do curso é 8.0 e 10 alunos fizeram, somamos 80 pontos ao montante
            if ($exam_takers > 0) {
                $t->grade_sum += ($avg_grade * $exam_takers);
                $t->grade_count += $exam_takers;
            }

            // mtrace("   Processado: ID {$course->id} | {$curso_nome} ! {$campus} | Inscritos: {$enrolled}");

        }
        
        foreach ($grouped as $key => $data) {
            $t = $grouped[$key]['totals']; 
            
            $t->avg_grade = $t->grade_count > 0
                ? round($t->grade_sum / $t->grade_count, 2)
                : 0;
            
            unset($t->grade_sum, $t->grade_count);
        }
        
        $DB->delete_records('local_suap_relatorio_cursos_autoinstrucionais');

        foreach ($grouped as $data) {
            mtrace("Salvando: Curso='{$data['curso_nome']}' | Campus='{$data['campus']}'");

            $record = (object)[
                'curso_nome' => $data['curso_nome'],
                'campus' => $data['campus'],
                'diario_tipo' => 'minicurso',
                'quantidade_cursos' => $data['quantidade_cursos'],

                'total_enrolled' => $data['totals']->total_enrolled,
                'accessed' => $data['totals']->accessed,
                'no_access' => $data['totals']->no_access,
                'final_exam_takers' => $data['totals']->final_exam_takers,
                'passed' => $data['totals']->passed,
                'failed' => $data['totals']->failed,
                'avg_grade' => $data['totals']->avg_grade,
                'with_certificate' => $data['totals']->with_certificate,
                'without_certificate' => $data['totals']->without_certificate,
                'completed' => $data['totals']->completed,

                'timegenerated' => $now
            ];

            $DB->insert_record('local_suap_relatorio_cursos_autoinstrucionais', $record);
        }

        mtrace('=== RELATÓRIO FINALIZADO ===');
    }
    
    private function count_enrolled($courseid) {
        global $DB;
        
        $sql = "SELECT COUNT(DISTINCT u.id) as total
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {context} ctx ON ctx.id = ra.contextid 
                    AND ctx.instanceid = e.courseid
                    AND ctx.contextlevel = 50
                WHERE e.courseid = :courseid 
                AND ra.roleid = 5
                AND e.status = 0 
                AND ue.status = 0
                AND u.deleted = 0";
            
        $result = $DB->get_record_sql($sql, ['courseid' => $courseid]);
        return $result->total;
    }
    
    private function count_accessed($courseid) {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT ula.userid) AS total
                FROM {user_lastaccess} ula
                JOIN {role_assignments} ra ON ra.userid = ula.userid
                JOIN {context} ctx ON ctx.id = ra.contextid
                    AND ctx.instanceid = :courseid
                    AND ctx.contextlevel = 50
                WHERE ula.courseid = :courseid2
                AND ra.roleid = 5";

        $result = $DB->get_record_sql($sql, [
            'courseid'  => $courseid,
            'courseid2' => $courseid,
        ]);

        return (int)($result->total ?? 0);
    }
    
    private function count_completed($courseid) {
        global $DB;
        
        $sql = "SELECT COUNT(DISTINCT cc.userid) as total
                FROM {course_completions} cc
                JOIN {role_assignments} ra ON ra.userid = cc.userid
                JOIN {context} ctx ON ctx.id = ra.contextid
                    AND ctx.instanceid = :courseid
                    AND ctx.contextlevel = 50
                WHERE cc.course = :courseid2
                AND cc.timecompleted IS NOT NULL
                AND ra.roleid = 5";

        $result = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'courseid2' => $courseid
        ]);
        return $result->total;
    }

    private function count_final_exam_takers($courseid) {
        global $DB;
        
        // Identificar qual é a "última" atividade do curso.
        $sql_last_item = "SELECT gi.id
                        FROM {grade_items} gi
                        WHERE gi.courseid = :courseid
                        AND gi.itemtype = 'mod'
                        AND gi.itemmodule IN ('quiz', 'assign')
                        ORDER BY gi.sortorder DESC
                        LIMIT 1";
        
        $last_item = $DB->get_record_sql($sql_last_item, ['courseid' => $courseid]);
        
        // Se não houver nenhuma atividade, retorna 0 imediatamente
        if (!$last_item) {
            mtrace("    Aviso: Nenhuma atividade (quiz/assignment) encontrada no curso {$courseid}");
            return 0;
        }

        mtrace("    ID da última atividade: {$last_item->id}");
        
        // Contar quantos alunos têm nota nessa atividade específica
        $sql_count = "SELECT COUNT(DISTINCT g.userid) as total
                    FROM {grade_grades} g
                    JOIN {role_assignments} ra ON ra.userid = g.userid
                    JOIN {context} ctx ON ctx.id = ra.contextid
                        AND ctx.instanceid = :courseid
                        AND ctx.contextlevel = 50
                    WHERE g.itemid = :itemid
                    AND g.finalgrade IS NOT NULL
                    AND ra.roleid = 5";
        
        $result = $DB->get_record_sql($sql_count, [
            'courseid' => $courseid,
            'itemid'   => $last_item->id
        ]);
        
        $count = $result ? (int)$result->total : 0;
        mtrace("    Alunos que fizeram a última atividade: {$count}");
        
        return $count;
    }

    private function count_passed($courseid) {
        global $DB;
        
        // Conta usuários que passaram (nota >= nota mínima do curso ou >= 6 se não configurada)
        $sql = "SELECT COUNT(DISTINCT gg.userid) as total
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gi.id = gg.itemid
                JOIN {role_assignments} ra ON ra.userid = gg.userid
                JOIN {context} ctx ON ctx.id = ra.contextid
                    AND ctx.instanceid = :courseid
                    AND ctx.contextlevel = 50
                WHERE gi.courseid = :courseid2
                AND gi.itemtype = 'course'
                AND gg.finalgrade IS NOT NULL
                AND gg.finalgrade >= CASE 
                    WHEN gi.gradepass IS NULL OR gi.gradepass = 0 
                    THEN 60 
                    ELSE gi.gradepass 
                END
                AND ra.roleid = 5";
        
        $result = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'courseid2' => $courseid
        ]);
        return $result ? (int)$result->total : 0;
    }

    private function get_average_grade($courseid) {
        global $DB;
        
        $sql = "SELECT AVG(gg.finalgrade) as avg_grade
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gi.id = gg.itemid
                JOIN {role_assignments} ra ON ra.userid = gg.userid
                JOIN {context} ctx ON ctx.id = ra.contextid
                    AND ctx.instanceid = :courseid
                    AND ctx.contextlevel = 50
                WHERE gi.courseid = :courseid2
                AND gi.itemtype = 'course'
                AND gg.finalgrade IS NOT NULL
                AND ra.roleid = 5";
        
        $result = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'courseid2' => $courseid
        ]);
        return $result && $result->avg_grade ? round($result->avg_grade, 2) : 0;
    }

    private function count_with_certificate($courseid) {
        global $DB;
        
        try {
            $dbman = $DB->get_manager();
            
            // Verificar se as tabelas do plugin coursecertificate existem
            $table_issues = new \xmldb_table('tool_certificate_issues');
            
            if (!$dbman->table_exists($table_issues)) {
                return 0; // Plugin não instalado
            }
            
            // Contar certificados emitidos para este curso
            // coursecertificate (atividade) -> tool_certificate_issues (emissões)
            $sql = "SELECT COUNT(DISTINCT tci.userid) as total
                    FROM {tool_certificate_issues} tci
                    JOIN {coursecertificate} cc ON cc.template = tci.templateid
                    JOIN {role_assignments} ra ON ra.userid = tci.userid
                    JOIN {context} ctx ON ctx.id = ra.contextid
                        AND ctx.instanceid = :courseid
                        AND ctx.contextlevel = 50
                    WHERE cc.course = :courseid2
                    AND tci.code IS NOT NULL
                    AND ra.roleid = 5";
            
            $result = $DB->get_record_sql($sql, [
                'courseid' => $courseid,
                'courseid2' => $courseid
            ]);
            return $result ? (int)$result->total : 0;
            
        } catch (\Exception $e) {
            mtrace("Aviso: Não foi possível contar certificados - {$e->getMessage()}");
            return 0;
        }
    }
    
}
