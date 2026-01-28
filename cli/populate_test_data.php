<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/user/lib.php');

cli_heading('Populando dados de teste para local_suap');

global $DB;

// Configurações
$num_campus = ['Natal-Central', 'Mossoró', 'Pau dos Ferros', 'Parnamirim', 'Caicó'];
$course_names = [
    'Introdução ao Python',
    'Gestão de Projetos',
    'Marketing Digital',
    'Excel Avançado',
    'Segurança da Informação'
];

cli_heading('1. Criando cursos');

$created_courses = [];
$category_id = 1; // Categoria padrão

foreach ($course_names as $index => $course_name) {
    foreach ($num_campus as $campus) {
        cli_writeln("Criando: {$course_name} - {$campus}");
        
        $course = new stdClass();
        $course->fullname = $course_name;
        $course->shortname = strtolower(str_replace(' ', '_', $course_name)) . '_' . $campus . '_' . time();
        $course->category = $category_id;
        $course->visible = 1;
        $course->startdate = time() - (30 * 24 * 60 * 60);
        $course->enddate = time() + (60 * 24 * 60 * 60);
        $course->format = 'topics';
        $course->newsitems = 0;
        $course->showgrades = 1;
        $course->showreports = 0;
        $course->maxbytes = 0;
        $course->enablecompletion = 1;
        
        $created_course = create_course($course);
        
        // Adicionar custom fields
        $fieldid_tipo = $DB->get_field('customfield_field', 'id', ['shortname' => 'diario_tipo']);
        $fieldid_campus = $DB->get_field('customfield_field', 'id', ['shortname' => 'campus_descricao']);
        
        if ($fieldid_tipo) {
            $data = new stdClass();
            $data->fieldid = $fieldid_tipo;
            $data->instanceid = $created_course->id;
            $data->value = 'minicurso';
            $data->valueformat = 0;
            $data->timecreated = time();
            $data->timemodified = time();
            
            // Verificar se já existe
            if (!$DB->record_exists('customfield_data', ['fieldid' => $fieldid_tipo, 'instanceid' => $created_course->id])) {
                $DB->insert_record('customfield_data', $data);
            }
        }
        
        if ($fieldid_campus) {
            $data = new stdClass();
            $data->fieldid = $fieldid_campus;
            $data->instanceid = $created_course->id;
            $data->value = $campus;
            $data->valueformat = 0;
            $data->timecreated = time();
            $data->timemodified = time();
            
            if (!$DB->record_exists('customfield_data', ['fieldid' => $fieldid_campus, 'instanceid' => $created_course->id])) {
                $DB->insert_record('customfield_data', $data);
            }
        }
        
        $created_courses[] = $created_course;
    }
}

cli_heading('2. Criando usuários e inscrevendo');

$num_students_per_course = [10, 15, 20, 25, 30];

foreach ($created_courses as $index => $course) {
    $num_students = $num_students_per_course[array_rand($num_students_per_course)];
    
    cli_writeln("Inscrevendo {$num_students} alunos em: {$course->fullname}");
    
    // Pegar método de inscrição manual
    $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    if (!$enrol) {
        // Criar método de inscrição manual se não existir
        $enrol_plugin = enrol_get_plugin('manual');
        $enrol_plugin->add_instance($course);
        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    }
    
    $manual_plugin = enrol_get_plugin('manual');
    
    for ($i = 0; $i < $num_students; $i++) {
        // Criar usuário
        $user = new stdClass();
        $user->username = 'aluno_' . $index . '_' . $i . '_' . time() . rand(1, 999);
        $user->password = hash_internal_user_password('Test123!');
        $user->firstname = 'Aluno';
        $user->lastname = "Teste {$index}-{$i}";
        $user->email = $user->username . '@teste.com';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->timecreated = time();
        $user->timemodified = time();
        
        $userid = user_create_user($user, false, false);
        
        // Inscrever como estudante (roleid 5)
        $manual_plugin->enrol_user($enrol, $userid, 5, time(), 0);
        
        // Simular acesso (70% dos alunos)
        if (rand(1, 100) <= 70) {
            $context = context_course::instance($course->id);
            
            $log = new stdClass();
            $log->userid = $userid;
            $log->courseid = $course->id;
            $log->eventname = '\core\event\course_viewed';
            $log->component = 'core';
            $log->action = 'viewed';
            $log->target = 'course';
            $log->objecttable = 'course';
            $log->objectid = $course->id;
            $log->contextid = $context->id;
            $log->contextlevel = CONTEXT_COURSE;
            $log->contextinstanceid = $course->id;
            $log->edulevel = 2; 
            $log->timecreated = time() - rand(1, 86400 * 20);
            $log->other = 'N;';
            $log->ip = '127.0.0.1';
            $log->realuserid = null;
            
            $DB->insert_record('logstore_standard_log', $log);
        }
    }
}

cli_heading('3. Criando atividades e notas');

