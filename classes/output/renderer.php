<?php
namespace local_suap\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

class renderer extends plugin_renderer_base {
    
    public function render_relatorio_page(relatorio_page $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_suap/report', $data);
    }
}
