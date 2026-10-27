# Cambios de formularios y revisión de requisitos — 27 de octubre de 2026

## Alcance

Se contrastó el sistema con los diez puntos del mensaje del usuario y con los requisitos iniciales escritos en este chat. La carpeta `sources` no contiene documentos de reunión disponibles y la conversación consultada no devuelve el acta. Por tanto, esta revisión no certifica acuerdos adicionales que no estén escritos aquí. No se modificaron archivos de referencia.

## Lista de cumplimiento

| Solicitud | Resultado y forma de usarlo |
|---|---|
| Presupuesto no debe pedir otro nombre de paciente | Cumplido. Se selecciona el paciente y el título se genera automáticamente. Se conservan los títulos de presupuestos anteriores. El detalle de prestaciones sigue siendo editable. |
| Aclarar identificador ficticio y habilitado | Cumplido. Se muestra **Código de paciente**, con ejemplo PAC-0001: es un identificador único de la ficha, no un RUT validado. La ayuda explica que deshabilitar conserva el historial e impide nuevas operaciones. Se mantienen datos ficticios en el entorno académico. |
| Citas sin término manual | Cumplido. Se introduce fecha/hora de inicio y se eligen 30, 45 o 60 minutos. El servidor calcula el término, ignora un término manipulado y mantiene las comprobaciones de disponibilidad y solapamiento. La disponibilidad de profesionales conserva desde/hasta porque define una jornada, no una cita. |
| Dientes seleccionables | Cumplido con dibujos esquemáticos SVG de incisivos, caninos, premolares y molares para ambas denticiones. Al pulsar un modelo se seleccionan la pieza FDI y la superficie completa. Las superficies individuales siguen disponibles debajo. No son modelos tridimensionales ni imágenes diagnósticas. |
| Fecha de contacto en recaptación | Opcional. Representa una fecha prevista para organizar seguimientos, no un contacto ya realizado. Una gestión sin fecha queda pendiente de programar. El filtro de fechas usa la fecha prevista. |
| Aviso de sucursal en notificaciones | Cumplido para **Notificaciones y correos**: el mensaje se guarda con un aviso si el paciente no está asociado a la sucursal elegida. El usuario debe tener acceso al paciente y a esa sucursal. No se agregan asociaciones automáticamente ni se relajan los controles de fichas clínicas. |
| Encuestas con preguntas hechas | Cumplido. La migración agrega una encuesta de satisfacción inicial y los formularios nuevos incluyen cuatro preguntas editables. La plantilla acepta de 1 a 20 preguntas. |
| Respuestas recibidas desde el paciente | Cumplido mediante formulario web personal. Se eliminó el botón de creación manual y el servidor rechaza esos intentos. El enlace puede copiarse para compartirlo o agregarse a la cola de correo. No se implementó lectura de respuestas escritas directamente en un correo ni integración automática de WhatsApp/SMS. |
| Sucursales y box sin fechas irrelevantes | Cumplido. Sucursales filtra nombre, dirección y habilitación. Box filtra nombre, sucursal y habilitación; la dirección corresponde a la sucursal. |
| Filtros de administración coherentes | Cumplido para los módulos existentes. Profesionales, plantillas, convenios y encuestas no filtran por fecha de creación. Se muestra búsqueda por nombre únicamente cuando existe ese campo. Disponibilidad conserva fechas por tratarse de intervalos horarios. |

## Tutorial de encuestas

1. Abre **Encuestas**, entra en **Satisfacción de la atención** o crea una encuesta usando las preguntas iniciales.
2. Pulsa **Invitar a responder**. Selecciona un paciente habilitado, su sucursal y el canal de entrega.
3. La invitación requiere autorización de comunicaciones. Para correo necesita también una dirección registrada.
4. **Copiar enlace para compartir** genera un enlace personal. **Agregar invitación a la cola de correos** crea un mensaje pendiente; no confirma un envío.
5. El paciente abre el enlace y valora cada pregunta del 1 al 5. Puede agregar un comentario. No se le muestra su identidad ni la ficha clínica.
6. La respuesta aparece en **Respuestas de encuestas**. La puntuación general es el promedio redondeado de las valoraciones. Se conservan las preguntas que tenía la encuesta al crear la invitación, aunque se editen después.

