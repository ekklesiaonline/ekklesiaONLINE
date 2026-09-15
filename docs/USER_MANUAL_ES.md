# Manual de Usuario de EkklesiaRD

## 1. Proposito

EkklesiaRD es un sistema de gestion eclesial multinivel (ChMS) para:

- Supervision provincial
- Administracion diocesana
- Operacion a nivel de iglesia local
- Flujos de trabajo de ministerios

El sistema esta basado en roles. Cada usuario ve solo los modulos y datos permitidos por sus permisos.

## 2. Acceso e Inicio de Sesion

1. Abra la URL del sistema proporcionada por su administrador.
2. Inicie sesion con su correo y contrasena.
3. Si su cuenta esta en revision pendiente, espere la aprobacion diocesana.
4. Luego de la aprobacion, sera redirigido al dashboard de su rol.

## 3. Dashboards por Rol

### Administrador Provincial

Dashboard principal: Province Dashboard.
Objetivo principal:

- Ver resumenes de toda la red de diocesis
- Filtrar por diocesis y periodo
- Crear diocesis
- Asignar administradores diocesanos

Widgets tipicos:

- Total de diocesis e iglesias
- Total de personas y asistencia
- Promedios de asistencia por periodo (Hoy, Semana, Mes, Año, Domingos, Dias de semana)

### Administrador Diocesano

Dashboard principal: Diocesan Dashboard.
Objetivo principal:

- Supervisar todas las iglesias de su diocesis
- Revisar y moderar nuevos registros
- Acceder al dashboard financiero y de estado del sistema

Widgets tipicos:

- Iglesias bajo alcance
- Personas/miembros bajo alcance
- Promedios de asistencia
- Registros pendientes con acciones Aprobar, Descartar, Bloquear, Eliminar

### Roles a Nivel de Iglesia (Parish Admin, Pastor, Tesorero, Coordinador de Voluntarios, Miembro)

Dashboard principal: Parish Dashboard.
Objetivo principal:

- Operacion diaria y cuidado pastoral

Widgets tipicos:

- Total de personas, miembros y miembros activos
- Conteo de buen estado y liderazgo
- Seguimientos pendientes
- Resumen financiero mensual (ingresos, gastos, neto)
- Servicios recientes e invitados recientes

## 4. Menu Principal de Navegacion

La barra lateral depende de permisos. Los menus comunes incluyen:

- Dashboard
- Members o Staff
- Families
- Services
- Attendance
- Guests
- Guest Conversions
- Leadership
- Good Standing
- Follow-ups
- Finance:
  - Donations
  - Expenses
  - Budgets
  - Annual Pledges
- Volunteers
- Volunteer Schedules
- Events
- Worship Planning
- Reports
- Consistency Report
- Parishes
- Profile
- Logout

Nota: algunos roles ven menos opciones.

## 5. Flujos de Trabajo Principales

### 5.1 Gestion de Miembros

Use Members para:

- Buscar y ver registros de miembros
- Crear miembros nuevos (si tiene permiso)
- Editar perfiles y estatus
- Exportar datos (si tiene permiso)

Flujo recomendado:

1. Vaya a Members.
2. Busque por nombre/correo/telefono.
3. Abra el registro.
4. Actualice los campos necesarios.
5. Guarde y verifique cambios.

### 5.2 Gestion de Familias

Use Families para:

- Crear nucleos familiares
- Asignar jefe de hogar
- Agregar o quitar miembros de familia

Flujo recomendado:

1. Abra Families.
2. Cree la familia con nombre y direccion.
3. Agregue miembros y relaciones.
4. Guarde.

### 5.3 Servicios y Asistencia

Use Services para crear eventos liturgicos.
Use Attendance para marcar asistencia individual y comulgantes.

Flujo recomendado:

1. Cree un servicio en Services.
2. Abra Attendance para ese servicio.
3. Marque asistentes.
4. Revise reportes de asistencia.

### 5.4 Seguimiento de Invitados y Conversion

Use Guests y Follow-ups para gestionar visitantes.
Use Guest Conversions para convertir invitados elegibles a miembros.

Flujo recomendado:

1. Registre la visita del invitado.
2. Asigne una tarea de seguimiento.
3. Complete el seguimiento.
4. Convierta el invitado a miembro cuando corresponda.

### 5.5 Liderazgo y Buen Estado

Use Leadership para asignar o actualizar posiciones de liderazgo.
Use Good Standing para revisar estado y ejecutar refresco.

Flujo recomendado:

1. Abra Leadership y actualice asignaciones.
2. Abra Good Standing.
3. Ejecute Refresh.
4. Revise resultados de estado.

### 5.6 Operacion Financiera

El modulo Finance incluye Donations, Expenses, Budgets y Annual Pledges.

Flujo recomendado:

1. Registre donaciones.
2. Registre gastos.
3. Mantenga presupuestos.
4. Gestione promesas anuales.
5. Revise reportes y posicion neta.

### 5.7 Voluntarios y Eventos

