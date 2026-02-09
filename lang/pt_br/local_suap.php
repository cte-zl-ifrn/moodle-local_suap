<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_suap
 * @category    string
 * @copyright   2022 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SUAP Integration';
$string['suap:adminview'] = 'Ver o admin do SUAP';

# Auth token
$string['auth_token_header'] = 'Token de autenticação';
$string['auth_token_header_desc'] = 'Qual será o token utilizado pelo SUAP para se autenticar nesta instalação do Moodle';
$string["auth_token"] = 'SUAP auth token';
$string["auth_token_desc"] = 'Qual será o token utilizado pelo SUAP para se autenticar nesta instalação do Moodle';

$string['painel_url'] = 'URL do Painel AVA';
$string['painel_url_desc'] = '(ex: https://ava.ifrn.edu.br)';

# Categories
$string['top_category_header'] = 'Categoria principal';
$string['top_category_header_desc'] = 'Configurações padrão da categoria principal';
$string["top_category_iznumber"] = 'Número de identificação da categoria superior';
$string["top_category_idnumber_desc"] = 'Usado para identificar onde colocar novos cursos, caso não exista uma categoria com este idnumber crie uma nova categoria com este idnumber';
$string["top_category_name"] = 'Nome da categoria principal';
$string["top_category_name_desc"] = 'Usado apenas para criar a nova categoria principal';
$string["top_category_parent"] = 'Pai de categoria superior';
$string["top_category_parent_desc"] = 'Usado apenas para criar a nova categoria principal';

# New user and new enrolment defaults
$string['user_and_enrolment_header'] = 'Novo usuário e novos padrões de inscrição';
$string['user_and_enrolment_header_desc'] = 'Configurações padrão da categoria principal';

# User preferences
$string["default_user_preferences"] = 'Preferências padrão do usuário';
$string["default_user_preferences_desc"] = 'Todo novo usuário (aluno ou professor) terá essas preferências. Use uma linha por preferência. Como um arquivo .ini.';

# Student
$string["student_settings_header"] = 'Sincronização de estudantes';
$string["student_settings_header_desc"] = 'Sincronização de estudantes';
$string["default_student_auth"] = 'Autenticação de método padrão para novos usuários alunos';
$string["default_student_auth_desc"] = 'Recomendamos que você configure oAuth com SOAP, mas... as escolhas são suas. Mas por que oauth? Porque seus alunos podem usufruir do portal SSO e AVA para SUAP.';
$string["default_student_role_id"] = 'Roleid padrão para uma inscrição de aluno';
$string["default_student_role_id_desc"] = 'Normalmente 5. Por quê? Este é o padrão do Moodle.';
$string["default_student_enrol_type"] = 'Enrol_type padrão para uma inscrição de aluno inativa';
$string["default_student_enrol_type_desc"] = 'Normalmente manuais. Por que? Porque os novos alunos serão matriculados no SUAP e sincronizados com o Moodle';

# Teacher
$string["teacher_settings_header"] = 'Sincronização de professores';
$string["teacher_settings_header_desc"] = 'Sincronização de professores';
$string["default_teacher_auth"] = 'Autenticação de método padrão para novos usuários professores';
$string["default_teacher_auth_desc"] = 'Recomendamos que você configure oAuth com SOAP, mas... as escolhas são suas. Mas por que oauth? Porque seus alunos podem usufruir do portal SSO e AVA para SUAP.';
$string["default_teacher_role_id"] = 'Roleid padrão para uma inscrição como professor';
$string["default_teacher_role_id_desc"] = 'Normalmente 5. Por quê? Este é o padrão do Moodle.';
$string["default_teacher_enrol_type"] = 'Enrol_type padrão para uma inscrição como professor';
$string["default_teacher_enrol_type_desc"] = 'Normalmente manuais. Por que? Porque os novos alunos serão matriculados no SUAP e sincronizados com o Moodle';

# Tutores
$string["assistant_settings_header"] = 'Sincronização de tutores';
$string["assistant_settings_header_desc"] = 'Sincronização de tutores';
$string["default_assistant_auth"] = 'Autenticação de método padrão para novos usuários tutores';
$string["default_assistant_auth_desc"] = 'Recomendamos que você configure oAuth com SOAP, mas... as escolhas são suas. Mas por que oauth? Porque seus alunos podem usufruir do portal SSO e AVA para SUAP.';
$string["default_assistant_role_id"] = 'Roleid padrão para uma inscrição como tutor';
$string["default_assistant_role_id_desc"] = 'Normalmente 5. Por quê? Este é o padrão do Moodle.';
$string["default_assistant_enrol_type"] = 'Enrol_type padrão para uma inscrição como tutor';
$string["default_assistant_enrol_type_desc"] = 'Normalmente manuais. Por que? Porque os novos alunos serão matriculados no SUAP e sincronizados com o Moodle';

