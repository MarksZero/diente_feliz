# Diente Feliz — copia separada para AWS

## Estado del paquete

Preparación de despliegue, **no versión aprobada para producción**. No se ha desplegado en AWS ni construido la imagen Docker en este equipo, donde Docker no está instalado. La auditoría base está en `docs/AUDITORIA-BASE.md` y conserva fallos de agenda, permisos delegados y pruebas. Este empaquetado no los corrige.

La copia excluye `.env` local, claves, base de datos, documentos subidos, logs, cachés y herramientas Windows. Incluye fixtures de código para tests, pero no carga datos de prueba ni crea cuentas demo durante el despliegue. El comando de provisión crea una cuenta nueva con contraseña elegida por el operador.

La aplicación contiene textos de “DEMO” y “datos ficticios” en sus vistas. Se conservaron porque los flujos siguen pendientes de corrección y esta copia debe probarse con información ficticia.

## Arquitectura elegida

Una instancia EC2 Linux con Docker Engine y Compose v2 ejecuta Caddy (HTTPS) y Laravel con PHP/Apache. PostgreSQL se aloja en RDS dentro de la misma VPC, sin acceso público. Solo Caddy publica 80 y 443. Apache y el proceso opcional de mensajes no publican puertos.

Los archivos clínicos se guardan en un volumen Docker persistente sobre el disco EBS de EC2, porque el código actual usa explícitamente `Storage::disk('local')`. **Esta versión no integra S3**, no admite escalado horizontal sin adaptar ese almacenamiento y no incluye alta disponibilidad de EC2. Usar EBS cifrado, respaldarlo y probar restauración junto con RDS y la clave de aplicación.

No se crean recursos ni se fijan costos automáticamente. Se requiere dominio DNS, EC2, RDS y configuración de red del titular de la cuenta AWS. El diseño no usa un balanceador ALB; si se incorpora uno, revisar la confianza en proxies y TLS.

## Cambios respecto de la copia local

- Dominio permitido obtenido de `APP_URL`, sin confiar automáticamente en subdominios.
- Solo se confía en el proxy Caddy configurado, por defecto `172.30.42.10`.
- Cookies de sesión Secure, HttpOnly y SameSite estricto; depuración desactivada.
- PostgreSQL con `sslmode=verify-full` y certificado raíz de RDS.
- PHP con 10 MB por archivo y 12 MB por petición.
- Archivos privados sin rutas temporales de servicio automático y con errores de almacenamiento explícitos.
- Instalación de Composer sin dependencias de desarrollo en la imagen.
- Comando `clinic:provision-admin` para una base vacía, sin seeders demo.
- Migraciones y correos no se ejecutan automáticamente al iniciar el servidor.

La zona horaria de la lógica heredada sigue en UTC. No se alteró la interpretación de los datos del proyecto como parte de esta separación; resolver el hallazgo correspondiente antes de pruebas de agenda reales.

## 1. Preparar la infraestructura

No exponer el sistema a usuarios externos antes de resolver los bloqueantes del informe. Preparar primero un entorno de pruebas controlado.

1. EC2 Linux con Docker Engine y Compose v2. El usuario que administra Docker tiene privilegios sobre el host; limitar quién accede.
2. RDS PostgreSQL 17 en subredes privadas. No habilitar acceso público.
3. En el grupo de seguridad de RDS, permitir TCP 5432 **solo desde el grupo de seguridad de EC2**, no desde Internet.
4. En EC2, habilitar HTTPS 443 y HTTP 80 para el certificado y redirección de Caddy cuando se autorice la publicación. Administrar por Systems Manager o limitar SSH a una dirección administrativa.
5. Crear una base vacía `diente_feliz`. Crear una cuenta de migraciones propietaria de sus objetos y otra cuenta de ejecución `clinic_app` sin privilegios de superusuario ni DDL. La extensión `btree_gist` debe poder instalarse con la cuenta de migraciones o instalarla antes desde la administración de RDS.
6. Crear un registro DNS del dominio que apunte a la dirección estable de EC2.
7. Si la red `172.30.42.0/24` se solapa con una red existente, cambiarla en `compose.yaml` y ajustar `TRUSTED_PROXIES` para que coincida con la IP fija de Caddy.

Referencia AWS: [conexión entre EC2 y RDS](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/tutorial-connect-ec2-instance-to-rds-database.html). La base debe permanecer privada.

## 2. Copiar y configurar

Subir únicamente este directorio o el ZIP preparado, no la carpeta del proyecto local completa. En Linux, situarse en el directorio que contiene `compose.yaml`:

```bash
cp .env.aws.example .env
chmod 600 .env
mkdir -p certs
curl --fail --show-error --location \
  https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem \
  --output certs/global-bundle.pem
```

Completar `.env` en el servidor:

- `APP_DOMAIN`: el dominio sin protocolo ni ruta.
- `APP_URL`: `https://` seguido exactamente por ese dominio.
- `APP_KEY`: una clave nueva para una base nueva; ver el siguiente paso.
- `DB_HOST`: endpoint original de RDS, necesario para verificar el nombre del certificado.
- `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`: base y cuenta de ejecución.
- `MAIL_FROM_ADDRESS`: dirección propia cuando se configure correo.

