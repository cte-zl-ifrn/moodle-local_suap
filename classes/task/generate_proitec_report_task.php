<?php
namespace local_suap\task;

defined('MOODLE_INTERNAL') || die();

use local_suap\service\course_metrics;

class generate_proitec_report_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('generate_proitec_report_task', 'local_suap');
    }

    public function execute() {
        global $DB;

        mtrace('=== INICIANDO RELATÓRIO PROITEC ===');

        $sql = "
        SELECT
            c.id,
            c.fullname,
            c.shortname,
            SUBSTRING(c.shortname, 1, 5) AS ano_semestre,
            cfd_campus.value AS campus,
            
            CASE
                WHEN c.shortname LIKE '%FIC.1195%' THEN 'portugues'
                WHEN c.shortname LIKE '%FIC.1196%' THEN 'matematica'
                WHEN c.shortname LIKE '%FIC.1197%' THEN 'etica'
                WHEN c.shortname LIKE '%FIC.1198%' THEN 'jornada'
            END AS disciplina

        FROM {course} c

        LEFT JOIN {customfield_field} cff_campus
            ON cff_campus.shortname = 'campus_descricao'

        LEFT JOIN {customfield_data} cfd_campus
            ON cfd_campus.fieldid = cff_campus.id
            AND cfd_campus.instanceid = c.id

        WHERE (
                c.shortname LIKE '%FIC.1195%'
            OR c.shortname LIKE '%FIC.1196%'
            OR c.shortname LIKE '%FIC.1197%'
            OR c.shortname LIKE '%FIC.1198%'
        )
        AND c.visible = 1

        ORDER BY ano_semestre, campus, disciplina
        ";

        $records = $DB->get_records_sql($sql);

        if (!$records) {
            mtrace('Nenhum curso PROITEC encontrado.');
            return;
        }

        $groups = [];

        foreach ($records as $course) {

            $campus = $course->campus ?: 'Não informado';
            $key = $course->ano_semestre . '|' . $campus;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'ano_semestre' => $course->ano_semestre,
                    'campus'       => $campus,

                    'courses' => [],

                    // métricas virão depois
                    'metrics' => [
                        'total_enrolled' => 0,
                        'accessed'       => 0,
                        'completed'      => 0,
                        'passed'         => 0,
                    ],
                ];
            }

            // Guarda o courseid pelo tipo de disciplina
            $groups[$key]['courses'][$course->disciplina] = [
                'courseid'   => $course->id,
                'fullname'   => $course->fullname,
                'shortname'  => $course->shortname,
            ];
        }

        // Verificação de integridade: cada grupo deve ter as 4 disciplinas
        $expected = ['portugues', 'matematica', 'etica', 'jornada'];

        foreach ($groups as $key => $group) {
            $missing = array_diff($expected, array_keys($group['courses']));

            if (!empty($missing)) {
                mtrace(
                    "Grupo PROITEC incompleto ({$key}). Faltando: " .
                    implode(', ', $missing)
                );

                unset($groups[$key]); // remove grupo inválido
            }
        }


        foreach ($groups as $key => &$group) {

            mtrace("Calculando métricas PROITEC: {$key}");

            foreach ($group['courses'] as $disciplina => &$courseinfo) {

                $courseid = $courseinfo['courseid'];

                $metrics = course_metrics::calculate($courseid);

                // Guarda métricas do curso individual
                $courseinfo['metrics'] = $metrics;

                mtrace(" - {$disciplina}: inscritos={$metrics->total_enrolled}, aprovados={$metrics->passed}");
            }
        }
        unset($group, $courseinfo);

    }
}