foreach ($created_courses as $course) {
    cli_writeln("Criando atividades para: {$course->fullname}");
    
    // Criar um quiz manualmente
    $quiz = new stdClass();
    $quiz->course = $course->id;
    $quiz->name = 'Avaliação Final';
    $quiz->intro = 'Avaliação final do curso';
    $quiz->introformat = FORMAT_HTML;
    $quiz->timeopen = 0;
    $quiz->timeclose = 0;
    $quiz->timelimit = 0;
    $quiz->overduehandling = 'autosubmit';
    $quiz->graceperiod = 0;
    $quiz->preferredbehaviour = 'deferredfeedback';
    $quiz->canredoquestions = 0;
    $quiz->attempts = 0;
    $quiz->attemptonlast = 0;
    $quiz->grademethod = 1;
    $quiz->decimalpoints = 2;
    $quiz->questiondecimalpoints = -1;
    $quiz->reviewattempt = 69904;
    $quiz->reviewcorrectness = 4368;
    $quiz->reviewmarks = 4368;
    $quiz->reviewspecificfeedback = 4368;
    $quiz->reviewgeneralfeedback = 4368;
    $quiz->reviewrightanswer = 4368;
    $quiz->reviewoverallfeedback = 4368;
    $quiz->questionsperpage = 1;
    $quiz->navmethod = 'free';
    $quiz->shuffleanswers = 1;
    $quiz->sumgrades = 100;
    $quiz->grade = 100;
    $quiz->timecreated = time();
    $quiz->timemodified = time();
    
    $quizid = $DB->insert_record('quiz', $quiz);
    
    // Adicionar módulo do curso
    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'quiz']);
    $cm->instance = $quizid;
    $cm->section = 0;
    $cm->added = time();
    $cm->visible = 1;
    $cm->groupmode = 0;
    $cm->groupingid = 0;
    
    $cmid = $DB->insert_record('course_modules', $cm);
    
    // Atualizar section
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
    if ($section) {
        if (empty($section->sequence)) {
            $section->sequence = $cmid;
        } else {
            $section->sequence .= ',' . $cmid;
        }
        $DB->update_record('course_sections', $section);
    }
    
    // Criar grade_item
    $grade_item = new stdClass();
    $grade_item->courseid = $course->id;
    $grade_item->categoryid = null;
    $grade_item->itemname = 'Avaliação Final';
    $grade_item->itemtype = 'mod';
    $grade_item->itemmodule = 'quiz';
    $grade_item->iteminstance = $quizid;
    $grade_item->itemnumber = 0;
    $grade_item->iteminfo = null;
    $grade_item->idnumber = '';
    $grade_item->calculation = null;
    $grade_item->gradetype = 1;
    $grade_item->grademax = 100;
    $grade_item->grademin = 0;
    $grade_item->scaleid = null;
    $grade_item->outcomeid = null;
    $grade_item->gradepass = 60;
    $grade_item->multfactor = 1.0;
    $grade_item->plusfactor = 0.0;
    $grade_item->aggregationcoef = 0;
    $grade_item->aggregationcoef2 = 0;
    $grade_item->sortorder = 1;
    $grade_item->display = 0;
    $grade_item->decimals = null;
    $grade_item->hidden = 0;
    $grade_item->locked = 0;
    $grade_item->locktime = 0;
    $grade_item->needsupdate = 0;
    $grade_item->weightoverride = 0;
    $grade_item->timecreated = time();
    $grade_item->timemodified = time();
    
    $gradeitemid = $DB->insert_record('grade_items', $grade_item);
    
    // Pegar alunos inscritos
    $context = context_course::instance($course->id);
    $students = get_enrolled_users($context, '', 0, 'u.id');
    
    // 60% fazem a avaliação
    $students_array = array_values($students);
    $num_to_grade = (int)(count($students_array) * 0.6);
    $students_to_grade = array_slice($students_array, 0, $num_to_grade);
    
    foreach ($students_to_grade as $student) {
        // 60% passam, 40% reprovam
        $passed = rand(1, 100) <= 60;
        $finalgrade = $passed ? rand(60, 100) : rand(30, 59);
        
        // Inserir nota
        $grade = new stdClass();
        $grade->itemid = $gradeitemid;
        $grade->userid = $student->id;
        $grade->rawgrade = $finalgrade;
        $grade->rawgrademax = 100;
        $grade->rawgrademin = 0;
        $grade->rawscaleid = null;
        $grade->usermodified = 2; // admin
        $grade->finalgrade = $finalgrade;
        $grade->hidden = 0;
        $grade->locked = 0;
        $grade->locktime = 0;
        $grade->exported = 0;
        $grade->overridden = 0;
        $grade->excluded = 0;
        $grade->feedback = null;
        $grade->feedbackformat = 0;
        $grade->information = null;
        $grade->informationformat = 0;
        $grade->timecreated = time() - rand(1, 86400 * 10);
        $grade->timemodified = time() - rand(1, 86400 * 5);
        
        $DB->insert_record('grade_grades', $grade);
        
        // Marcar conclusão para 50% dos aprovados
        if ($passed && rand(1, 100) <= 50) {
            $completion = new stdClass();
            $completion->userid = $student->id;
            $completion->course = $course->id;
            $completion->timeenrolled = time() - (20 * 86400);
            $completion->timestarted = time() - (15 * 86400);
            $completion->timecompleted = time() - rand(1, 86400 * 5);
            $completion->reaggregate = 0;
            
            if (!$DB->record_exists('course_completions', ['userid' => $student->id, 'course' => $course->id])) {
                $DB->insert_record('course_completions', $completion);
            }
        }
    }
    
    rebuild_course_cache($course->id, true);
}

cli_heading('✓ Dados de teste criados com sucesso!');
cli_writeln('Total de cursos criados: ' . count($created_courses));
cli_writeln('');
cli_writeln('Próximos passos:');
cli_writeln('1. Execute a task: php admin/cli/scheduled_task.php --execute=\\local_suap\\task\\report_task');
cli_writeln('2. Acesse: http://localhost/local/suap/pages/relatorio.php');
