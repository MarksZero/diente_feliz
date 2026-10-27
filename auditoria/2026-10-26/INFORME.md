# Auditoría de Diente Feliz

Revisión iniciada el 26 de octubre de 2026 y completada durante la madrugada del 27, hora de Chile. Alcance: código Laravel guardado en `diente-feliz`, configuración local, migraciones, seeders, pruebas y operaciones HTTP seleccionadas. No se modificó la lógica de la aplicación para esta revisión. Se añadieron documentación y comprobaciones en esta carpeta.

## Resultado

**RECHAZADO para entrega final.** La aplicación inicia y varias consultas funcionan, pero crear una cita puede devolver un error 500 y revertir la operación. La agenda no identifica a un mismo profesional entre sedes. La delegación de permisos de usuarios permite otorgar privilegios superiores. La suite completa tampoco permite validar el sistema de forma repetible.

No se confirmó un hallazgo CRÍTICO dentro del alcance realizado. Sí hay problemas ALTOS que bloquean la aprobación. La ausencia de hallazgos críticos no constituye una certificación de seguridad.

## Verificación realizada

| Comprobación | Resultado observado |
|---|---|
| Sintaxis de PHP propio | 30 archivos de `app`, `routes`, `database` y `tests` sin errores de sintaxis |
| Compilación de vistas | `artisan view:cache` terminó correctamente |
| Migraciones de la base demo | Las cuatro migraciones figuran ejecutadas |
| Suite existente completa, sin corregirla | 21 pruebas: 4 aprobadas, 17 errores, 67 aserciones |
| Las 18 pruebas funcionales ejecutadas individualmente | 13 aprobadas, 5 fallidas |
| Comprobación adicional de caracterización | 1 prueba aprobada, 15 aserciones; reproduce defectos, no demuestra que estén corregidos |
| Composer audit sobre `composer.lock` | Sin avisos ni paquetes abandonados reportados en la consulta |
| HTTP real, login y logout | Correctos con la cuenta demo |
| HTTP real sin token CSRF | Login rechazado con 419 |
| HTTP real de pacientes, ficha avanzada, reportes, ayuda y CSS | Respuestas 200 |
| Cabeceras observadas | CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` |

Las pruebas de base de datos se ejecutaron contra **diente_feliz_test**, separada de la demo. Las comprobaciones HTTP usaron la demo: generaron actividad de sesión y auditoría, sin modificar pacientes. PostgreSQL recuperó automáticamente el clúster tras el apagado previo. Esto no equivale a verificar una restauración de respaldos.

El entorno aislado bloqueó inicialmente conexiones y lecturas de PHP; esas ejecuciones no se contabilizan como defectos del producto. Se repitieron las comprobaciones con acceso autorizado al entorno local.

## Auditoría A: funcionamiento y seguridad

### A1 — ALTO — Un modelo dinámico descarta campos al escribir en distintas tablas

**Estado:** confirmado mediante prueba funcional.

**Ubicación:** `diente-feliz/app/Models/Record.php:6`, `:10`; `diente-feliz/app/Services/Clinic.php:119`. Evidencia del framework: `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/GuardsAttributes.php`, método `isGuardableColumn`.

`Record` cambia su tabla con `setTable`, pero mantiene `$guarded = ['id']`. Eloquent memoriza las columnas permitidas por **clase de modelo**, no por tabla. Al guardar una cita y luego su notificación en la misma petición, se reutilizan las columnas de `appointments` para `messages`: `subject` y `body` se descartan.

**Evidencia:** `test_appointments_validate_duration_availability_and_overlaps` devuelve 500; PostgreSQL rechaza un `subject` nulo. La transacción revierte también la cita.

En las pruebas con varias peticiones dentro del mismo proceso también fallan la devolución posterior a un pago (`payment_id` descartado) y el aumento de versión de una plantilla después de emitir un consentimiento. Estos dos casos muestran contaminación entre peticiones de prueba; no demuestran por sí solos que esos flujos fallen entre peticiones PHP independientes. El fallo de cita y notificación sí ocurre dentro de una sola petición.

**Consecuencia:** agenda bloqueada y comportamiento dependiente del orden de escrituras. En procesos persistentes el alcance potencial es mayor.

**Corrección:** usar modelos de tabla fija con listas explícitas de atributos asignables. Como alternativa acotada, definir asignación explícita por entidad sin depender de la caché de columnas de la clase compartida. No eliminar globalmente la protección de asignación masiva. Añadir una prueba que guarde cita y notificación en una misma transacción.

### A2 — ALTO — La suite falla por identificadores fijos en el seeder

**Estado:** confirmado.

**Ubicación:** `diente-feliz/database/seeders/ClinicSeeder.php:23`, `:24`, `:28`; `diente-feliz/tests/Feature/ClinicSecurityTest.php:11`, `:12`.

Se crean sucursales y usuarios, pero después se usan IDs literales (`1`, `2`, `3`) para enlazarlos. PostgreSQL no revierte las secuencias con el rollback de cada prueba. Tras el primer caso, las nuevas sucursales ya no tienen ID 1 y el seeder inserta una asociación inválida.

**Evidencia:** 17 errores `23503`, clave foránea `branch_user_branch_id_foreign`, durante la preparación de pruebas. La mayoría de las verificaciones de seguridad ni siquiera llega a ejecutar su cuerpo en la suite completa.

**Consecuencia:** no hay una señal fiable de regresiones. Reejecutar el seeder sobre una base parcialmente poblada también puede producir estados incompletos, porque abandona el trabajo cuando existe cualquier usuario.

**Corrección:** relacionar usando los objetos creados y consultar usuarios por identificadores estables en los tests. Hacer la carga demo transaccional, con una política clara de ejecución sobre bases no vacías. No “arreglar” la suite desactivando claves foráneas.

### A3 — ALTO — Gestionar usuarios permite conceder permisos superiores

**Estado:** confirmado para roles delegados personalizados.

**Ubicación:** `diente-feliz/app/Http/Controllers/AdminController.php:13`, `:14`, `:25`.

`saveUser` exige solamente `users.write`. Las listas de roles y sucursales se validan por existencia, sin comprobar qué roles puede conceder el actor ni qué sedes puede administrar. Una cuenta con únicamente `users.write` pudo crear otra cuenta con el rol base de administrador y acceso a ambas sedes.

**Evidencia:** reproducción en `AuditProbeTest.php`, con usuario delegado limitado a una sede; la cuenta creada obtiene `roles.write`.

**Consecuencia:** si se delega la gestión de cuentas, esa delegación equivale en la práctica a acceso administrativo total. No se observó una vía de escalada para la recepcionista demo sin ese permiso.

**Corrección:** separar “editar datos de usuario” de “conceder roles/accesos”, reservar la segunda acción al administrador general y restringir el ámbito de sucursales. Validar la jerarquía de concesión en el servidor, no solo ocultar opciones.

### A4 — ALTO — El mismo dentista puede tener citas simultáneas en dos sedes

**Estado:** confirmado en la regla de agenda y en la restricción de base de datos.

**Ubicación:** `diente-feliz/app/Services/Clinic.php:128`, `:134`; `diente-feliz/database/migrations/2026_10_20_000001_create_clinic.php:21`, `:47`.

Se admite una fila de profesional por combinación `branch_id/user_id`. Tanto la exclusión SQL como la consulta de solapamiento usan `professional_id`. Dos filas del mismo usuario en sucursales distintas se interpretan como personas distintas.

**Evidencia:** con otro paciente y otro box en Norte, la regla `schedule` aceptó el mismo intervalo que una cita en Centro del mismo dentista. La inserción directa controlada en la base de pruebas también fue aceptada. Se probó la regla de forma aislada porque A1 bloquea la escritura HTTP completa de citas.

**Consecuencia:** después de reparar A1, seguiría siendo posible reservar a la misma persona en dos lugares a la vez.

**Corrección:** separar la identidad profesional de sus habilitaciones por sede, o comprobar y bloquear por una identidad común del profesional. Aplicar la exclusión también a esa identidad para cubrir concurrencia.

### A5 — ALTO — La aplicación usa el superusuario de PostgreSQL

**Estado:** configuración confirmada; no se intentó explotar el servidor.

**Ubicación:** `configure-local.ps1:21`; configuración local existente.

La conexión web usa `postgres`. Las operaciones de aplicación, migración y administración comparten privilegios. La auditoría existe como tabla modificable por esa cuenta; el cifrado de sus valores no la convierte en un registro inalterable.

**Consecuencia:** una vulnerabilidad que permita ejecutar consultas arbitrarias o comprometer el proceso tendría un alcance mayor al necesario. Esto no demuestra que exista SQLi en los controladores revisados.

**Corrección:** separar cuenta de migraciones y cuenta de ejecución; esta última sin privilegios de superusuario ni creación de esquemas. Para auditoría, restringir actualización/eliminación y definir conservación y respaldos verificables.

### A6 — MEDIO — Una suspensión futura de la cuenta no bloquea la cita futura

**Estado:** confirmado en la regla de agenda.

**Ubicación:** `diente-feliz/app/Services/Clinic.php:129`, `:130`; `diente-feliz/app/Models/User.php:20`.

Para la cuenta se consulta `enabled()`, que evalúa el momento actual. Solo la suspensión del registro profesional se compara con el intervalo de la cita.

**Evidencia:** se programó en la regla de agenda una cita de mañana, aunque la cuenta del dentista está suspendida desde mañana por tres días.

**Corrección:** evaluar ambos intervalos, cuenta y habilitación profesional, contra el inicio y fin de la reserva. Conservar `enabled()` para controlar el acceso actual.

### A7 — MEDIO — Cambiar la sede de un recurso deja asociaciones incoherentes

**Estado:** confirmado para box.

**Ubicación:** `diente-feliz/app/Services/Clinic.php:54`, `:109`; `diente-feliz/app/Services/Modules.php:33`.

Se permite editar el `branch_id` de un box existente sin revisar sus citas y disponibilidades. La prueba movió el Box 1 a Norte y quedaron citas de Centro que aún lo referencian.

**Consecuencia:** historia operativa y recursos pertenecen a sedes distintas. Las claves foráneas garantizan existencia, pero no esa coherencia.

**Corrección:** impedir el traslado cuando haya asociaciones, o diseñar una operación de traslado con vigencia temporal. Revisar la misma restricción para profesionales y disponibilidades.

### A8 — MEDIO — Omitir campos opcionales en usuarios causa un error 500

**Estado:** confirmado por prueba HTTP de integración.

**Ubicación:** `diente-feliz/app/Http/Controllers/AdminController.php:14`, `:21`, `:26`.

`disabled_from` y `disabled_until` son opcionales, pero después se accede a sus claves sin comprobar si llegaron. Un POST válido que omite esas claves produce `Undefined array key "disabled_from"` y revierte el alta. El formulario habitual envía los campos vacíos y puede no mostrar el defecto.

**Corrección:** normalizar esos valores a `null` después de validar, o usar una solicitud tipada que garantice su presencia. Añadir casos de campos omitidos, vacíos y fechas inválidas.

### A9 — MEDIO — Se marcan correos como enviados sin quedar registrados localmente

**Estado:** confirmado por la configuración y el transporte instalado; no se envió correo externo.

**Ubicación:** `configure-local.ps1:32`, `:36`; `diente-feliz/app/Console/Commands/SendClinicMessages.php:17`, `:18`; `vendor/laravel/framework/src/Illuminate/Mail/Transport/LogTransport.php`, método `send`.

El modo local usa `MAIL_MAILER=log`, pero `LOG_LEVEL=warning`. El transporte escribe con nivel `debug`: el mensaje se filtra y luego se marca como `Enviado`.

**Consecuencia:** el operador ve una confirmación sin entrega externa ni evidencia en el registro local. Con SMTP, el envío dentro de una transacción reintentable también plantea un riesgo de duplicación si se envía y el commit falla; ese segundo caso no fue reproducido.

**Corrección:** usar un buzón de captura local o un canal separado para correo, distinguir “capturado” de “enviado” y procesar una bandeja de salida con idempotencia fuera de transacciones reintentables.

### A10 — MEDIO — La configuración y el ejemplo no reproducen el proyecto

**Estado:** confirmado por inspección.

**Ubicación:** `diente-feliz/.env.example:4`, `:23`, `:32`; `configure-local.ps1:5`; `diente-feliz/README.md:1`.

El ejemplo mantiene SQLite, depuración activa y sesiones sin cifrar, mientras las migraciones usan PostgreSQL, `tstzrange` y `btree_gist`. El README es el inicial de Laravel. El configurador depende de archivos locales preexistentes y reemplaza `.env` y `.env.testing`.

**Consecuencia:** copiar el proyecto y seguir la documentación original no produce el entorno solicitado. Reejecutar el configurador puede sobrescribir ajustes personales.

**Corrección:** entregar un `.env.example` de PostgreSQL sin secretos, instrucciones de instalación y un arranque idempotente que preserve configuraciones existentes. Esta auditoría añade un tutorial para la copia actual, pero no corrige el instalador.

### A11 — MEDIO — Zona horaria UTC en una interfaz sin conversión explícita

**Estado:** confirmado por configuración; impacto inferido de los flujos de fecha.

**Ubicación:** `diente-feliz/config/app.php:68`; `diente-feliz/app/Services/Clinic.php:125`; `diente-feliz/app/Http/Controllers/ClinicController.php:11`.

Los controles `datetime-local` no incluyen zona. Laravel interpreta las entradas con UTC y usa esa misma zona para “hoy”; la clínica opera en Chile. No hay conversión explícita entre entrada local, almacenamiento y presentación.

**Consecuencia:** citas, suspensiones y límites diarios pueden desplazarse respecto de la hora local, especialmente cerca de medianoche. Una hora que se ve igual en pantalla no demuestra que el instante almacenado sea correcto.

**Corrección:** definir la zona de la clínica (`America/Santiago`), convertir instantes de forma explícita y probar cambios de día y horario de verano. No modificar retrospectivamente las fechas sin evaluar su interpretación anterior.

### A12 — MEDIO — La interfaz ofrece 10 MB, pero PHP acepta 2 MB por archivo

**Estado:** configuración confirmada; no se hizo una carga HTTP real de 10 MB.

**Ubicación:** `.runtime/php/php.ini:697`, `:849`; `diente-feliz/app/Services/Modules.php:22` (módulo documents; buscar `max:10240` si cambia el archivo).

El formulario y Laravel permiten 10 MB, pero PHP conserva `upload_max_filesize=2M` y `post_max_size=8M`. Las cargas se rechazan antes de llegar a la validación prevista. Los archivos simulados de PHPUnit no cubren los límites del servidor HTTP.

**Corrección:** alinear PHP, la aplicación y cualquier servidor frontal. Comprobar tamaño real, MIME y errores de subida por HTTP. En la copia actual usar archivos inferiores a 2 MB.

### A13 — MEDIO — Exportaciones incompletas respecto de los datos financieros

**Estado:** confirmado por código.

**Ubicación:** `diente-feliz/app/Http/Controllers/ClinicController.php:52`, `:64`; `diente-feliz/resources/views/export.blade.php:1`.

Los formatos disponibles son CSV, HTML y ZIP. No hay generación nativa de XLSX ni PDF: PDF depende de imprimir desde el navegador. Los campos exportados se derivan de los campos de entrada del formulario; en presupuestos faltan `total`, `discount_percent` y saldo, y en consentimientos faltan la huella y la fecha de firma.

**Consecuencia:** la exportación no reproduce el contenido que el usuario ve ni constituye una copia completa del documento emitido. La descarga de pacientes tampoco equivale a una descarga masiva de toda su historia clínica.

**Corrección:** definir esquemas de exportación separados de los formularios y agregar los datos calculados/documentales. Etiquetar los formatos con precisión. Probar los archivos producidos, permisos, límites y selección de filas.

## Auditoría B: calidad y arquitectura

### B1 — MEDIO — El servicio central concentra demasiadas responsabilidades

**Ubicación:** `diente-feliz/app/Services/Clinic.php:44`, `:53`; `diente-feliz/app/Services/Modules.php:5`.

`save()` reúne validación, permisos, relaciones, agenda, pagos, devoluciones, archivos, consentimientos y mensajes en una sola transacción con condiciones por nombre de módulo. La misma matriz de posiciones describe etiquetas, controles, reglas y relaciones.

**Evidencia:** A1 ya demuestra un fallo de la abstracción compartida; incorporar cambios de un módulo exige razonar sobre el servicio completo. La compresión de muchos bloques en una línea dificulta revisar ramas y asignaciones.

**Consecuencia:** mayor probabilidad de regresión y pruebas difíciles de aislar.

**Corrección:** primero reparar el modelo, después extraer gradualmente agenda, finanzas y documentos a operaciones concretas con solicitudes validadas. Mantener metadatos comunes solo para presentación. No se recomienda reescribir todo el proyecto durante la auditoría.

### B2 — MEDIO — Consultas repetidas y selectores con corte silencioso

**Ubicación:** `diente-feliz/app/Services/Clinic.php:40`, `:139`; `diente-feliz/resources/views/dashboard.blade.php:7`; `diente-feliz/resources/views/reports.blade.php:4`; `diente-feliz/app/Http/Controllers/ClinicController.php:73`.

El dashboard consulta pacientes desde un bucle de vista. El saldo ejecuta dos consultas por presupuesto. Reportes recupera todos los presupuestos del período sin paginación. Los selectores cargan hasta 1.000 entidades sin búsqueda remota; los posteriores desaparecen del selector.

**Consecuencia:** crecimiento de consultas y memoria; ciertos registros dejan de ser seleccionables. No se midió carga de producción.

**Corrección:** precargar relaciones, calcular agregados por lote, paginar reportes y usar selectores buscables con autorización en servidor. Mantener consultas fuera de las vistas.

### B3 — MEDIO — Los tests mezclan lógica de producto y estado persistente del harness

**Ubicación:** `diente-feliz/tests/Feature/ClinicSecurityTest.php:12`, `:40`, `:84`; framework `PreventRequestForgery.php:99`.

Además de A2, las pruebas HTTP encadenadas conservan estado de la misma clase `Record`. La prueba de revocación incrementa una instancia distinta de la autenticada mediante `actingAs`, por lo que la petición reutiliza un objeto de usuario desactualizado. El fallo 200 de ese test no demuestra que una sesión real sobreviva a la revocación.

La protección CSRF se omite por defecto en el modo unitario de Laravel. Las pruebas existentes no activan explícitamente esa protección; por eso se complementó con HTTP real, donde sí se obtuvo 419.

**Corrección:** fixtures sin IDs fijos, separar procesos solo cuando sea necesario y simular sesiones recargadas realmente. Añadir pruebas HTTP de revocación, CSRF, límites de archivos y concurrencia. Mantener separados tests de caracterización y tests de aceptación.

## Controles que sí tienen evidencia favorable

Las ejecuciones individuales aprobaron la denegación de acceso clínico para recepción, aislamiento básico de pacientes entre sedes, bloqueo de login, cifrado y escape del texto clínico probado, filtrado parametrizado del ejemplo de SQLi, control de comentarios, rechazo de solapamiento directo del mismo ID profesional, validación de paciente en pagos, rechazo de los archivos simulados no permitidos y neutralización del caso de fórmula CSV probado. No generalizar un payload aprobado a todas las variantes del ataque.

Los archivos se guardan fuera de `public` y la descarga aplica autorización. Las contraseñas usan el hash de Laravel. Las rutas de escritura incluyen autenticación, verificación de cuenta y límites de frecuencia. La aplicación tiene una base útil; los problemas encontrados impiden considerarla terminada.

## Pendientes de verificación

No se realizó pentest externo, fuzzing extensivo, prueba concurrente de múltiples procesos, análisis antivirus, recuperación desde respaldo, inspección visual en varios navegadores, auditoría de accesibilidad completa ni revisión de cumplimiento legal. Tampoco se validó entrega SMTP real. Las cabeceras y CSRF se comprobaron en rutas seleccionadas, no en cada respuesta posible.

## Orden de corrección

1. Reparar `Record` y hacer que la suite completa prepare sus datos correctamente.
2. Restringir concesión de roles y separar la cuenta de base de datos.
3. Corregir identidad profesional entre sedes, suspensiones y cambios de recursos.
4. Resolver campos opcionales, correo local, zona horaria y límites de PHP.
5. Completar exportaciones y configuración reproducible; después revisar rendimiento y experiencia de uso.

## Evidencia y reproducción

Desde la carpeta raíz del proyecto, con PostgreSQL local iniciado y `.env.testing` apuntando exclusivamente a `diente_feliz_test`:

```powershell
& .\.runtime\php\php.exe .\diente-feliz\vendor\phpunit\phpunit\phpunit --configuration .\diente-feliz\phpunit.xml
.\auditoria\2026-10-26\run-isolated-tests.ps1
& .\.runtime\php\php.exe .\diente-feliz\vendor\phpunit\phpunit\phpunit --configuration .\diente-feliz\phpunit.xml .\auditoria\2026-10-26\AuditProbeTest.php
```

Las pruebas usan migraciones y pueden reconstruir la base configurada para testing. Nunca apuntarlas a una base que se quiera conservar. La prueba de caracterización **espera los defectos actuales**; después de repararlos debe convertirse en una prueba que exija el comportamiento correcto.

Salidas: `suite-original.txt`, `suite-original.xml`, `dependencies.json`, `test_*.txt`, `probes.txt` y `http-smoke.json` en esta carpeta. La aprobación de dependencias significa únicamente que Composer no reportó avisos en la consulta realizada.

**Veredicto: RECHAZADO.** Verificación estática, pruebas contra PostgreSQL y comprobaciones HTTP locales. Hay fallos reproducidos que bloquean operaciones y límites de autorización que deben corregirse antes de integrar como entrega final.

