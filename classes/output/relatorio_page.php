<?php
namespace local_suap\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

class relatorio_page implements renderable, templatable {
    
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();

        $minicursos = $this->get_minicursos_data();
        $proitec    = $this->get_proitec_data();

        $data->has_minicursos = !empty($minicursos);
        $data->has_proitec    = !empty($proitec);

        if ($minicursos) {
            $data->minicursos = $minicursos;
        }

        if ($proitec) {
            $data->proitec = $proitec;
        }

        if (!$data->has_minicursos && !$data->has_proitec) {
            $data->nodata = true;
            $data->nodatamessage = get_string('nodata', 'analytics');
        }

        return $data;
    }


    private function get_minicursos_data() {
        global $DB;

        $latest_time = $DB->get_field_sql(
            "SELECT MAX(timegenerated)
            FROM {local_suap_relatorio_cursos_autoinstrucionais}"
        );

        if (!$latest_time) {
            return null;
        }

        $records = $DB->get_records(
            'local_suap_relatorio_cursos_autoinstrucionais',
            ['timegenerated' => $latest_time],
            'curso_nome, campus'
        );

        if (!$records) {
            return null;
        }

        $grouped_by_curso = [];
        $course_counter = 0;

        foreach ($records as $record) {
            $curso_nome = $record->curso_nome;

            if (!isset($grouped_by_curso[$curso_nome])) {
                $course_counter++;

                $grouped_by_curso[$curso_nome] = [
                    'curso_nome' => $curso_nome,
                    'curso_id'   => 'curso-' . $course_counter,
                    'export_url' => (new \moodle_url(
                        '/local/suap/cursos/export.php',
                        [
                            'curso_nome'    => $curso_nome,
                            'timegenerated' => $latest_time
                        ]
                    ))->out(false),
                    'campus_list' => []
                ];
            }

            $total        = $record->total_enrolled;
            $exam_takers  = $record->final_exam_takers;
            $eligible     = $record->with_certificate + $record->without_certificate;

            $campus = new \stdClass();
            $campus->campus_nome = $record->campus;
            $campus->campus_id   = clean_param($record->campus, PARAM_ALPHANUMEXT);

            $campus->curso_url = (new \moodle_url(
                '/course/view.php',
                ['id' => $record->courseid]
            ))->out(false);

            $campus->curso_codigo = $record->curso_codigo;

            $campus->total_enrolled = $record->total_enrolled;
            $campus->accessed       = $record->accessed;
            $campus->no_access      = $record->no_access;

            $campus->pct_accessed   = $total > 0 ? round(($record->accessed / $total) * 100, 2) : 0;
            $campus->pct_no_access  = $total > 0 ? round(($record->no_access / $total) * 100, 2) : 0;

            $campus->final_exam_takers = $record->final_exam_takers;
            $campus->pct_exam_takers   = $total > 0 ? round(($exam_takers / $total) * 100, 2) : 0;

            $campus->passed       = $record->passed;
            $campus->failed       = $record->failed;
            $campus->pct_passed   = $exam_takers > 0 ? round(($record->passed / $exam_takers) * 100, 2) : 0;
            $campus->pct_failed   = $exam_takers > 0 ? round(($record->failed / $exam_takers) * 100, 2) : 0;

            $campus->avg_grade = number_format($record->avg_grade, 2, ',', '.');

            $campus->with_certificate    = $record->with_certificate;
            $campus->without_certificate = $record->without_certificate;

            $campus->pct_with_cert    = $eligible > 0 ? round(($record->with_certificate / $eligible) * 100, 2) : 0;
            $campus->pct_without_cert = $eligible > 0 ? round(($record->without_certificate / $eligible) * 100, 2) : 0;

            $campus->completed     = $record->completed;
            $campus->pct_completed = $total > 0 ? round(($record->completed / $total) * 100, 2) : 0;

            $grouped_by_curso[$curso_nome]['campus_list'][] = $campus;
        }

        return [
            'lastupdated' => userdate($latest_time, get_string('strftimedatetimeshort')),
            'cursos'      => array_values($grouped_by_curso)
        ];
    }


    private function get_proitec_data() {
        global $DB;

        $latest_time = $DB->get_field_sql(
            "SELECT MAX(timegenerated)
            FROM {local_suap_relatorio_proitec}"
        );

        if (!$latest_time) {
            return null;
        }

        $records = $DB->get_records(
            'local_suap_relatorio_proitec',
            ['timegenerated' => $latest_time],
            'ano_semestre, campus, disciplina'
        );

        if (!$records) {
            return null;
        }

        $campi = [];

        foreach ($records as $r) {

            $campus = $r->campus;
            $semkey = $r->ano_semestre;

            // 🔹 Campus
            if (!isset($campi[$campus])) {
                $campi[$campus] = [
                    'campus' => $campus,
                    'semestres' => []
                ];
            }

            // 🔹 Ano/Semestre
            if (!isset($campi[$campus]['semestres'][$semkey])) {
                $campi[$campus]['semestres'][$semkey] = [
                    'id' => clean_param($semkey . '-' . $campus, PARAM_ALPHANUMEXT),
                    'ano_semestre' => substr($semkey, 0, 4) . '.' . substr($semkey, 4, 1),

                    // acumuladores para o resumo
                    'total_enrolled' => $r->total_enrolled,
                    'grade_sum' => 0,
                    'grade_count' => 0,
                    'completed' => 0,

                    'disciplinas' => []
                ];
            }

            // 🔹 Detalhe por disciplina
            $total = $r->total_enrolled;
            $exam  = $r->final_exam_takers;

            $disciplina = [
                'disciplina' => ucfirst($r->disciplina),

                'accessed' => $r->accessed,
                'no_access' => $r->no_access,
                'pct_accessed' => $total > 0 ? round(($r->accessed / $total) * 100, 2) : 0,

                'final_exam_takers' => $exam,
                'pct_exam_takers' => $total > 0 ? round(($exam / $total) * 100, 2) : 0,

                'passed' => $r->passed,
                'failed' => $r->failed,
                'pct_passed' => $exam > 0 ? round(($r->passed / $exam) * 100, 2) : 0,

                'avg_grade' => number_format($r->avg_grade, 2, ',', '.'),

                'with_certificate' => $r->with_certificate,
                'without_certificate' => $r->without_certificate,

                'completed' => $r->completed,
                'pct_completed' => $total > 0 ? round(($r->completed / $total) * 100, 2) : 0,
            ];

            $campi[$campus]['semestres'][$semkey]['disciplinas'][] = $disciplina;

            // 🔹 Acumuladores do resumo
            if ($exam > 0) {
                $campi[$campus]['semestres'][$semkey]['grade_sum']
                    += ($r->avg_grade * $exam);
                $campi[$campus]['semestres'][$semkey]['grade_count']
                    += $exam;
            }

            $campi[$campus]['semestres'][$semkey]['completed']
                = min(
                    $campi[$campus]['semestres'][$semkey]['completed'],
                    $r->completed
                );
        }

        // 🔹 Finalizar resumos
        foreach ($campi as &$campus) {
            foreach ($campus['semestres'] as &$sem) {

                $avg = $sem['grade_count'] > 0
                    ? round($sem['grade_sum'] / $sem['grade_count'], 2)
                    : 0;

                $sem['resumo'] = [
                    'total_enrolled' => $sem['total_enrolled'],
                    'avg_grade_geral' => number_format($avg, 2, ',', '.'),
                    'completed' => $sem['completed'],
                    'pct_completed' => $sem['total_enrolled'] > 0
                        ? round(($sem['completed'] / $sem['total_enrolled']) * 100, 2)
                        : 0,
                ];

                unset(
                    $sem['grade_sum'],
                    $sem['grade_count'],
                    $sem['total_enrolled'],
                    $sem['completed']
                );
            }

            // manter semestres como array indexado (Mustache)
            $campus['semestres'] = array_values($campus['semestres']);
        }

        return [
            'lastupdated' => userdate($latest_time, get_string('strftimedatetimeshort')),
            'campi' => array_values($campi)
        ];
    }
}
