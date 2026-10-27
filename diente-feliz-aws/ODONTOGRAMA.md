# Odontograma

## Uso

1. Inicia sesión con un usuario que tenga `clinical.view`. Para registrar hallazgos necesita también `clinical.write`.
2. En **Pacientes**, abre la ficha. El odontograma aparece tanto en la ficha simple como en la avanzada.
3. Elige la dentición permanente o temporal. El dibujo muestra la derecha del paciente a la izquierda de la pantalla; las piezas usan numeración FDI.
4. Haz clic en una superficie para abrir el formulario con paciente, pieza y superficie seleccionados. Haz clic en el número para registrar la pieza completa.
5. Selecciona la sucursal, confirma los datos y elige Sano, Caries, Restaurado, Ausente, Endodoncia o Corona. Puedes agregar una observación. Guarda para volver al odontograma.
6. Consulta **Hallazgos vigentes y observaciones** o **Historial completo**. Cada registro conserva su fecha, sucursal y autor; las observaciones se almacenan cifradas.

V significa vestibular; L/P, lingual o palatina; M, mesial; D, distal; O/I, oclusal o incisal. El formulario almacena palatina como Lingual e incisal como Oclusal para conservar la compatibilidad con los registros existentes.

Los colores son una convención interna explicada en la leyenda. Gris claro significa sin registro, no sano. La aplicación no emite diagnósticos automáticos.

## Correcciones e historial

Los hallazgos no se editan ni eliminan. Para corregir uno se registra otro. El dibujo toma el último registro de cada superficie por orden de guardado. Un nuevo registro de pieza completa reemplaza las superficies anteriores en el dibujo; los registros posteriores por superficie refinan ese estado. El historial original permanece disponible.

La vista reúne los registros de las sucursales autorizadas del usuario. Un usuario con acceso a menos sucursales puede ver un estado diferente. La ficha muestra la sucursal de cada hallazgo vigente. Las fechas mantienen la zona horaria configurada en la aplicación (UTC en la configuración actual).

En pantallas pequeñas se desplazan las arcadas horizontalmente. Los enlaces admiten teclado y no requieren JavaScript. El botón Imprimir / PDF de la ficha permite imprimir también el odontograma.

## Verificación del cambio — 27 de octubre de 2026

- Compilación de vistas Blade correcta.
- Prueba específica `tests/Feature/OdontogramTest.php`: 1 prueba, 34 comprobaciones aprobadas sobre PostgreSQL de pruebas, sin modificar datos clínicos locales.
- Cubre guardado, autor asignado por servidor, auditoría, cifrado, escape de HTML, validación de piezas/superficies/estados, acceso por sucursal, inmutabilidad y usuarios de lectura o sin permiso clínico.
- El cambio utiliza la tabla existente; no requiere migraciones ni volver a cargar los datos de prueba.
- Se añadió una lista explícita de campos asignables para el odontograma para evitar el problema de caché de columnas del modelo dinámico al guardar este módulo. El problema general de ese modelo en otros módulos sigue pendiente.
- No se realizó una revisión visual en navegador ni una ejecución de Docker/AWS. No se repitió la suite general, que tenía fallos documentados en la auditoría anterior.

La copia para AWS contiene el mismo cambio funcional. La configuración específica de despliegue permanece separada. Este cambio no convierte el sistema completo en apto para producción.

## Actualización del 27 de octubre

Los modelos de dientes también son seleccionables: elige el dibujo para registrar la pieza completa. Se distinguen incisivos, caninos, premolares y molares; son esquemas, no imágenes diagnósticas. La selección se comprobó en navegador. La validación actualizada está en REVISION-REQUISITOS.md.
