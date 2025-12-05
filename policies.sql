-- Enable RLS (ERD tables)
alter table public.role enable row level security;
alter table public.permission enable row level security;
alter table public.role_permission enable row level security;
alter table public.user_role enable row level security;
alter table public."user" enable row level security;
alter table public.grade enable row level security;
alter table public.student enable row level security;
alter table public.guardian enable row level security;
alter table public.student_guardian enable row level security;
alter table public.employee enable row level security;
alter table public.employee_task enable row level security;
alter table public.attendance enable row level security;
alter table public.student_payment enable row level security;
alter table public.teacher_payment enable row level security;
alter table public.invoice enable row level security;
alter table public.activity enable row level security;
alter table public.activity_media enable row level security;
alter table public.student_observation enable row level security;
alter table public.notification enable row level security;
alter table public.audit_log enable row level security;
alter table public.session enable row level security;

-- Basic read access scoped by permissions (view)
create policy "read_roles_view" on public.role for select using (public.has_permission('Usuarios y Roles','view'));
create policy "read_permission_view" on public.permission for select using (public.has_permission('Usuarios y Roles','view'));
create policy "read_role_permission_view" on public.role_permission for select using (public.has_permission('Usuarios y Roles','view'));
create policy "read_own_user_role" on public.user_role for select using (
	exists (select 1 from public."user" u where u."UserID" = "UserID" and u."AuthUserID" = auth.uid())
);
create policy "read_grades_view" on public.grade for select using (public.has_permission('Grados','view'));
create policy "read_students_view" on public.student for select using (public.has_permission('Estudiantes','view'));
create policy "read_guardians_view" on public.guardian for select using (public.has_permission('Responsables','view'));
-- Permitir a Usuarios y Roles ver empleados y responsables para la lista de pendientes
create policy "read_employee_from_users_roles" on public.employee for select using (public.has_permission('Usuarios y Roles','view'));
create policy "read_guardian_from_users_roles" on public.guardian for select using (public.has_permission('Usuarios y Roles','view'));
create policy "read_student_guardian_view" on public.student_guardian for select using (public.has_permission('Estudiantes','view'));
create policy "read_employee_view" on public.employee for select using (public.has_permission('Personal','view'));
create policy "read_employee_task_view" on public.employee_task for select using (public.has_permission('Tareas','view'));
create policy "read_attendance_view" on public.attendance for select using (public.has_permission('Asistencia','view'));
create policy "read_student_payment" on public.student_payment for select using (auth.role() = 'authenticated');
create policy "read_teacher_payment" on public.teacher_payment for select using (auth.role() = 'authenticated');
create policy "read_invoice" on public.invoice for select using (auth.role() = 'authenticated');
create policy "read_activity_view" on public.activity for select using (public.has_permission('Actividades','view'));
create policy "read_activity_media_view" on public.activity_media for select using (public.has_permission('Actividades','view'));
create policy "read_student_observation" on public.student_observation for select using (auth.role() = 'authenticated');
create policy "read_notification" on public.notification for select using (auth.role() = 'authenticated');
create policy "read_audit_log" on public.audit_log for select using (auth.role() = 'authenticated');
create policy "read_session" on public.session for select using (auth.role() = 'authenticated');

-- Insert/update/delete scoped by 'edit' permissions
create policy "ins_role_edit" on public.role for insert with check (public.has_permission('Usuarios y Roles','edit'));
create policy "upd_role_edit" on public.role for update using (public.has_permission('Usuarios y Roles','edit'));
create policy "del_role_edit" on public.role for delete using (public.has_permission('Usuarios y Roles','edit'));

create policy "ins_permission_edit" on public.permission for insert with check (public.has_permission('Usuarios y Roles','edit'));
create policy "upd_permission_edit" on public.permission for update using (public.has_permission('Usuarios y Roles','edit'));
create policy "del_permission_edit" on public.permission for delete using (public.has_permission('Usuarios y Roles','edit'));

create policy "ins_role_permission_edit" on public.role_permission for insert with check (public.has_permission('Usuarios y Roles','edit'));
create policy "del_role_permission_edit" on public.role_permission for delete using (public.has_permission('Usuarios y Roles','edit'));

-- user_role: restringir a su propio usuario interno
create policy "insert_own_user_role" on public.user_role for insert with check (
	exists (select 1 from public."user" u where u."UserID" = "UserID" and u."AuthUserID" = auth.uid())
);
create policy "delete_own_user_role" on public.user_role for delete using (
	exists (select 1 from public."user" u where u."UserID" = "UserID" and u."AuthUserID" = auth.uid())
);
-- Además, permitir administrar roles de usuarios si se tiene permiso 'Usuarios y Roles' edit
create policy "manage_user_role_admin" on public.user_role for all using (public.has_permission('Usuarios y Roles','edit')) with check (public.has_permission('Usuarios y Roles','edit'));

