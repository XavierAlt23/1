## Kids – Gestión educativa (Supabase + Vite)

Este proyecto es una SPA ligera con HTML/JS (sin backend propio) y Supabase para:
- Autenticación, roles y permisos (RLS)
- CRUD de estudiantes, grados y tutores
- Personal y tareas
- Asistencia
- Pagos y facturación
- Actividades y multimedia (Storage)
- Observaciones
- Reportes (vistas SQL)
- Mensajería, notificaciones y auditoría

La navegación se hace con una pantalla de login (`index.html`) y un dashboard dinámico (`html/dashboard.html`).

### Requisitos
- Node.js 18+
- Una instancia de Supabase (URL + ANON KEY)

### Configuración
1) Instalar dependencias
```
npm install
```

2) Configurar variables públicas en `env.js`
- Copia `env.example.js` a `env.js` y coloca tus valores de Supabase (URL y anon key)

3) Crear el esquema en Supabase
- En la consola de SQL de Supabase, ejecuta en orden:
   - `supabase/schema.sql`
   - `supabase/policies.sql`

4) Semillas de permisos
- El `schema.sql` inserta permisos base (Dashboard, Usuarios y Roles, Estudiantes). Crea roles y asígnales permisos desde el módulo UI.

### Ejecutar en local
```
npm run dev
```
- Abre `http://localhost:3000` para ver el login. Tras iniciar sesión te lleva al dashboard.

### Despliegue
- Opción recomendada (Render con Docker): despliega frontend estático y endpoints PHP juntos.
   1) Ya incluimos `Dockerfile` y `render.yaml`.
   2) En Render, crea un servicio desde repo, selecciona "Blueprint" y apunta a `render.yaml`.
   3) Define variables de entorno en Render:
       - `SUPABASE_URL` = URL de tu proyecto Supabase
       - `SUPABASE_ANON_KEY` = anon key pública para el cliente
       - `SUPABASE_SERVICE_ROLE_KEY` = service role key (solo del lado servidor, usada por PHP)
   4) `env.js` en producción: deja `PHP_BASE_URL` vacío (`''`) para usar misma-origin con Apache.
   5) Render expondrá el puerto `$PORT` automáticamente; Apache corre en 80 dentro del contenedor.

- Alternativa (Vercel):
   - Si solo sirves el frontend, usa proyecto estático (framework: Vite). Build `vite build`, output `dist/`.
   - Para endpoints, migra PHP a funciones serverless (Node/Edge) o usa un micro-servicio aparte (Render) y ajusta `PHP_BASE_URL` a ese dominio.

### Módulos implementados
- Autenticación con Supabase (login, logout)
- Usuarios/Roles: gestión de roles y permisos (UI). Nota: la creación/listado de usuarios de `auth.users` requiere backend/Edge Function (no se expone en cliente). Se oculta la sección de “Usuarios” en la UI; puedes asignar permisos a roles y consultar el rol del usuario actual mediante RLS.
- Estudiantes: CRUD completo (crear, editar, archivar y eliminar)
- Placeholders listos: grados, tutores, staff, tareas, asistencia, pagos, facturas, actividades, observaciones, reportes, notificaciones y auditoría

### Notas importantes
- Gestión de usuarios (crear/buscar otros usuarios) no es posible desde el cliente sin exponer la service role key. Para esto, usa:
   - Panel de Supabase para crear usuarios, o
   - Supabase Edge Functions (Node) con la service role key segura y endpoints protegidos.
- RLS: El archivo `policies.sql` define políticas base de lectura para usuarios autenticados y escrituras simples por propietario. Ajusta según tus necesidades.

### Estructura relevante
- `index.html`: Login (JS: `index.js`)
- `html/dashboard.html`: Layout con sidebar dinámico (JS: `js/dashboard.js`, `js/dynamic-sidebar.js`)
- `html/users/users-roles.html` + `html/users/users-roles.js`: Roles y permisos
- `html/students.html` + `html/students.js`: CRUD de estudiantes
- `supabase/schema.sql`, `supabase/policies.sql`: Tablas y RLS
- `supabaseClient.js`: Cliente Supabase (toma credenciales de `env.js`)
- `js/ui.js`: utilidades (toasts, helpers)

### Próximos pasos sugeridos
- Agregar Edge Functions para:
   - Alta de usuarios (admin) y listado paginado
   - Reportes agregados (asistencia/finanzas) si exceden las capacidades de vistas
- Implementar módulos pendientes usando los endpoints de Supabase y Storage
- Añadir auditoría por triggers (INSERT en `audit_logs`) por tabla