No usar las credenciales demo ni el usuario administrador de RDS como cuenta web. No copiar una `.env` completada a Git, al ZIP ni a la imagen. Para esta primera versión se inyecta mediante `env_file`; Secrets Manager no está integrado automáticamente.

La [documentación de TLS de RDS PostgreSQL](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/PostgreSQL.Concepts.General.SSL.html) describe la validación de certificados. El paquete usa `verify-full` y `PGSSLROOTCERT=/run/certs/global-bundle.pem`. No degradar la conexión a `prefer` para evitar errores de certificado.

## 3. Construir y comprobar la imagen

```bash
docker compose config --quiet
docker compose build app
```

Las imágenes base usan etiquetas `php:8.4-apache-bookworm`, `composer:2` y `caddy:2-alpine`. Las versiones PHP instaladas por Composer quedan fijadas en `composer.lock`; las imágenes y paquetes del sistema no están fijados por digest. Después de validar una construcción, registrar y fijar sus digests antes de una entrega reproducible.

Para una base completamente nueva, generar una clave sin iniciar Laravel ni ejecutar migraciones:

```bash
docker run --rm --entrypoint php diente-feliz-app:local \
  -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Guardar el resultado en `APP_KEY` de `.env` y respaldarlo de forma privada. Si se importan datos cifrados existentes, conservar su clave original: generar otra los vuelve ilegibles. El arranque del contenedor nunca genera ni sustituye claves.

## 4. Migrar con una cuenta separada

En Bash, introducir credenciales de migración sin escribir su contraseña literal en el historial:

```bash
read -rp 'Usuario de migraciones: ' MIGRATION_USER
read -rsp 'Contraseña de migraciones: ' MIGRATION_PASSWORD
printf '\n'
docker compose run --rm \
  -e DB_USERNAME="$MIGRATION_USER" \
  -e DB_PASSWORD="$MIGRATION_PASSWORD" \
  app php artisan migrate --force
unset MIGRATION_USER MIGRATION_PASSWORD
```

No añadir `--seed` ni ejecutar `migrate:fresh`. En actualizaciones, tomar respaldo y revisar las migraciones antes de aplicarlas. La cuenta web debe tener SELECT/INSERT/UPDATE/DELETE en las tablas necesarias y USAGE/SELECT en sus secuencias; configurar también privilegios predeterminados para objetos futuros creados por el migrador. Restringir UPDATE/DELETE de auditoría en la cuenta web requiere validar expresamente las operaciones del sistema.

## 5. Crear el primer acceso

Con los permisos de la cuenta de ejecución ya aplicados:

```bash
docker compose run --rm app php artisan clinic:provision-admin
```

El comando solicita nombre, correo, contraseña confirmada y datos de la primera sucursal. No acepta una contraseña predeterminada y se niega a crear otra cuenta inicial si ya existen usuarios. No se ejecutó contra una base AWS en esta preparación.

Se crea el rol base de administrador. Los roles restantes se crean desde la aplicación una vez corregido el defecto de delegación de accesos. No importar usuarios demo para evitar este paso.

## 6. Iniciar el entorno autorizado

Después de corregir y verificar los bloqueantes de aplicación:

```bash
docker compose up -d app caddy
docker compose ps
docker compose logs --tail=100 app
```

Caddy obtiene el certificado para el dominio y redirige a HTTPS, siempre que DNS y conectividad estén configurados. Consulte la [guía oficial de HTTPS de Caddy](https://caddyserver.com/docs/quick-starts/https).

Verificar acceso a `/login`, rechazo de POST sin CSRF, cookies Secure, autorización por sede, subida/descarga y persistencia después de recrear el contenedor. `/up` es una comprobación básica de arranque, no demuestra que RDS y archivos estén disponibles.

Los logs pueden contener detalles técnicos: restringir el acceso al host y establecer una política de retención. No presentar capturas con secretos o información clínica.

## 7. Correo, respaldo y actualizaciones

El scheduler está en un perfil opcional y no se inicia por defecto. `MAIL_MAILER=log` no entrega correos externos; tampoco sirve como evidencia de captura con el nivel warning actual. Revisar primero el hallazgo de correo, configurar y probar el transporte autorizado. Solo entonces:

```bash
docker compose --profile messages up -d scheduler
```

No se provisionan credenciales SES/SMTP ni se envían correos como parte de este paquete.

Respaldar RDS, volumen `clinic_storage`, clave `APP_KEY` y configuración de dominio/TLS. Probar restauración en una base distinta. **No usar `docker compose down -v`**, porque elimina volúmenes, incluidos los documentos y los certificados.

Actualizar con imágenes verificadas y un respaldo previo. `docker compose down` sin `-v` detiene servicios conservando volúmenes. No copiar `vendor` de Windows al servidor: la imagen instala las dependencias desde el lock para Linux.

## Verificación pendiente

En este equipo se verifica la sintaxis PHP, consistencia básica del paquete y ausencia de secretos locales. Falta construir Docker, validar Compose/Caddy con sus binarios, conectar a RDS, probar migraciones y permisos de cuentas, obtener HTTPS, ejecutar los flujos y comprobar respaldo/restauración. El informe base sigue rechazando la aplicación para entrega final; este directorio es la preparación separada solicitada, no una aprobación nueva.
