# Desarrollo

Laravel Sail es la vía local admitida para PHP, PostgreSQL, Node y las extensiones.

## Configuración inicial

```bash
cp .env.example .env
# Set a local DB_PASSWORD before starting containers.
make setup
make admin
make dev
```

`make setup` utiliza Docker para cada runtime y se detiene antes de la instalación si la contraseña de la base de datos local está vacía. Nunca confirme `.env` ni `APP_KEY` en el repositorio. Consulte [COMMANDS.md](COMMANDS.md#first-setup) para conocer sus límites exactos.

## Configuración de pedidos

1. Inicie sesión como administrador.
2. Abra `/admin/settings/general`.
3. Revise los precios base cocinados y los descuentos de la alternativa cruda para la docena combinada y los productos.
4. Establezca el costo real de entrega. Su valor predeterminado de migración es deliberadamente `0`, no un precio de negocio inventado.
5. Establezca el destino de WhatsApp. Permanece deliberadamente vacío después de la migración; el checkout público no puede generar un enlace hasta que se configure.
6. Revise los nombres, precios, disponibilidad y orden de los toppings.

Estas configuraciones son registros de la base de datos, no secretos del entorno. Nunca codifique de forma rígida el teléfono comercial en React o PHP.

## Flujo de trabajo diario

```bash
make dev
```

Comandos útiles:

```bash
make status
make logs
make shell
make artisan ARGS="route:list"
make artisan ARGS="test"
make stop
make down
```

Ejecute `make artisan ARGS="storage:link"` una vez por entorno para las imágenes de productos. Cree de forma no destructiva los registros faltantes del catálogo con:

```bash
make artisan ARGS="db:seed --class=ProductCatalogSeeder"
```

## Seguridad de las migraciones

Ejecute las migraciones versionadas normales sobre el entorno de desarrollo. No ejecute `migrate:fresh`: eliminaría los administradores, productos, clientes y pedidos existentes. Las comprobaciones de migraciones desde cero corresponden a la base de datos de pruebas aislada.

Cree una vez la base de datos aislada y el entorno local de pruebas:

```bash
cp .env.testing.example .env.testing
# Set DB_PASSWORD in .env.testing to the same local PostgreSQL password used by Sail.
./vendor/bin/sail exec -T pgsql sh -lc 'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '\''testing'\''" | grep -qx 1 || createdb -U "$POSTGRES_USER" testing'
```

Luego verifique las migraciones desde cero únicamente con el entorno de pruebas explícito:

```bash
make artisan ARGS="migrate:fresh --env=testing"
```

`.env.testing` es local y se ignora; `.env.testing.example` es la plantilla versionada. Cuando `APP_ENV=testing`, el arranque de la aplicación rechaza cualquier nombre de base de datos que no contenga un segmento independiente `test` o `testing`. Esta protección también comprueba la base de datos seleccionada por `DB_URL`, cuando está presente. Existe porque, de otro modo, Laravel utiliza `.env` como alternativa cuando falta `.env.testing`.

Las secuencias de pedidos de PostgreSQL pueden contener espacios después de revertir transacciones o actualizar la secuencia. Un espacio no implica corrupción; nunca renumere pedidos ni reemplace la secuencia con `max + 1`.

## Control de calidad

```bash
make check
make e2e
make audit
```

Instale Chromium una vez con `make npm ARGS="exec -- playwright install chromium"`. Las pruebas del navegador utilizan el servidor y la base de datos de pruebas configurados; revise `playwright.config.ts` antes de cambiar los puertos.

Consulte [COMMANDS.md](COMMANDS.md) para conocer la interfaz canónica de comandos desde la raíz y la diferencia entre detener y eliminar los contenedores locales.

## Contrato del entorno

Los valores de aplicación, base de datos, sesión y logs provienen de `.env`; `.env.example` es la referencia sin secretos. Los precios base cocinados, los descuentos de la alternativa cruda, los toppings, el costo de entrega y el destino de WhatsApp residen en la UI tipada de configuración de la base de datos, no en variables de entorno.

## Runtime operativo e instalación móvil

El checkout mutable de Sail en `:8000` es para desarrollo. No dirija Tailscale Serve hacia él: cuando existe `public/hot`, las páginas renderizadas referencian el servidor de Vite y un teléfono resolvería `localhost:5173` como el propio teléfono.

`deploy/tailscale/` prepara una imagen separada, similar a producción, en el loopback `127.0.0.1:8082`. Utiliza assets compilados, mantiene `APP_DEBUG=false`, no contiene el archivo `.env` del proyecto y no copia a la imagen el almacenamiento local, los logs, las capturas de pantalla, las pruebas ni los artefactos de desarrollo.

Prepare la configuración local del runtime sin modificar el archivo `.env` de desarrollo:

```bash
cp deploy/tailscale/.env.example deploy/tailscale/.env
# Fill a new APP_KEY and the existing PostgreSQL credentials locally.
# Keep APP_URL=https://tuki-notebook.tail2b1906.ts.net.
make ops-prepare
OPERATIONAL_RUNTIME_CONFIRM=yes make ops-start
OPERATIONAL_RUNTIME_CONFIRM=yes make ops-migrate
make ops-verify
```

`deploy/tailscale/.env` se ignora y nunca debe confirmarse en el repositorio. El runtime se conecta a la red Docker de PostgreSQL existente, monta por separado los medios públicos de productos y los medios privados de Instagram Story, y crea `public/storage` dentro de la imagen inmutable para que las imágenes de productos continúen accesibles en la web. Los archivos privados de Story nunca se enlazan debajo de `public/storage`; solo se transmiten mediante la ruta temporal de medios firmada. Sail y el runtime operativo escriben esos medios con el grupo compartido del proyecto, `umask 0007`, archivos `0660` y directorios `0770`; el directorio montado `storage/app/private/instagram` debe conservar además `setgid` para que sus descendientes hereden el grupo. El entorno de testing redirige automáticamente el disco local a `storage/framework/testing/disks/local` y no debe escribir en este montaje. Un contenedor operativo dedicado ejecuta `php artisan schedule:work`, y la verificación del runtime exige que tanto la aplicación como el scheduler estén en ejecución. Cree y valide un respaldo de la base de datos antes de ejecutar migraciones o realizar el cambio.

El cambio de la ruta privada es deliberadamente independiente y requiere confirmación:

```bash
make route-status
TAILSCALE_ROUTE_CONFIRM=yes make route-cutover

# Immediate rollback to Te Pinta 2.0 on 127.0.0.1:8080:
TAILSCALE_ROUTE_CONFIRM=yes make route-rollback
```

Tailscale Serve continúa limitado a la tailnet. La única excepción pública es el handler de Funnel protegido por separado en HTTPS `:8443` para `/media/instagram`; su backend es `/media/instagram` en el runtime operativo, de modo que la eliminación de la ruta montada por Tailscale preserve la ruta de Laravel. Nunca publique `/`, `/admin` ni `/api`. Verifique `/up`, el inicio de sesión, las escrituras protegidas por CSRF, los assets compilados y `/manifest.webmanifest` desde el teléfono antes de considerar completo el cambio. La notebook debe permanecer activa con Docker y Tailscale en ejecución.

El entorno operativo establece `TRUSTED_PROXIES=REMOTE_ADDR` porque Tailscale Serve termina HTTPS y reenvía la solicitud al contenedor exclusivo del loopback mediante HTTP. Esto permite que Laravel respete el encabezado `X-Forwarded-Proto: https` del proxy directo y evita URLs de assets con contenido mixto sin confiar en proxies arbitrarios durante el desarrollo.

El cambio inicial a Te Pinta 3.0 se completó y verificó el 2026-08-14. `route.sh status` continúa siendo la autoridad porque un administrador puede realizar una reversión deliberada posteriormente.

En Android, conecte el teléfono a la misma tailnet, abra la URL HTTPS estable en Chrome, inicie sesión con **Remember me** y luego utilice **Add to Home screen**. El manifiesto inicia `/admin` como una aplicación standalone y expone un acceso directo **Crear pedido**. No se registra ningún service worker: las páginas autenticadas y las escrituras de pedidos nunca se almacenan en caché para su uso sin conexión.

`APP_URL` define el origen de Fortify/WebAuthn. Una passkey registrada en `localhost` no pertenece al origen `*.ts.net`; después del cambio, inicie sesión una vez con la contraseña y registre una passkey nueva desde la URL HTTPS estable.

## Política de dependencias

Priorice Laravel, React, Tailwind, shadcn/ui y las capacidades nativas de la plataforma. Agregue una dependencia de producción únicamente después de documentar la responsabilidad concreta, el costo de mantenimiento y seguridad, y por qué la composición con las herramientas existentes resulta insuficiente.
