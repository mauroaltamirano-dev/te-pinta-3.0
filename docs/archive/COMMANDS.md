# Comandos del proyecto

El `Makefile` raíz es la interfaz de comandos soportada para el desarrollo local,
los controles de calidad y el entorno aislado de Tailscale. No agrega paquetes ni
dependencias de producción: los objetivos delegan en Docker, Laravel Sail, los scripts
existentes de Composer/npm o los scripts protegidos de `deploy/tailscale/`.

Ejecutá `make` o `make help` para ver los comandos principales.

## Configuración inicial

Los requisitos previos son Docker con Docker Compose y GNU Make. No se necesitan
PHP, Composer ni Node en el host.

```bash
cp .env.example .env
# Set a non-empty local DB_PASSWORD in .env.
make setup
make admin
make dev
```

`make setup` instala las dependencias bloqueadas de Composer mediante el contenedor
oficial de Composer, inicia Sail, genera `APP_KEY` sólo cuando está vacía, ejecuta
las migraciones versionadas pendientes e instala las dependencias bloqueadas de npm
con `npm ci`. Si `.env` no existe, lo crea y se detiene para que las credenciales
se configuren deliberadamente. Nunca versionés `.env`, `APP_KEY` ni credenciales locales.

La configuración es repetible, pero no es de sólo lectura: instala archivos de
dependencias y las migraciones normales pueden cambiar el esquema de desarrollo
configurado. Nunca ejecuta `migrate:fresh`, elimina volúmenes ni crea implícitamente
un administrador.

## Ciclo de vida local

| Comando        | Propósito                                                        |
| -------------- | ---------------------------------------------------------------- |
| `make up`      | Inicia los contenedores de Sail en segundo plano.                |
| `make dev`     | Inicia Sail, migra y ejecuta Vite en primer plano.               |
| `make stop`    | Detiene los contenedores sin eliminarlos.                        |
| `make down`    | Detiene y elimina contenedores y la red Docker local.            |
| `make restart` | Reinicia los contenedores existentes.                            |
| `make status`  | Muestra el estado actual de los contenedores de Sail.            |
| `make logs`    | Sigue los logs; se detiene con `Ctrl+C`.                         |
| `make shell`   | Abre una terminal como usuario de la aplicación Sail.            |
| `make admin`   | Crea un administrador de forma interactiva.                      |
| `make migrate` | Ejecuta las migraciones versionadas pendientes en la base local. |

`make stop` conserva los contenedores. `make down` elimina los contenedores y
la red, pero mantiene el volumen nombrado de PostgreSQL. Deliberadamente no hay
un comando raíz para `down --volumes`, `db:wipe` ni `migrate:fresh` en desarrollo.

Pasá argumentos opcionales de logs de Docker Compose mediante `ARGS`:

```bash
make logs ARGS="--tail=100 laravel.test"
```

## Calidad y pruebas

| Comando           | Ejecuta                                                                            |
| ----------------- | ---------------------------------------------------------------------------------- |
| `make format`     | Aplica el formato de Pint y Prettier.                                              |
| `make lint-check` | Verifica Pint y ESLint sin cambiar archivos.                                       |
| `make types`      | Ejecuta los controles de PHPStan y TypeScript.                                     |
| `make test`       | Ejecuta Pint, PHPStan y la suite de pruebas de Laravel.                            |
| `make check`      | Ejecuta lint/formato/tipos frontend, `make test` y el build de producción de Vite. |
| `make build`      | Compila únicamente los assets frontend de producción.                              |
| `make e2e`        | Ejecuta la suite completa de Playwright.                                           |
| `make audit`      | Ejecuta auditorías de dependencias de Composer y npm.                              |

Playwright y las auditorías de dependencias permanecen fuera de `make check`
para que el control de calidad predeterminado sea determinista cuando no haya
navegadores o acceso al registro. Consultá [TESTING.md](TESTING.md) para las suites
focalizadas, la base de testing aislada y las invariantes de regresión.

## Ejecución focalizada

Usá `ARGS` cuando un comando del framework o del gestor de paquetes no justifique
otro alias:

```bash
make artisan ARGS="route:list"
make artisan ARGS="test tests/Feature/Dashboard"
make npm ARGS="run test:e2e -- tests/e2e/dashboard.spec.ts"
make composer ARGS="audit"
```

Los argumentos se ejecutan dentro del contenedor de la aplicación Sail. Sail debe estar iniciado.

## Entorno operativo de Tailscale

El entorno similar a producción en el puerto loopback `8082` está separado del
desarrollo mutable. El Makefile envuelve los scripts existentes; no duplica ni
evita sus controles de entorno, verificaciones de salud o confirmaciones.

Comandos de sólo lectura:

```bash
make ops-status
make ops-verify
make route-status
```

Prepará una imagen sin iniciar ni reemplazar un servicio:

```bash
make ops-prepare
```

Las mutaciones del entorno requieren la confirmación que ya impone `runtime.sh`:

```bash
OPERATIONAL_RUNTIME_CONFIRM=yes make ops-start
OPERATIONAL_RUNTIME_CONFIRM=yes make ops-migrate
OPERATIONAL_RUNTIME_CONFIRM=yes make ops-stop
```

Los cambios de ruta conservan la confirmación independiente de Tailscale:

```bash
TAILSCALE_ROUTE_CONFIRM=yes make route-cutover
TAILSCALE_ROUTE_CONFIRM=yes make route-rollback
```

La ruta privada de Serve y la ruta pública de medios de Instagram son independientes.
Inspeccioná o actualizá sólo el handler de Funnel restringido por ruta con:

```bash
make media-route-status
TAILSCALE_FUNNEL_CONFIRM=yes make media-route-cutover
```

El comando de Funnel administra únicamente `https://<device>:8443/media/instagram`.
Su backend incluye el mismo prefijo `/media/instagram` porque Tailscale quita el
prefijo público montado antes de hacer proxy. Nunca publica `/admin`, `/api` ni
el handler privado raíz de Serve.

Sin esos valores de entorno exactos, los scripts subyacentes rechazan la mutación.
El Makefile nunca proporciona la confirmación automáticamente.

### Límite del rollback

`make route-rollback` sólo vuelve a dirigir Tailscale Serve al backend verificado
de Te Pinta 2.0 en `127.0.0.1:8080`. No revierte migraciones de base de datos,
detiene Te Pinta 3.0, restaura un backup ni cambia archivos de entorno. A la inversa,
`make ops-stop` detiene sólo los contenedores aislados de la aplicación y el scheduler
de Te Pinta 3.0 y no cambia la ruta. Tratá el backup, la migración, la verificación,
el cutover y el rollback como decisiones operativas separadas y seguí el procedimiento
completo de [DEVELOPMENT.md](DEVELOPMENT.md#operational-runtime-and-mobile-installation).
