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
$string['auth_token_header'] = 'Authentication token';
$string['auth_token_header_desc'] = 'Which will be the token used by SUAP to authenticate itself to this Moodle installation';
$string["auth_token"] = 'SUAP auth token';
$string["auth_token_desc"] = 'Which will be the token used by SUAP to authenticate itself to this Moodle installation';

$string['painel_url'] = 'Painel AVA URL';
$string['painel_url_desc'] = '(ex: https://ava.ifrn.edu.br)';

# Categories
$string['top_category_header'] = 'Top category';
$string['top_category_header_desc'] = 'Top category default settings';
$string["top_category_idnumber"] = 'Top category id number';
$string["top_category_idnumber_desc"] = 'Used to identify where put new courses, if a category with this idnumber does not exists create a new category with this idnumber';
$string["top_category_name"] = 'Top category name';
$string["top_category_name_desc"] = 'Used only to create the new top category';
$string["top_category_parent"] = 'Top category parent';
$string["top_category_parent_desc"] = 'Used only to create the new top category';

# New user and new enrolment defaults
$string['user_and_enrolment_header'] = 'New user and new enrolment defaults';
$string['user_and_enrolment_header_desc'] = 'Top category default settings';

# User preferences
$string["default_user_preferences"] = 'Default user preferences';
$string["default_user_preferences_desc"] = 'All new user (student or teacher) will have this preferences. Use one line per preferece. Like a .ini file.';

# Student
$string["default_student_auth"] = 'Default method authentication for new student users';
$string["default_student_auth_desc"] = 'We recommend that you configure oAuth with SOAP, but... the choices are yours. But why oauth? Because your students can take advantage of the SSO and Painel AVA for SUAP.';
$string["default_student_role_id"] = 'Default roleid for a new student enrolment';
$string["default_student_role_id_desc"] = 'Normally 5. Why? This is the Moodle default.';
$string["default_student_enrol_type"] = 'Default enrol_type for a new student enrolment';
$string["default_student_enrol_type_desc"] = 'Normally manual. Why? Because new students will be enrolled -manually- on SUAP and synched to Moodle';

# Inactivated Student
$string["default_inactivated_student_auth"] = 'Default method authentication for inactivated student users';
$string["default_inactivated_student_auth_desc"] = 'We recommend that you configure oAuth with SOAP, but... the choices are yours. But why oauth? Because your students can take advantage of the SSO and Painel AVA for SUAP.';
$string["default_inactivated_student_role_id"] = 'Default roleid for a inactivated student enrolment';
$string["default_inactivated_student_role_id_desc"] = 'Normally 5. Why? This is the Moodle default.';
$string["default_inactivated_student_enrol_type"] = 'Default enrol_type for a inactivated student enrolment';
$string["default_inactivated_student_enrol_type_desc"] = 'Normally manual. Why? Because inactivated students will be enrolled -manually- on SUAP and synched to Moodle';

# Teacher in course
$string["default_teacher_auth"] = 'Default method authentication for new teacher users';
$string["default_teacher_auth_desc"] = 'We recommend that you configure oAuth with SOAP, but... the choices are yours. But why oauth? Because your teachers can take advantage of the SSO and Painel AVA for SUAP.';
$string["default_teacher_role_id"] = 'Default roleid for a new teacher enrolment';
$string["default_teacher_role_id_desc"] = 'Normally 3. Why? This is the Moodle default.';
$string["default_teacher_enrol_type"] = 'Default enrol_type for a new teacher enrolment';
$string["default_teacher_enrol_type_desc"] = 'Normally manual. Why? Because new teachers will be enrolled -manually- on SUAP and synched to Moodle';

# Assistant in course
$string["default_assistant_auth"] = 'Default method authentication for new assistant users';
$string["default_assistant_auth_desc"] = 'We recommend that you configure oAuth with SOAP, but... the choices are yours. But why oauth? Because your assistants can take advantage of the SSO and Painel AVA for SUAP.';
$string["default_assistant_role_id"] = 'Default roleid for a new assistant enrolment';
$string["default_assistant_role_id_desc"] = 'Normally 3. Why? This is the Moodle default.';
$string["default_assistant_enrol_type"] = 'Default enrol_type for a new assistant enrolment';
$string["default_assistant_enrol_type_desc"] = 'Normally manual. Why? Because new assistants will be enrolled -manually- on SUAP and synched to Moodle';

# Instructor in course
$string["default_instructor_auth"] = 'Default method authentication for new instructor users';
$string["default_instructor_auth_desc"] = 'We recommend that you configure oAuth with SOAP, but... the choices are yours. But why oauth? Because your instructors can take advantage of the SSO and Painel AVA for SUAP.';
$string["default_instructor_role_id"] = 'Default roleid for a new instructor enrolment';
$string["default_instructor_role_id_desc"] = 'Normally 3. Why? This is the Moodle default.';
$string["default_instructor_enrol_type"] = 'Default enrol_type for a new instructor enrolment';
$string["default_instructor_enrol_type_desc"] = 'Normally manual. Why? Because new instructors will be enrolled -manually- on SUAP and synched to Moodle';

# Task
$string["sync_up_enrolments_task"] = 'Sync Up Enrolments Task';
$string["sync_up_enrolments_task_desc"] = 'Sync Up Enrolments Task';

# Notas
$string["notes_to_sync_header"] = 'Sincronização de notas';
$string["notes_to_sync_header_desc"] = 'Sincronização de notas';
$string["notes_to_sync"] = 'Notas a sincronizar';
$string["notes_to_sync_desc"] = 'Notas a sincronizar';

# Grupos do curso
$string['groups_in_course_header'] = 'Groups in course';
$string['groups_in_course_header_desc'] = 'Groups in course';
$string["course_group_entrada"] = 'Sync groups for entrada';
$string["course_group_entrada_desc"] = 'Sync groups for entrada';
$string["course_group_turma"] = 'Sync groups for turma';
$string["course_group_turma_desc"] = 'Sync groups for turma';
$string["course_group_polo"] = 'Sync groups for polo';
$string["course_group_polo_desc"] = 'Sync groups for polo';
$string["course_group_programa"] = 'Sync groups for programa';
$string["course_group_programa_desc"] = 'Sync groups for programa';

# Grupos da sala de coordenação
$string['groups_in_room_header'] = 'Groups in coordination room';
$string['groups_in_room_header_desc'] = 'Groups in coordination room';
$string["room_group_entrada"] = 'Sync groups for entrada';
$string["room_group_entrada_desc"] = 'Sync groups for entrada';
$string["room_group_turma"] = 'Sync groups for turma';
$string["room_group_turma_desc"] = 'Sync groups for turma';
$string["room_group_polo"] = 'Sync groups for polo';
$string["room_group_polo_desc"] = 'Sync groups for polo';
$string["room_group_programa"] = 'Sync groups for programa';
$string["room_group_programa_desc"] = 'Sync groups for programa';
