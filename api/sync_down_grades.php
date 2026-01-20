<?php

namespace local_suap;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Desabilita verificação CSRF para esta API
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../config.php');
require_once("../locallib.php");
require_once("servicelib.php");

// Link de acesso (exemplo): http://ava/local/suap/api/sync_down_grades.php?codigo_diario=20231.1.15806.1E.TEC.1386

class sync_down_grades_service extends service
{

    function do_call()
    {
        global $CFG, $DB;
        $notes_to_sync = config('notes_to_sync') ?: "'N1', 'N2', 'N3', 'N4', 'NAF'";
        try {
            $notas = $DB->get_records_sql("
                WITH a AS (
                    SELECT  ra.userid                        AS id_usuario,
                            u.username                       AS matricula,
                            u.firstname || ' ' || u.lastname AS nome_completo,
                            u.email                          AS email,
                            c.id                             AS id_curso
                    FROM     {course} AS c
                                INNER JOIN {context} AS ctx ON (c.id=ctx.instanceid AND ctx.contextlevel=50)
                                    INNER JOIN {role_assignments} AS ra ON (ctx.id=ra.contextid)
                                        INNER JOIN {role} AS r ON (ra.roleid=r.id AND r.archetype='student')
                                        INNER JOIN {user} AS u ON (ra.userid=u.id)
                    WHERE    C.idnumber LIKE '%#' || ?
                )
                SELECT   a.matricula, a.nome_completo,
                        (
                                SELECT   jsonb_object_agg(gi.idnumber::text, gg.finalgrade)
                                FROM     {grade_items} gi
                                            inner join {grade_grades} gg on (gg.itemid=gi.id AND gg.userid = a.id_usuario)
                                WHERE    gi.idnumber IN ($notes_to_sync)
                                AND    gi.courseid = a.id_curso
                        ) notas,
                        (
                            SELECT (coalesce(CASE WHEN COUNT(mc.id)=0.0 THEN NULL ELSE COUNT(mc.id)::FLOAT END / COUNT(cm.id)::FLOAT, 0.0) * 100.0)::DECIMAL(7,2)
                            FROM mdl_course                                         AS c
                                    LEFT JOIN mdl_course_modules                    AS cm ON (c.id = cm.course AND cm.completion > 0)
                                        LEFT JOIN  mdl_course_modules_completion    AS mc ON (cm.id = mc.coursemoduleid)
                            WHERE   (c.id=a.id_curso AND mc.userid=a.id_usuario)
                            GROUP BY c.id, c.fullname, c.shortname, c.idnumber
                        ) completude
                FROM     a
                ORDER BY a.nome_completo           
            ", [$_GET['diario_id']]);
            # Usar mais um campo para filtar além de diario_id
            $result = array_values($notas);
            foreach ($result as $key => $aluno) {
                if ($aluno->notas != null) {
                    $aluno->notas = json_decode($aluno->notas);
                    $aluno->completude = $aluno->completude != null ? floatval($aluno->completude) : $aluno->completude;
                }
            }
            return $result;
        } catch (Exception $ex) {
            die("error");
            http_response_code(500);
            if ($ex->getMessage() == "Data submitted is invalid (value: Data submitted is invalid)") {
                echo json_encode(["error" => ["message" => "Ocorreu uma inconsistência no servidor do AVA. Este erro é conhecido e a solução dele já está sendo estudado pela equipe de desenvolvimento. Favor tentar novamente em 5 minutos."]]);
            } else {
                echo json_encode(["error" => ["message" => $ex->getMessage()]]);
            }
        }
    }
}
