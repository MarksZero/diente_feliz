# Limpieza y separación de versiones — 27 de octubre de 2026

## Ubicación de cada versión

- `diente-feliz/`: aplicación local; conserva `.env`, dependencias y acceso a la base existente.
- `diente-feliz-aws/`: copia de código y configuración para EC2, HTTPS y RDS. Sus instrucciones están en su `README.md`.
- `diente-feliz-aws.zip`: paquete para transferir al servidor. No subir la carpeta raíz completa.
- `TUTORIAL-DIENTE-FELIZ.md`: instrucciones de uso local.
- `auditoria/2026-10-26/`: informe y evidencia de los defectos pendientes.

## Qué se eliminó

Se retiraron 1.126.018.006 bytes, aproximadamente 1,13 GB decimales:

- ZIP de instalación de PHP y PostgreSQL, ya extraídos y con ejecutables presentes.
- pgAdmin y StackBuilder incluidos en el ZIP de PostgreSQL, que no se usaban para ejecutar el proyecto.
- Caché de descargas de Composer; se conservan Composer y las dependencias instaladas de la aplicación.
- Tres resultados de auditoría duplicados en la raíz, después de verificar que coincidían con las copias conservadas.
- Página de bienvenida de Laravel y archivos de Vite/Tailwind/Node sin referencias desde la interfaz usada.
- Base SQLite vacía, caché de resultados PHPUnit y vistas Blade compiladas.

La lista de archivos, tamaños y hashes de la primera limpieza está en `LIMPIEZA-2026-10-27.json`. Los directorios opcionales y caché retirados se registran en `LIMPIEZA-COMPLEMENTARIA-2026-10-27.json`.

No se eliminaron `sources`, base PostgreSQL, contraseña de base de datos, `.env`, `.env.testing`, documentos clínicos privados, pruebas ni evidencia de auditoría. Se mantuvieron las bibliotecas de PHP y PostgreSQL necesarias para ejecutarlos. Las vistas se regeneran al usar la aplicación.

## Ajustes efectuados

El `composer.json` local ya no intenta ejecutar Node ni generar una nueva clave al preparar el proyecto. Se reemplazó el README genérico por instrucciones locales. El código de los módulos de negocio conserva los defectos identificados en la auditoría.

La copia AWS añade configuración de Docker, Apache, Caddy, validación TLS de RDS, cookies seguras y una provisión inicial de administrador con contraseña interactiva. El scheduler queda desactivado por defecto. No se desplegó nada ni se crearon recursos AWS.

## Comprobaciones realizadas

- Composer validó ambos manifiestos y sus locks.
- La aplicación local compiló sus vistas y registró sus 21 rutas.
- 38 archivos PHP de la copia AWS pasaron la validación de sintaxis.
- El script de arranque pasó `bash -n`.
- 80 archivos del paquete se comprobaron contra `MANIFEST-SHA256.json`.
- No se encontraron los valores de APP_KEY, DB_PASSWORD o DEMO_PASSWORD del entorno local dentro de la copia AWS.

La sintaxis no sustituye una prueba de despliegue. Docker no está instalado en este equipo; no se construyó la imagen ni se ejecutaron Compose, Caddy, RDS o el comando de provisión en AWS. Tampoco se repitió la suite funcional completa: la limpieza no corrige sus fallos y el informe previo sigue vigente.

## Regenerar el ZIP

Tras modificar la copia AWS, conservar o renombrar el ZIP anterior y ejecutar desde la raíz:

```powershell
.\empaquetar-aws.ps1
```

El script se niega a reemplazar un ZIP existente o a incluir entornos privados, dependencias instaladas, logs, bases de datos o archivos de almacenamiento. No agregues credenciales al código ni al ejemplo de entorno. El ZIP debe generarse antes de configurar un `.env` privado en esa carpeta; en el flujo previsto, ese archivo se crea directamente en el servidor.

**Estado:** limpieza y paquete separados completados. La aprobación funcional y de seguridad para producción sigue pendiente de resolver los hallazgos de auditoría y validar el despliegue.
