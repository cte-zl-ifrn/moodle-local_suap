<?php
namespace local_suap\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

class relatorio_page implements renderable, templatable {
    
    public function export_for_template(renderer_base $output) {
        global $DB;
        
        $data = new stdClass();
        
        // Buscar dados mais recentes
        $latest_time = $DB->get_field_sql(
            "SELECT MAX(timegenerated) FROM {local_suap_relatorio_cursos_autoinstrucionais}"
        );
        
        if (!$latest_time) {
            $data->hasdata = false;
            $data->nodatamessage = get_string('nodata', 'analytics');
            return $data;
        }
        
        $data->hasdata = true;
        $data->lastupdated = userdate($latest_time, get_string('strftimedatetimeshort'));
        
        // Buscar todos os cursos
        $records = $DB->get_records('local_suap_relatorio_cursos_autoinstrucionais', [
            'timegenerated' => $latest_time
        ], 'curso_nome, campus');
        
        // Agrupar por curso
        $grouped_by_curso = [];
        
        foreach ($records as $record) {
            $curso_nome = $record->curso_nome;
            
            if (!isset($grouped_by_curso[$curso_nome])) {
                $course_counter++;

                $grouped_by_curso[$curso_nome] = [
                    'curso_nome' => $curso_nome,
                    'curso_id' => 'curso-' . $course_counter,
                    'campus_list' => []
                ];
            }
            
            // Calcular percentuais
            $total = $record->total_enrolled;
            $exam_takers = $record->final_exam_takers;
            $eligible = $record->with_certificate + $record->without_certificate;
            
            $campus_data = new stdClass();
            $campus_data->campus_nome = $record->campus;
            $campus_data->campus_id = clean_param($record->campus, PARAM_ALPHANUMEXT);
            
            $campus_data->total_enrolled = $record->total_enrolled;
            $campus_data->accessed = $record->accessed;
            $campus_data->no_access = $record->no_access;
            $campus_data->pct_accessed = $total > 0 ? round(($record->accessed / $total) * 100, 2) : 0;
            $campus_data->pct_no_access = $total > 0 ? round(($record->no_access / $total) * 100, 2) : 0;
            
            $campus_data->final_exam_takers = $record->final_exam_takers;
            $campus_data->pct_exam_takers = $total > 0 ? round(($exam_takers / $total) * 100, 2) : 0;
            
            $campus_data->passed = $record->passed;
            $campus_data->failed = $record->failed;
            $campus_data->pct_passed = $exam_takers > 0 ? round(($record->passed / $exam_takers) * 100, 2) : 0;
            $campus_data->pct_failed = $exam_takers > 0 ? round(($record->failed / $exam_takers) * 100, 2) : 0;
            
            $campus_data->avg_grade = number_format($record->avg_grade, 2, ',', '.');
            
            $campus_data->with_certificate = $record->with_certificate;
            $campus_data->without_certificate = $record->without_certificate;
            $campus_data->pct_with_cert = $eligible > 0 ? round(($record->with_certificate / $eligible) * 100, 2) : 0;
            $campus_data->pct_without_cert = $eligible > 0 ? round(($record->without_certificate / $eligible) * 100, 2) : 0;
            
            $campus_data->completed = $record->completed;
            $campus_data->pct_completed = $total > 0 ? round(($record->completed / $total) * 100, 2) : 0;
            
            $grouped_by_curso[$curso_nome]['campus_list'][] = $campus_data;
        }
        
        // Converter para array indexado para o template
        $data->cursos = array_values($grouped_by_curso);
        
        return $data;
    }
}
