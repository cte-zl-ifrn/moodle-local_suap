<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

require_login();

$PAGE->set_url(new moodle_url('/local/suap/pages/relatorio.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('relatorio', 'local_suap'));
$PAGE->set_heading(get_string('relatorio', 'local_suap'));

echo $OUTPUT->header();

// Criar o renderable que busca os dados
$relatorio = new \local_suap\output\relatorio_page();

// Renderizar usando o renderer
$renderer = $PAGE->get_renderer('local_suap');
echo $renderer->render($relatorio);

echo $OUTPUT->footer();