-- user: el usuario puede ver/crear/editar solo su propio registro (por AuthUserID)
create policy "read_own_user" on public."user" for select using ("AuthUserID" = auth.uid());
create policy "insert_own_user" on public."user" for insert with check ("AuthUserID" = auth.uid());
create policy "update_own_user" on public."user" for update using ("AuthUserID" = auth.uid());

create policy "ins_student_edit" on public.student for insert with check (public.has_permission('Estudiantes','edit'));
create policy "upd_student_edit" on public.student for update using (public.has_permission('Estudiantes','edit'));
create policy "del_student_edit" on public.student for delete using (public.has_permission('Estudiantes','edit'));

create policy "ins_grade_edit" on public.grade for insert with check (public.has_permission('Grados','edit'));
create policy "upd_grade_edit" on public.grade for update using (public.has_permission('Grados','edit'));
create policy "del_grade_edit" on public.grade for delete using (public.has_permission('Grados','edit'));

create policy "ins_guardian_edit" on public.guardian for insert with check (public.has_permission('Responsables','edit'));
create policy "upd_guardian_edit" on public.guardian for update using (public.has_permission('Responsables','edit'));
create policy "del_guardian_edit" on public.guardian for delete using (public.has_permission('Responsables','edit'));

create policy "ins_student_guardian_edit" on public.student_guardian for insert with check (public.has_permission('Estudiantes','edit'));
create policy "del_student_guardian_edit" on public.student_guardian for delete using (public.has_permission('Estudiantes','edit'));

create policy "ins_employee_edit" on public.employee for insert with check (public.has_permission('Personal','edit'));
create policy "upd_employee_edit" on public.employee for update using (public.has_permission('Personal','edit'));
create policy "del_employee_edit" on public.employee for delete using (public.has_permission('Personal','edit'));

create policy "ins_employee_task_edit" on public.employee_task for insert with check (public.has_permission('Tareas','edit'));
create policy "upd_employee_task_edit" on public.employee_task for update using (public.has_permission('Tareas','edit'));
create policy "del_employee_task_edit" on public.employee_task for delete using (public.has_permission('Tareas','edit'));

create policy "ins_attendance_edit" on public.attendance for insert with check (public.has_permission('Asistencia','edit'));
create policy "upd_attendance_edit" on public.attendance for update using (public.has_permission('Asistencia','edit'));
create policy "del_attendance_edit" on public.attendance for delete using (public.has_permission('Asistencia','edit'));

create policy "ins_student_payment_edit" on public.student_payment for insert with check (public.has_permission('Pagos','edit'));
create policy "upd_student_payment_edit" on public.student_payment for update using (public.has_permission('Pagos','edit'));
create policy "del_student_payment_edit" on public.student_payment for delete using (public.has_permission('Pagos','edit'));

create policy "ins_teacher_payment_auth" on public.teacher_payment for insert with check (auth.role() = 'authenticated');
create policy "upd_teacher_payment_auth" on public.teacher_payment for update using (auth.role() = 'authenticated');
create policy "del_teacher_payment_auth" on public.teacher_payment for delete using (auth.role() = 'authenticated');

create policy "ins_invoice_edit" on public.invoice for insert with check (public.has_permission('Facturación','edit'));
create policy "upd_invoice_edit" on public.invoice for update using (public.has_permission('Facturación','edit'));
create policy "del_invoice_edit" on public.invoice for delete using (public.has_permission('Facturación','edit'));

create policy "ins_activity_edit" on public.activity for insert with check (public.has_permission('Actividades','edit'));
create policy "upd_activity_edit" on public.activity for update using (public.has_permission('Actividades','edit'));
create policy "del_activity_edit" on public.activity for delete using (public.has_permission('Actividades','edit'));

create policy "ins_activity_media_edit" on public.activity_media for insert with check (public.has_permission('Actividades','edit'));
create policy "del_activity_media_edit" on public.activity_media for delete using (public.has_permission('Actividades','edit'));

create policy "ins_student_observation_auth" on public.student_observation for insert with check (auth.role() = 'authenticated');
create policy "upd_student_observation_auth" on public.student_observation for update using (auth.role() = 'authenticated');
create policy "del_student_observation_auth" on public.student_observation for delete using (auth.role() = 'authenticated');

create policy "ins_notification_auth" on public.notification for insert with check (auth.role() = 'authenticated');
create policy "upd_notification_auth" on public.notification for update using (auth.role() = 'authenticated');
create policy "del_notification_auth" on public.notification for delete using (auth.role() = 'authenticated');

create policy "ins_audit_log_any" on public.audit_log for insert with check (true);
create policy "ins_session_auth" on public.session for insert with check (auth.role() = 'authenticated');
