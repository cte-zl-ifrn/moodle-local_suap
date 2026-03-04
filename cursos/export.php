<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('local/suap:view_mooc_reports', context_system::instance());

global $DB;

$curso_nome = required_param('curso_nome', PARAM_TEXT);
$timegenerated = required_param('timegenerated', PARAM_INT);

$records = $DB->get_records(
    'local_suap_relatorio_cursos_autoinstrucionais',
    [
        'curso_nome'   => $curso_nome,
        'timegenerated'=> $timegenerated
    ],
    'campus'
);

if (!$records) {
    throw new moodle_exception('nodata', 'local_suap');
}

// Cabeçalhos do Excel
$columns = [
    'campus'             => 'Campus',
    'curso_codigo'       => 'Código do Curso',
    'curso_nome'         => 'Curso',
    'total_enrolled'     => 'Total Inscritos',
    'accessed'           => 'Acessaram',
    'no_access'          => 'Nunca Acessaram',
    'final_exam_takers'  => 'Fizeram Avaliação Final',
    'passed'             => 'Aprovados',
    'failed'             => 'Reprovados',
    'avg_grade'          => 'Nota Média',
    'with_certificate'   => 'Com Certificado',
    'without_certificate'=> 'Aptos sem Certificado',
    'completed'          => 'Concluídos',
];

// Montar dados
$data = [];
foreach ($records as $r) {
    $data[] = [
        $r->campus,
        $r->curso_codigo,
        $r->curso_nome,
        $r->total_enrolled,
        $r->accessed,
        $r->no_access,
        $r->final_exam_takers,
        $r->passed,
        $r->failed,
        $r->avg_grade,
        $r->with_certificate,
        $r->without_certificate,
        $r->completed,
    ];
}

$filename = clean_filename(
    'relatorio_' . $curso_nome . '_' . userdate($timegenerated, '%Y%m%d_%H%M')
);

// Exportar
\core\dataformat::download_data(
    $filename,
    'excel',
    array_values($columns),
    $data
);