Use Volunteers para equipos y asignaciones.
Use Volunteer Schedules para planificar responsabilidades por servicio.
Use Events para ciclo de vida de eventos y check-in.

Flujo recomendado:

1. Cree o actualice equipos.
2. Asigne miembros.
3. Arme calendarios.
4. Registre y haga check-in de asistentes.

## 6. Flujo de Moderacion de Registros (Diocesis)

Para administradores diocesanos:

1. Abra Diocesan Dashboard.
2. Revise New User Registrations Pending Review.
3. Seleccione rol.
4. Seleccione asignacion de iglesia cuando aplique.
5. Elija accion:
   - Approve
   - Dismiss
   - Ban
   - Delete

Buena practica:

- Aprobar solo despues de validar datos de perfil y alcance de iglesia.

## 7. Flujo de Supervision Provincial

Para administradores provinciales:

1. Abra Province Dashboard.
2. Seleccione alcance de diocesis (o All Dioceses).
3. Seleccione filtro de periodo.
4. Revise resumenes de asistencia y membresia.
5. Cree diocesis o asigne administradores diocesanos segun necesidad.

## 8. Flujo del Dashboard Financiero (Diocesis)

1. Abra Diocesan Finance Dashboard.
2. Elija alcance de iglesia (All Parishes o una iglesia).
3. Elija periodo (today/week/month/year/custom).
4. Revise ingresos, gastos y neto.
5. Analice comparativos por iglesia y tendencias mensuales.

## 9. Reportes y Calidad de Datos

### Reportes

Use Reports para resumenes operativos y financieros segun su rol.

### Consistency Report

Los roles administrativos pueden usar Consistency Report para detectar inconsistencias y exportar CSV.

## 10. Perfil y Cuenta

Use Profile para:

- Actualizar datos personales
- Completar datos faltantes de perfil
- Mantener informacion de contacto

Si el perfil esta incompleto, completelo antes de usar flujos avanzados.

## 11. Reglas de Permisos y Visibilidad

- Los permisos definen acciones permitidas.
- Usuarios de iglesia estan limitados a su iglesia.
- Administradores diocesanos estan limitados a su diocesis.
- Administradores provinciales ven resumenes provinciales y no CRUD/finanzas irrestrictas salvo permiso explicito.

## 12. Solucion de Problemas

### No veo una opcion de menu

Causa: permiso faltante o rol incorrecto.
Accion: contacte a un administrador para validar rol y permisos.

### El sistema me redirige fuera de una pagina

Causa: restriccion de alcance o permiso.
Accion: confirme su asignacion de iglesia/diocesis y su rol.

### El dashboard aparece vacio

Causa: no hay registros en el alcance/periodo seleccionado.
Accion: ajuste filtros de fecha y verifique la carga de datos en modulos origen.

### Mi registro sigue pendiente

Causa: moderacion diocesana incompleta.
Accion: solicite revision a administracion diocesana.

## 13. Rutina Operativa Recomendada

Diario:

- Revisar tarjetas del dashboard
- Procesar seguimientos
- Registrar asistencia y finanzas

Semanal:

- Revisar conversiones y buen estado
- Revisar horarios de voluntarios y eventos

Mensual:

- Conciliar donaciones y gastos
- Validar presupuestos y promesas
- Exportar reportes para liderazgo

## 14. Guia de Captura de Pantallas

Actualmente no hay capturas incluidas en el repositorio.

Para construir un anexo visual de este manual:

1. Inicie sesion con cada rol.
2. Capture:
   - Province Dashboard
   - Diocesan Dashboard
   - Parish Dashboard
   - Finance Dashboard
   - Members, Guests, Good Standing, Pledges, Volunteers
3. Use este patron de nombres:
   - screenshot-province-dashboard.png
   - screenshot-diocesan-dashboard.png
   - screenshot-parish-dashboard.png
   - screenshot-finance-dashboard.png
4. Guarde en docs/images y referencie en este manual.

## 15. Inicio Rapido por Rol

### Province Admin

- Abrir Province Dashboard
- Definir filtros de diocesis/periodo
- Revisar tendencias provinciales
- Crear diocesis y asignar administradores diocesanos

### Diocesan Admin

- Abrir Diocesan Dashboard
- Moderar registros pendientes
- Gestionar iglesias y herramientas diocesanas
- Usar finanzas y estado del sistema

### Parish Admin

- Gestionar miembros, familias, servicios, asistencia, invitados, finanzas y voluntarios
- Monitorear consistencia y reportes

### Pastor

- Enfoque en miembros, asistencia, seguimientos, eventos y worship planning

### Treasurer

- Enfoque en donaciones, gastos, presupuestos, promesas y reportes

### Volunteer Coordinator

- Enfoque en equipos, asignaciones, horarios y operaciones de eventos

### Member

- Mantener su perfil y usar las funciones permitidas

---
Version del manual: 1.0 (Espanol)
Ultima actualizacion: 2026-03-26