El enlace vence a los 14 días y admite una sola respuesta. La posesión del enlace permite responder: no verifica documentalmente la identidad del paciente. Compártelo solo con su destinatario. Deshabilitar la encuesta impide utilizar invitaciones pendientes.

En el entorno local, los enlaces a localhost solo funcionan en el mismo equipo. Para compartirlos fuera de ese equipo se necesita una dirección accesible y el despliegue correspondiente; no se publicó la aplicación ni se abrieron puertos externos.

Si el transporte de correo es `log`, el procesador marca los mensajes **Simulado** y no los envía. El contenido permanece consultable en Notificaciones. Para entrega real se necesita configurar y probar SMTP u otro transporte; esa entrega no fue ejecutada durante esta revisión.

## Instalación de esta actualización

En la copia local ya se aplicó la migración. En otra instalación existente, después de copiar el código y conservar su `.env`, clave y archivos privados:

```sh
php artisan migrate --force
php artisan view:clear
```

No uses `migrate:fresh`, no regeneres la clave y no vuelvas a cargar los datos demo sobre una base con información que quieras conservar.

La copia AWS incorpora el mismo código y la migración, manteniendo sus ajustes de despliegue. Se regenera el paquete ZIP sin secretos ni datos locales. Sigue pendiente construir y probar los contenedores en AWS.

## Verificación realizada

- Migración aplicada correctamente en PostgreSQL local y en la base separada de pruebas.
- 10 pruebas enfocadas y unitarias: **140 comprobaciones aprobadas**.
- 5 pruebas existentes ejecutadas por separado: pagos/devoluciones (12), consentimientos/versiones (7), archivos (10), páginas/formularios (61) y agenda (8). Todas aprobadas.
- Total de esas ejecuciones: **15 pruebas, 238 comprobaciones**. No equivale a aprobar toda la suite original; se conserva la limitación del seeder con identificadores fijos documentada en la auditoría.
- Se comprobó CSRF del formulario público desactivando expresamente el atajo del entorno de pruebas: petición sin token rechazada con 419 y petición con token aceptada.
- Se comprobaron vencimiento, reutilización, cifrado, escape de HTML, permisos, rechazo de creación manual y cálculo de duración en servidor.
- Revisión visual en navegador de la ficha y los modelos dentales; se pulsó un incisivo y se confirmó la pieza 12 preseleccionada en el formulario. No se modificaron hallazgos durante esa comprobación.
- No se enviaron correos externos, no se verificó entrega SMTP y no se ejecutó Docker/AWS.

## Auditoría A — Funcionamiento y seguridad

El problema A1 de la auditoría anterior se corrigió mediante campos asignables explícitos por módulo, evitando la caché de columnas compartida del modelo dinámico. Se verificaron escrituras consecutivas en citas/mensajes, pagos/devoluciones, consentimientos/plantillas y encuestas/respuestas. Los campos derivados y el autor siguen asignándose en el servidor; no se habilitó asignación masiva irrestricta.

La simulación de correos ya no se presenta como envío real. La entrega externa y los posibles reintentos del transporte requieren pruebas específicas adicionales.

Persisten hallazgos previos ajenos a estos cambios: concesión de permisos superiores desde gestión de usuarios (ALTO), citas simultáneas del mismo profesional registrado por separado en distintas sedes (ALTO), usuario PostgreSQL local con privilegios excesivos (ALTO), y fallos generales del seeder de pruebas (ALTO). Ver archivos, líneas, evidencia, consecuencias y correcciones en `auditoria/2026-10-26/INFORME.md` del proyecto principal. No se declara corregida la auditoría completa.

## Auditoría B — Calidad y arquitectura

Se mantuvo el registro de módulos para formularios y filtros. Las invitaciones y respuestas públicas se separaron en `SurveyController`; no se añadieron paquetes ni servicios externos. Continúan las observaciones previas sobre concentración de lógica en `Clinic`, consultas repetidas y límite de opciones en selectores (MEDIO). No se realizó una refactorización general.

**APROBADO CON OBSERVACIONES para esta actualización local:** los cambios solicitados y las pruebas indicadas pasan; el correo externo y la validación completa del despliegue están pendientes. **RECHAZADO para producción como sistema completo:** permanecen los hallazgos altos señalados y no se ha completado su verificación.
