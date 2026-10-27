# Diente Feliz: arranque local y uso del código

Actualizado el 27 de octubre de 2026. Describe la copia que existe en este equipo, no una versión terminada. La auditoría y las pruebas están en `auditoria/2026-10-26/INFORME.md`.

El sistema permite iniciar sesión y consultar la información demo. La creación de citas tiene un fallo confirmado y la suite completa aún no aprueba. No uses este tutorial como evidencia de que todos los módulos funcionan correctamente.

## 1. Qué contiene el proyecto

La carpeta principal es:

```text
C:\Users\pablo\.codex\.chatgpt-projects\g-p-6ad4feff27a4819194dbd53f0a39898e
```

| Ruta | Contenido |
|---|---|
| `diente-feliz/` | Aplicación Laravel |
| `.runtime/php/` | PHP portátil 8.4.26 y su `php.ini` |
| `.runtime/composer.phar` | Gestor de dependencias Composer |
| `.runtime/pgsql/` | Herramientas PostgreSQL 17.11 |
| `.runtime/pgdata/` | Base de datos local existente; no borrarla |
| `diente-feliz/.env` | Configuración y claves de la demo |
| `diente-feliz/.env.testing` | Configuración de la base de pruebas separada |
| `auditoria/2026-10-26/` | Informe, comprobaciones y resultados |

La interfaz usa vistas Blade y archivos propios `public/clinic.css` y `public/clinic.js`. No necesita Node ni `npm run dev`. El 27 de octubre se retiraron los archivos de Vite/Tailwind sin uso y se sustituyó el README inicial de Laravel por instrucciones de esta aplicación. La copia separada para AWS está en `diente-feliz-aws`; no comparte su configuración con la versión local.

## 2. Abrir la copia que ya está instalada

Abre PowerShell. Todos los comandos de esta sección se ejecutan desde la carpeta principal, no desde `diente-feliz`:

```powershell
Set-Location 'C:\Users\pablo\.codex\.chatgpt-projects\g-p-6ad4feff27a4819194dbd53f0a39898e'
& .\.runtime\php\php.exe -v
& .\.runtime\pgsql\bin\pg_isready.exe -h 127.0.0.1 -p 55432
```

Si la última línea dice `accepting connections`, PostgreSQL ya está disponible. Si dice `no response`, inícialo:

```powershell
& .\.runtime\pgsql\bin\pg_ctl.exe -D .\.runtime\pgdata -l .\.runtime\postgres.log -o '-h 127.0.0.1 -p 55432' start
```

Después de un corte puede aparecer `automatic recovery in progress`. Espera a que termine y confirma nuevamente con `pg_isready`. No borres `pgdata` ni archivos de bloqueo para intentar repararlo.

Comprueba la aplicación:

```powershell
& .\.runtime\php\php.exe .\diente-feliz\artisan migrate:status
& .\.runtime\php\php.exe .\diente-feliz\artisan view:cache
```

En esta copia las cuatro migraciones ya están aplicadas. No necesitas cargar los datos demo otra vez. **No ejecutes `migrate:fresh`, `db:wipe` ni regeneres `APP_KEY` sobre esta base.** La clave existente permite descifrar los contactos, antecedentes y registros que ya se guardaron.

Inicia el servidor web:

