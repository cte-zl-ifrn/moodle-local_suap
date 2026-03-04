<?php
namespace local_suap\task;

defined('MOODLE_INTERNAL') || die();

use local_suap\service\course_metrics;

class generate_report_task extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('generate_report_task', 'local_suap');
    }
    
    public function execute() {
        global $DB;
        
        mtrace('=== INICIANDO RELATÓRIO DE CURSOS AUTOINSTRUCIONAIS ===');
        
        $now = time();
        mtrace('Data de execução: ' . date('Y-m-d H:i:s', $now));
                
        $sql = "
        SELECT
            c.id,
            c.fullname AS curso_nome,
            cfd_campus.value AS campus,
            cfd_codigo.value AS curso_codigo
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

        LEFT JOIN {customfield_field} cff_codigo
          ON cff_codigo.shortname = 'curso_codigo'
        LEFT JOIN {customfield_data} cfd_codigo
          ON cfd_codigo.fieldid = cff_codigo.id
        AND cfd_codigo.instanceid = c.id

        WHERE LOWER(cfd_diario.value) = 'minicurso'
          AND c.visible = 1
          AND c.startdate < :nowstart
          AND (c.enddate = 0 OR c.enddate > :nowend)

        ORDER BY c.fullname, campus
        ";

        $courses = $DB->get_records_sql($sql, [
            'nowstart' => $now,
            'nowend'   => $now,
        ]);

        if (!$courses) {
            mtrace('Nenhum minicurso encontrado.');
            return;
        }

        $grouped = [];

        foreach ($courses as $course) {

            $metrics = course_metrics::calculate($course->id);

            $curso_nome = $course->curso_nome;
            $campus = !empty($course->campus) ? $course->campus : 'Não informado';

            $key = $curso_nome . '|' . $campus;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'curso_nome' => $curso_nome,
                    'courseid' => $course->id,
                    'curso_codigo' => $course->curso_codigo ?? null, 
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
            
            // Pega a referência dos totais do grupo atual
            $t = $grouped[$key]['totals'];

            // Incrementa a contagem de turmas nesse grupo
            $grouped[$key]['quantidade_cursos']++;

            // Soma direta das métricas calculadas
            $t->total_enrolled      += $metrics->total_enrolled;
            $t->accessed            += $metrics->accessed;
            $t->no_access           += $metrics->no_access;
            $t->final_exam_takers   += $metrics->final_exam_takers;
            $t->passed              += $metrics->passed;
            $t->failed              += $metrics->failed;
            $t->with_certificate    += $metrics->with_certificate;
            $t->without_certificate += $metrics->without_certificate;
            $t->completed           += $metrics->completed;

            // Média ponderada
            if ($metrics->final_exam_takers > 0) {
                $t->grade_sum   += ($metrics->avg_grade * $metrics->final_exam_takers);
                $t->grade_count += $metrics->final_exam_takers;
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
                'courseid'          => $data['courseid'],
                'curso_codigo'      => $data['curso_codigo'],
                'curso_nome'        => $data['curso_nome'],
                'campus'            => $data['campus'],
                'diario_tipo'       => 'minicurso',
                'quantidade_cursos' => $data['quantidade_cursos'],

                'total_enrolled'      => $data['totals']->total_enrolled,
                'accessed'            => $data['totals']->accessed,
                'no_access'           => $data['totals']->no_access,
                'final_exam_takers'   => $data['totals']->final_exam_takers,
                'passed'              => $data['totals']->passed,
                'failed'              => $data['totals']->failed,
                'avg_grade'           => $data['totals']->avg_grade,
                'with_certificate'    => $data['totals']->with_certificate,
                'without_certificate' => $data['totals']->without_certificate,
                'completed'           => $data['totals']->completed,

                'timegenerated' => $now
            ];

            $DB->insert_record('local_suap_relatorio_cursos_autoinstrucionais', $record);
        }

        mtrace('=== RELATÓRIO FINALIZADO ===');
    }
    
}
