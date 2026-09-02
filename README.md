# Te Pinta 3.0

Te Pinta 3.0 es una aplicación nueva de pedidos y administración para Te Pinta. Este repositorio contiene una base segura y modular: navegación pública, entorno autenticado de administración/POS, base de datos y documentación de ingeniería. Las capacidades implementadas y lo que queda diferido se describen en [Estado actual](docs/CURRENT_STATE.md).

## Tecnologías

- PHP 8.3+ (Sail usa PHP 8.5) y Laravel 13
- PostgreSQL
- React 19, TypeScript e Inertia 3
- Tailwind CSS 4 y primitivas shadcn/ui
- PHPUnit mediante el runner estándar de Laravel
- Playwright para validación funcional y responsive en navegador
- Laravel Sail para el entorno local

## Cómo empezar

Requisitos previos: Docker con Docker Compose. PHP, Composer y Node son opcionales en el host.

```bash
cp .env.example .env
# Set a local DB_PASSWORD in .env before starting the containers.
make setup
make admin
make dev
```

`make setup` usa Docker para Composer, PHP, PostgreSQL y Node; no requiere esos
runtimes en el host. Nunca versionés el `.env` generado ni reutilices credenciales
locales en otro entorno.

Abrí la URL configurada en `APP_URL`. No existe registro público de administradores.

Consultá [Comandos del proyecto](docs/COMMANDS.md) para la interfaz raíz completa, [Desarrollo](docs/DEVELOPMENT.md) para la configuración y [Mapa del proyecto](docs/PROJECT_MAP.md) para ubicar código rápidamente.

## Controles de calidad

```bash
make check
make e2e
make audit
```

Ejecutá sólo scripts presentes en `composer.json` o `package.json`; [Pruebas](docs/TESTING.md) es la fuente de verdad de la cobertura actual. `make npm ARGS="run test:e2e:ui"` abre el runner interactivo de Playwright para diagnóstico local.

## Documentación

Leé primero [docs/INDEX.md](docs/INDEX.md). Arquitectura, seguridad, base de datos, diseño, logging, pruebas y ownership de módulos se documentan por separado para mantenerlos prácticos de actualizar.

## Límites de alcance

No hay flujo de pago online. El checkout público produce un mensaje de WhatsApp revisado por el cliente; el servidor sigue siendo autoritativo para precios, totales, identificadores y transiciones de estado. Consultá [Estado actual](docs/CURRENT_STATE.md) antes de asumir que un módulo diferido ya está implementado.