# Docentes nas salas de coordenação
$string["instructor_settings_header"] = 'Sincronização de colaboradores em salas de coordenação';
$string["instructor_settings_header_desc"] = 'Sincronização de colaboradores em salas de coordenação';
$string["default_instructor_auth"] = 'Autenticação de método padrão para novos usuários docentes em salas de coordenação';
$string["default_instructor_auth_desc"] = 'Recomendamos que você configure oAuth com SOAP, mas... as escolhas são suas. Mas por que oauth? Porque seus alunos podem usufruir do portal SSO e AVA para SUAP.';
$string["default_instructor_role_id"] = 'Roleid padrão para uma inscrição como docente em salas de coordenação';
$string["default_instructor_role_id_desc"] = 'Normalmente 4. Por quê? Este é o padrão do Moodle para professores que não podem editar.';
$string["default_instructor_enrol_type"] = 'Enrol_type padrão para uma inscrição como docente em salas de coordenação';
$string["default_instructor_enrol_type_desc"] = 'Normalmente manuais. Por que? Porque os novos docentes em salas de coordenação serão matriculados no SUAP e sincronizados com o Moodle';

# Formador
$string["former_settings_header"] = 'Sincronização de formadores';
$string["former_settings_header_desc"] = 'Sincronização de formadores';
$string["default_former_auth"] = 'Autenticação de método padrão para novos usuários formadores em salas de coordenação';
$string["default_former_auth_desc"] = 'Recomendamos que você configure oAuth com SOAP, mas... as escolhas são suas. Mas por que oauth? Porque seus alunos podem usufruir do portal SSO e AVA para SUAP.';
$string["default_former_role_id"] = 'Roleid padrão para uma inscrição como formador em salas de coordenação';
$string["default_former_role_id_desc"] = 'Normalmente 4. Por quê? Este é o padrão do Moodle para professores que não podem editar.';
$string["default_former_enrol_type"] = 'Enrol_type padrão para uma inscrição como formador em salas de coordenação';
$string["default_former_enrol_type_desc"] = 'Normalmente manuais. Por que? Porque os novos formadores em salas de coordenação serão matriculados no SUAP e sincronizados com o Moodle';

# Mediador
$string["moderator_settings_header"] = 'Sincronização de moderadores';
$string["moderator_settings_header_desc"] = 'Sincronização de moderadores';
$string["default_moderator_auth"] = 'Autenticação de método padrão para novos usuários moderadores em salas de coordenação';
$string["default_moderator_auth_desc"] = 'Recomendamos que você configure oAuth com SOAP, mas... as escolhas são suas. Mas por que oauth? Porque seus alunos podem usufruir do portal SSO e AVA para SUAP.';
$string["default_moderator_role_id"] = 'Roleid padrão para uma inscrição como moderador em salas de coordenação';
$string["default_moderator_role_id_desc"] = 'Normalmente 4. Por quê? Este é o padrão do Moodle para professores que não podem editar.';
$string["default_moderator_enrol_type"] = 'Enrol_type padrão para uma inscrição como moderador em salas de coordenação';
$string["default_moderator_enrol_type_desc"] = 'Normalmente manuais. Por que? Porque os novos moderadores em salas de coordenação serão matriculados no SUAP e sincronizados com o Moodle';

# Task
$string["sync_up_enrolments_task"] = 'Sync Up Enrolments Task';
$string["sync_up_enrolments_task_desc"] = 'Sync Up Enrolments Task';
$string["generate_report_task"] = 'Relatório de cursos autoinstrucionais.';

# Notas
$string["notes_to_sync_header"] = 'Sincronização de notas';
$string["notes_to_sync_header_desc"] = 'Sincronização de notas';
$string["notes_to_sync"] = 'Notas a sincronizar';
$string["notes_to_sync_desc"] = 'Notas a sincronizar';

# Grupos do curso
$string['groups_in_course_header'] = 'Grupos no curso';
$string['groups_in_course_header_desc'] = 'Grupos no curso';
$string["course_group_entrada"] = 'Sincronizar grupos para entrada';
$string["course_group_entrada_desc"] = 'Sincronizar grupos para entrada';
$string["course_group_turma"] = 'Sincronizar grupos para turma';
$string["course_group_turma_desc"] = 'Sincronizar grupos para turma';
$string["course_group_polo"] = 'Sincronizar grupos para polo';
$string["course_group_polo_desc"] = 'Sincronizar grupos para polo';
$string["course_group_programa"] = 'Sincronizar grupos para programa';
$string["course_group_programa_desc"] = 'Sincronizar grupos para programa';

# Grupos da sala de coordenação
$string['groups_in_room_header'] = 'Grupos na sala de coordenação';
$string['groups_in_room_header_desc'] = 'Grupos na sala de coordenação';
$string["room_group_entrada"] = 'Sincronizar grupos para entrada';
$string["room_group_entrada_desc"] = 'Sincronizar grupos para entrada';
$string["room_group_turma"] = 'Sincronizar grupos para turma';
$string["room_group_turma_desc"] = 'Sincronizar grupos para turma';
$string["room_group_polo"] = 'Sincronizar grupos para polo';
$string["room_group_polo_desc"] = 'Sincronizar grupos para polo';
$string["room_group_programa"] = 'Sincronizar grupos para programa';
$string["room_group_programa_desc"] = 'Sincronizar grupos para programa';
