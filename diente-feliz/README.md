# Diente Feliz — versión local

Aplicación Laravel + PHP + PostgreSQL. Interfaz Blade con CSS y JavaScript propios; no usa Node, Vite ni Tailwind.

Desde la carpeta superior a esta, iniciar PostgreSQL y después:

```powershell
& .\.runtime\php\php.exe .\diente-feliz\artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Abrir http://127.0.0.1:8000. Las instrucciones completas, cuentas demo y limitaciones están en `../TUTORIAL-DIENTE-FELIZ.md`.

Conservar `.env`, su `APP_KEY`, la base PostgreSQL y `storage/app/private`. No ejecutar `migrate:fresh` sobre datos que se quieran conservar. `composer setup` instala dependencias y aplica migraciones; requiere un `.env` ya configurado y no regenera la clave.

Los resultados de auditoría están en `../auditoria/2026-10-26/INFORME.md`. La aplicación mantiene fallos conocidos y no está aprobada como entrega final.

La carpeta hermana `../diente-feliz-aws` contiene una copia separada con configuración para EC2/RDS. No subir esta carpeta local completa a AWS: incluye herramientas y datos privados fuera del código.
