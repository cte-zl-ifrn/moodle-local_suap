<?php
namespace local_suap\service;

defined('MOODLE_INTERNAL') || die();

class course_metrics {

    public static function calculate($courseid) {

        $data = new \stdClass();

        $data->total_enrolled     = self::count_enrolled($courseid);
        $data->accessed           = self::count_accessed($courseid);
        $data->final_exam_takers  = self::count_final_exam_takers($courseid);
        $data->passed             = self::count_passed($courseid);
        $data->avg_grade          = self::get_average_grade($courseid);
        $data->with_certificate   = self::count_with_certificate($courseid);
        $data->completed          = self::count_completed($courseid);

        $data->no_access = max(0, $data->total_enrolled - $data->accessed);
        $data->failed    = max(0, $data->final_exam_takers - $data->passed);

        $data->without_certificate = max(
            0,
            min($data->passed, $data->final_exam_takers) - $data->with_certificate
        );

        if ($data->completed == 0 && ($data->with_certificate + $data->without_certificate) > 0) {
            $data->completed = $data->with_certificate + $data->without_certificate;
        }

        return $data;
    }


    private static function count_enrolled($courseid) {
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

    private static function count_accessed($courseid) {
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

    private static function count_final_exam_takers($courseid) {
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

    private static function count_passed($courseid) {
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

    private static function get_average_grade($courseid) {
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

    private static function count_with_certificate($courseid) {
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

    private static function count_completed($courseid) {
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

}