```powershell
& .\.runtime\php\php.exe .\diente-feliz\artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Mantén abierta esa terminal. En el navegador abre [http://127.0.0.1:8000](http://127.0.0.1:8000). Usa siempre la misma dirección durante la sesión, sin alternar con `localhost`.

El servidor de desarrollo se limita a este equipo. No es un despliegue de producción ni requiere abrir puertos del router.

### Cómo detenerlo

Pulsa **Ctrl+C** en la terminal del servidor web. Para apagar también PostgreSQL de este proyecto, desde otra terminal situada en la carpeta principal:

```powershell
& .\.runtime\pgsql\bin\pg_ctl.exe -D .\.runtime\pgdata status
& .\.runtime\pgsql\bin\pg_ctl.exe -D .\.runtime\pgdata -m fast stop
```

Esto cierra conexiones y guarda el estado; no elimina los datos. Usa siempre la ruta del clúster de este proyecto.

## 3. Cuentas demo

La contraseña inicial configurada para estas cuentas es **`Demo-DienteFeliz!2026`**. El ingreso del administrador fue comprobado durante la auditoría. Si posteriormente cambias una contraseña, prevalece el valor guardado en la base; modificar `DEMO_PASSWORD` no cambia las cuentas existentes.

| Correo | Rol inicial | Sucursales |
|---|---|---|
| `admin@dientefeliz.test` | Administrador general | Centro y Norte |
| `recepcion@dientefeliz.test` | Recepcionista | Centro |
| `dentista@dientefeliz.test` | Dentista | Centro |
| `encargado@dientefeliz.test` | Encargado y recepcionista | Norte |

Son credenciales de demostración, conocidas y compartidas. Los pacientes y contactos también son ficticios. No introduzcas datos reales. Desde **Mi cuenta** puedes cambiar la contraseña: mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos.

Cinco intentos fallidos para una cuenta activan un bloqueo temporal de 15 minutos. El mensaje de acceso no distingue entre cuenta inexistente, contraseña incorrecta o cuenta inhabilitada.

## 4. Recorrido recomendado para conocer la aplicación

### Inicio y navegación

Después de ingresar verás indicadores de citas, pacientes y seguimientos. No se muestran ingresos del día. El menú lateral cambia según los permisos.

Las citas del seeder corresponden al día en que se cargaron originalmente, no al día en que abres el sistema. Por eso el dashboard puede mostrar cero citas hoy aunque existan citas históricas. Consulta **Agenda y citas** y filtra por fechas; no reconstruyas la base para refrescar el dashboard.

### Pacientes y ficha clínica

1. Entra a **Pacientes** y abre un registro existente, por ejemplo Sofía Ejemplo.
2. La ficha simple reúne datos generales, últimas atenciones, presupuestos y seguimiento.
3. La ficha avanzada añade secciones de comentarios, documentos, consentimientos y movimientos financieros, según los permisos de la cuenta.
4. Para registrar un paciente, usa **Nuevo registro**, un identificador único como `DEMO-0099`, fecha de nacimiento, datos ficticios y al menos una sucursal.
5. Activa comunicaciones únicamente si deseas simular que el paciente dio autorización.

El identificador de esta versión es un código demo; no hay validación de dígito verificador de RUT chileno. Al editar sucursales de un paciente se agregan asociaciones; quitar una casilla no elimina el vínculo ya existente.

La cuenta de recepción no puede abrir atenciones clínicas, odontograma ni archivos clínicos. La cuenta de dentista tiene acceso clínico en sus sedes asignadas. El control implementado es por sede y permiso; no hay una asignación individual de cada paciente a su dentista como condición adicional de acceso.

### Atenciones y comentarios

Con la cuenta de dentista, abre una ficha y agrega una atención con diagnóstico, evolución, alergias, medicamentos y antecedentes. Las atenciones son registros históricos: para corregir información se añade una evolución, no se borra la anterior.

En **Comentarios**, selecciona visibilidad clínica o administrativa. El servidor exige el permiso del tipo seleccionado. La recepción demo tiene comentarios administrativos; la cuenta de dentista tiene clínicos.

### Odontograma

Desde la ficha, pulsa **Registrar hallazgo**. Selecciona sucursal, paciente, pieza FDI, superficie y estado.

El gráfico incluye dentición permanente y temporal. Gris significa **sin registro**, no “sano”. Verde indica que existe registro y coral señala caries. Los hallazgos vigentes se calculan por pieza y superficie; consulta el detalle y el historial, no solo el color.

### Sucursales, profesionales, box y disponibilidad

Para preparar una atención, el orden previsto es:

1. Crear o revisar la sucursal.
2. Asignar el usuario del profesional a esa sucursal.
3. Registrar al profesional y un box habilitado.
4. Crear una disponibilidad con inicio y fin, profesional y box.
5. Asociar al paciente a esa sede.

Por ahora no traslades un box existente a otra sucursal si ya tiene citas: la auditoría confirmó que el sistema deja asociaciones incoherentes. Tampoco delegues `users.write` en un rol limitado: ese permiso actualmente permite conceder roles superiores.

### Agenda: flujo previsto y bloqueo actual

El formulario de cita pide paciente, sede, profesional, box, inicio, fin, motivo y estado. La duración debe ser de 30, 45 o 60 minutos y quedar dentro de una disponibilidad.

**La creación de citas está bloqueada por un defecto confirmado.** Una reserva válida puede devolver 500 al generar su notificación. La transacción se revierte: no supongas que quedó guardada. Consulta las citas existentes para explorar la interfaz. La edición de citas que cambia hora o estado puede activar la misma ruta problemática.

Aunque se repare ese error, faltan correcciones para evitar solapar al mismo dentista entre sedes y para respetar suspensiones futuras de la cuenta. Estas limitaciones están detalladas como A1, A4 y A6 en el informe.

### Radiografías y documentos

Con permisos clínicos, abre **Radiografías y archivos**, elige paciente y sede, agrega un nombre y selecciona PDF, PNG o JPEG.

La aplicación anuncia 10 MB, pero el PHP portátil actual limita cada archivo a **2 MB**. Para explorar esta copia utiliza archivos inferiores a 2 MB. Se rechazan formatos no permitidos y las descargas pasan por autorización; los archivos no se guardan en una carpeta pública.

No crees enlaces públicos hacia `storage/app/private`. La validación MIME no equivale a análisis antivirus.

### Consentimientos y plantillas

En **Plantillas editables** se prepara el texto. En **Consentimientos**, selecciona paciente, sede y plantilla; despliega las plantillas disponibles y pulsa **Usar este texto**. Completa y revisa el contenido antes de registrar el nombre del firmante y confirmar consentimiento presencial.

El documento emitido conserva su texto y una huella SHA-256. El registro de un nombre y una casilla no implementa firma electrónica avanzada ni captura una firma manuscrita.

Las pruebas encadenadas de versiones de plantilla fallan por el modelo compartido. No se ha certificado el ciclo completo de edición, emisión y versionado.

### Presupuestos, abonos y devoluciones

1. Abre **Presupuestos** y revisa un presupuesto demo.
2. El monto se expresa en CLP enteros. El formulario recibe descripción libre de prestaciones y monto bruto; no hay un catálogo de ítems con cantidades y precios calculados por línea.
3. Al crear un presupuesto desde la aplicación, se aplica el convenio activo y vigente del paciente. Los datos iniciales del seeder no reproducen necesariamente ese cálculo: hay presupuestos demo con descuento cero.
4. El presupuesto debe estar **Aceptado** antes de recibir un abono.
5. En el detalle, pulsa **Registrar abono**, verifica paciente/sede y escribe un monto que no supere el saldo.
6. Desde el detalle del pago puedes abrir **Emitir devolución**. El importe no debe superar lo disponible del pago original.

Pagos y devoluciones son inmutables desde la interfaz. Un presupuesto con pagos no se puede editar. La prueba encadenada pago→devolución falla por el modelo compartido; no se debe presentar ese flujo como validado de principio a fin.

Las devoluciones vuelven a aumentar el saldo calculado. No existe una nota de crédito que reduzca automáticamente el valor del tratamiento cancelado. La nota registrada es un movimiento interno, no un documento tributario.

### Recaptación, encuestas y mensajes

En **Recaptación** registra un motivo objetivo —control, pendiente clínico, presupuesto o saldo—, fecha y estado. En esta versión es un registro manual; no hay un motor que genere automáticamente todos esos seguimientos desde las demás tablas.

Las encuestas permiten preguntas escritas una por línea. Las respuestas se registran internamente con una valoración de 1 a 5 y texto. No hay un portal público de pacientes ni enlaces individuales para responder.

Los mensajes pueden registrarse manualmente; las citas intentan generar avisos automáticamente, pero están afectadas por A1. Para procesar mensajes pendientes en un entorno de prueba:

```powershell
& .\.runtime\php\php.exe .\diente-feliz\artisan clinic:send-messages
```

El modo actual es `MAIL_MAILER=log`: **no envía correos externos**. Existe un fallo adicional: `LOG_LEVEL=warning` filtra los mensajes escritos a nivel debug, aunque se marquen como enviados. Antes de demostrar captura de correo local, corrige ese canal; para una prueba exclusivamente ficticia puede configurarse `LOG_LEVEL=debug` en `.env`, limpiar configuración y reiniciar. No actives SMTP pensando que la entrega ya está probada.

La programación automática requiere un proceso separado:

```powershell
& .\.runtime\php\php.exe .\diente-feliz\artisan schedule:work
```

No es necesario ejecutarlo para consultar la interfaz. Detén el proceso con Ctrl+C cuando termines.

### Filtros, descargas y reportes

Los módulos ofrecen filtros según su contenido: nombre, sucursal, paciente, profesional, box, estado y fechas. El buscador de nombre no busca en todos los textos clínicos. Los listados muestran 20 filas por página.

Si tienes permisos de exportación, marca filas y elige un formato. Sin filas seleccionadas se exportan los primeros resultados del filtro, hasta 100; no se exporta automáticamente todo el sistema.

- **CSV / Excel:** descarga CSV separado por punto y coma, no `.xlsx`. Si Excel no separa columnas, impórtalo como texto UTF-8 con delimitador `;`.
- **Documento imprimible / PDF:** descarga HTML. Ábrelo en el navegador y utiliza Imprimir → Guardar como PDF. Los botones de impresión de la interfaz también dependen del navegador.
- **ZIP:** disponible en documentos para descargar archivos seleccionados.

Las exportaciones actuales no contienen todos los campos calculados o las secciones de la ficha. En particular, no las uses como respaldo financiero completo ni como copia íntegra de consentimientos. Los reportes se limitan a las sedes y permisos del usuario.

## 5. Cómo leer y modificar el código

| Archivo o carpeta | Responsabilidad |
|---|---|
| `routes/web.php` | Rutas, autenticación, middleware de cuenta y límites de solicitudes |
| `app/Http/Controllers/AuthController.php` | Login, bloqueo, logout y cambio de contraseña |
| `app/Http/Controllers/AdminController.php` | Usuarios, roles y sucursales asignadas |
| `app/Http/Controllers/ClinicController.php` | Listados, formularios, ficha, descargas y reportes |
| `app/Services/Modules.php` | Catálogo de módulos, campos y reglas de validación |
| `app/Services/Clinic.php` | Reglas operativas y persistencia; contiene los defectos principales auditados |
| `app/Models/Record.php` | Modelo dinámico compartido; requiere revisión antes de ampliarlo |
| `app/Policies/RecordPolicy.php` | Autorización para consultar o actualizar registros |
| `app/Http/Middleware/EnabledUser.php` | Inhabilitación de cuenta y versión de sesión |
| `app/Services/Audit.php` | Eventos de auditoría, con cambios cifrados |
| `database/migrations/` | Tablas, relaciones y restricciones PostgreSQL |
| `database/seeders/ClinicSeeder.php` | Datos ficticios; depende incorrectamente de IDs fijos |
| `resources/views/` | Pantallas Blade |
| `public/clinic.css` y `public/clinic.js` | Estilos y comportamientos de interfaz |
| `tests/` | Pruebas existentes |

Una petición típica sigue este recorrido: ruta → autenticación y estado de cuenta → controlador → permisos y validación → servicio → modelo/PostgreSQL → vista. Las escrituras deben mantenerse bajo validación y autorización del servidor aunque cambie la interfaz.

Para un cambio de presentación, comienza en la vista correspondiente y en `clinic.css`. Para una regla de agenda o finanzas, localiza primero su prueba y su operación en `Clinic.php`. Agregar solo una entrada a `Modules.php` no implementa por sí mismo una nueva regla de negocio.

No edites `vendor`: se reconstruye desde `composer.lock`. No modifiques los archivos sincronizados de `sources/`.

## 6. Ejecutar comprobaciones

Antes de ejecutar tests confirma que `.env.testing` contiene `DB_DATABASE=diente_feliz_test`. Las pruebas con `RefreshDatabase` pueden reconstruir esa base. No reutilices la base demo ni una base con información que quieras conservar.

Desde la carpeta principal:

```powershell
& .\.runtime\php\php.exe .\diente-feliz\vendor\phpunit\phpunit\phpunit --configuration .\diente-feliz\phpunit.xml
```

Resultado de la auditoría: 21 pruebas, 4 aprobadas y 17 errores de preparación. Para investigar sin que el fallo de secuencias impida ejecutar los demás casos:

```powershell
.\auditoria\2026-10-26\run-isolated-tests.ps1
```

Ese script ejecuta cada prueba funcional en un proceso separado. Resultado observado: 13 aprobadas y 5 fallidas. Es un instrumento de diagnóstico, no una reparación de la suite.

Para consultar avisos de dependencias, con conexión a Internet:

```powershell
Push-Location .\diente-feliz
& ..\.runtime\php\php.exe ..\.runtime\composer.phar audit --locked
Pop-Location
```

No uses `composer update` para intentar reparar una prueba sin identificar la causa. El archivo `composer.lock` conserva las versiones concretas.

## 7. Llevar el proyecto a otro equipo

La copia actual tiene herramientas portátiles y archivos locales que no forman parte de una instalación reproducible completa. La instalación en un segundo equipo **no fue ejecutada** en esta auditoría.

Para preparar una instalación nueva:

1. Disponer de PHP compatible con el lock actual —PHP 8.4 fue la versión verificada—, Composer y PostgreSQL. Habilitar las extensiones necesarias, entre ellas `pdo_pgsql`, `pgsql`, `mbstring`, `fileinfo`, `openssl`, `curl`, `zip`, `intl` y `gd`. Comprobar requisitos con `composer check-platform-reqs` después de instalar.
2. Copiar el código y `composer.lock`. Si se trasladan datos cifrados, preservar de forma privada la clave existente y los archivos clínicos. No publicar `.env`, contraseñas de base de datos, `pgdata` ni archivos privados en Git.
3. Crear una base de aplicación y otra de pruebas. El usuario de migraciones necesita poder instalar `btree_gist`; la cuenta web debería ser distinta y de privilegios limitados. La configuración actual con `postgres` es uno de los hallazgos pendientes.
4. Preparar `.env` con PostgreSQL. No usar sin revisar el `.env.example` original: todavía selecciona SQLite y depuración activa.
5. Ejecutar `composer install`, no `composer update`. Si se parte de datos completamente nuevos, generar una única `APP_KEY` con `php artisan key:generate`.
6. Definir `DEMO_PASSWORD` antes del seeding, con al menos 12 caracteres y una contraseña adecuada para demo. Ejecutar migraciones y seeder únicamente sobre la base nueva y vacía. El seeder actual puede fallar con secuencias o datos preexistentes.
7. Iniciar la web solo en `127.0.0.1` y ejecutar las pruebas. No considerar aprobado el traslado mientras persistan los errores del informe.

Valores que se deben configurar en `.env`: `APP_ENV=local`, `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:8000`, `DB_CONNECTION=pgsql`, host/puerto/base/usuario/contraseña propios, `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=strict`, `SESSION_LIFETIME=30`, `MAIL_MAILER=log`. En HTTP local la cookie Secure permanece desactivada; para un futuro despliegue HTTPS requiere otra configuración y revisión.

No vuelvas a ejecutar `configure-local.ps1` en la instalación actual sin revisar sus efectos: reemplaza `.env` y `.env.testing` y depende del secreto local de PostgreSQL.

## 8. Diagnóstico de problemas frecuentes

| Síntoma | Qué revisar |
|---|---|
| No se puede abrir la web | La terminal de `artisan serve` debe seguir abierta; comprobar host y puerto |
| Puerto 8000 ocupado | Usar `--port=8001` y abrir esa dirección; no finalizar procesos desconocidos |
| Error de conexión a PostgreSQL | `pg_isready`, puerto 55432, contraseña y base configurada; dentro de un entorno aislado puede requerir autorización de red local |
| El dashboard no muestra citas | Las citas demo son históricas; consultar Agenda y filtrar por fecha |
| Error 419 | Recargar el formulario, iniciar sesión de nuevo y no alternar hostnames; no desactivar CSRF |
| Error 403 o menú ausente | Revisar rol y sucursales asignadas; no dar permisos de administrador como solución general |
| Archivo de 5 MB rechazado | PHP admite 2 MB por archivo en esta copia, aunque el formulario anuncie 10 MB |
| Error 500 al crear cita | Defecto A1 confirmado; no se corrige cambiando la fecha o repitiendo la petición |
| Error de clave foránea al ejecutar todos los tests | Seeder con IDs fijos; hallazgo A2 |
| Correo “Enviado” pero no aparece | Modo log y nivel warning; no equivale a entrega SMTP |
| Datos cifrados ilegibles | Revisar que se conserva la `APP_KEY` original; no generar otra para intentar repararlos |
| Cambios de vistas no aparecen | Ejecutar `artisan view:clear`; si cambiaste `.env`, también `artisan config:clear`, y reiniciar el servidor |

## 9. Conservar el trabajo

No basta con copiar el código: los datos están en PostgreSQL y los documentos en `diente-feliz/storage/app/private`. El cifrado depende de la clave guardada en `.env`.

Antes de modificar migraciones o actualizar dependencias, realiza un respaldo de PostgreSQL con `pg_dump`, conserva una copia privada de la clave y de los documentos, y verifica la restauración en otra base. No copies un directorio `pgdata` mientras el servidor está escribiendo ni publiques respaldos como parte del código.

La auditoría no hizo una prueba de restauración. Su informe separa los controles comprobados de los que aún faltan.

## Actualización de formularios y encuestas

Consulta [REVISION-REQUISITOS.md](diente-feliz/REVISION-REQUISITOS.md) para los cambios del 27 de octubre: títulos automáticos de presupuestos, inicio y duración de citas, dientes seleccionables, recaptación con fecha opcional, filtros por módulo e invitaciones de encuestas. Las respuestas ya no se crean manualmente. Los mensajes del transporte local se marcan Simulado.
