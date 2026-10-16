# Automatización de Instagram Stories

Te Pinta conserva en el POS las programaciones, los recursos, el estado de la cola y el historial de publicaciones. n8n Community Edition sólo reclama trabajo y ejecuta el flujo oficial de creación y publicación de contenedores de Meta. Nunca lee PostgreSQL ni recibe una sesión del panel.

> **Requisito previo para producción:** el POS necesita un origen HTTPS público al que Meta pueda acceder. La URL actual de Tailscale, limitada a la tailnet, no es un acceso público a Internet, por lo que no puede ser el origen final de los medios salvo que la ruta de medios se exponga por separado y de forma segura.

## Lista de verificación para la primera publicación

1. Desplegar la migración del POS, ejecutar el seeder de Instagram y configurar el scheduler de Laravel.
2. Establecer las variables de entorno del POS y reiniciar o almacenar en caché la configuración.
3. Cargar **Pedidos abiertos** y **Menú** en **Marketing → Instagram**.
4. Asignar cada recurso a su programación y luego habilitar las programaciones.
5. Desplegar n8n desde `ops/n8n/`, importar el flujo de trabajo y asociar ambas credenciales.
6. Configurar una aplicación Meta Business y un token de acceso de larga duración con permiso para publicar contenido.
7. Con la automatización global desactivada, ejecutar el flujo una vez y verificar que el reclamo sin trabajo responda con el `204` esperado. Luego, poner en cola una prueba controlada con **Enviar a cola** y activar brevemente los interruptores del despliegue y de la UI para que n8n pueda reclamarla.
8. Ejecutar el flujo de trabajo manualmente y verificar el historial. Sólo después de una prueba exitosa, activar el flujo de n8n y dejar habilitados los interruptores previstos.

Ejecutar los pasos de base de datos desde la versión desplegada del POS:

```bash
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\InstagramStoryAutomationSeeder' --force
```

El seeder es idempotente: crea o actualiza únicamente las dos programaciones iniciales inactivas y sin recursos, y nunca crea archivos de imagen.

## Arquitectura

```text
Laravel Scheduler                 n8n Community Edition                 Meta
        │                                   │                            │
        ├─ create pending publication       │                            │
        │                                   ├─ claim pending atomically  │
        │                                   ├─ receive signed JPEG URL   │
        │                                   ├─ POST /{ig-id}/media ─────►│
        │                                   ├─ POST /media_publish ─────►│
        │◄─ published/failed callback ──────┤                            │
        └─ retain queue and history          └─ retain orchestration logs │
```

| Responsable | Responsabilidad |
| --- | --- |
| POS | Recursos, programaciones, interruptor global, cálculo del vencimiento, filas únicas de cola, reclamo atómico, reintentos, entrega firmada de medios e historial. |
| n8n | Sondeo, solicitudes a la Graph API oficial y callbacks de resultados. |
| Meta | Creación del contenedor de medios y publicación de Instagram Story. |

n8n no tiene conexión a la base de datos del POS. Su único acceso al POS es la API de automatización protegida mediante bearer. Los campos de solicitud específicos de Meta permanecen en el flujo de n8n exportado, fuera del dominio del POS.

## Modelo de datos

| Tabla | Propósito | Protecciones importantes |
| --- | --- | --- |
| `instagram_automation_settings` | Interruptor global del operador respaldado por la base de datos. | Su valor predeterminado es desactivado, independientemente del interruptor de emergencia del entorno. |
| `instagram_story_assets` | Imágenes de Story almacenadas y validadas, junto con sus metadatos. | Ruta de disco privada; JPEG almacenado; indicador de actividad. |
| `instagram_story_schedules` | Nombre, hora local, días ISO de la semana, recurso seleccionado, indicador de actividad y orden. | Una programación activa exige un recurso activo; las filas con la misma hora usan `sort_order` y luego el ID. |
| `instagram_story_publications` | Cola junto con el historial operativo inmutable. | `(schedule_id, scheduled_for)` único, orden de cola capturado en snapshot, intentos, marcas de tiempo de resultado o archivo, posibilidad de reintento, ID de Meta, código de falla e indicador de revisión manual. |

El seeder inicial crea **Pedidos abiertos** a las 11:00 y **Menú** a las 18:30 para todos los días, sin recursos y desactivados. No inventa archivos multimedia.

## Reglas de cola e idempotencia

- El scheduler se ejecuta cada minuto y sólo crea filas `pending` que hayan vencido en la zona horaria comercial configurada.
- La restricción única de la base de datos sobre programación e instante exacto de vencimiento vuelve inocuas las ejecuciones repetidas del scheduler.
- `GET /pending` bloquea una fila elegible mediante `FOR UPDATE SKIP LOCKED`, cambia `pending → processing`, incrementa `attempts` y sólo entonces la devuelve.
- Una segunda ejecución de n8n no puede reclamar la misma fila mientras la primera sea su propietaria.
- Los callbacks sólo aceptan el estado `processing`; las transiciones inválidas o repetidas se rechazan.
- Una falla anterior a la publicación marcada como reintentable puede volver a `pending` hasta alcanzar `INSTAGRAM_AUTOMATION_MAX_ATTEMPTS`.
- Las filas con la misma hora se reclaman según el instante de vencimiento, el orden de cola capturado en snapshot y luego el ID. La clave de idempotencia continúa siendo la programación junto con el instante de vencimiento.

### Resultado ambiguo de publicación

Meta no proporciona una clave de idempotencia definida por la aplicación que vuelva atómica la solicitud completa a `media_publish` junto con el callback al POS. Puede producirse un timeout de red después de que Meta acepte la publicación pero antes de que n8n reciba la respuesta; Meta también puede publicar correctamente aunque falle el callback al POS.

Por lo tanto, el sistema prioriza **no publicar un duplicado**:

1. Un resultado fallido o incierto de `media_publish` se informa con `retryable: false`.
2. Si Meta devuelve un ID de medio pero falla el callback al POS, el flujo se detiene con un error explícito y NO DEBE reintentarse.
3. Una publicación que permanezca en `processing` después del timeout se marca como `failed` con `requires_manual_review=true`; no vuelve automáticamente a `pending`.
4. Un operador compara la ejecución de n8n, la cuenta de Instagram, la hora programada, la previsualización del recurso y cualquier ID conocido del contenedor o medio. Sólo el operador puede crear una nueva publicación manual después de confirmar su ausencia.

Esto acepta deliberadamente la posibilidad de omitir una Story a cambio de evitar un duplicado accidental.

## Administración del historial

El historial administrativo se ordena del más reciente al más antiguo, y se filtra y pagina en el POS. Archivar establece `archived_at`; nunca elimina la fila ni los datos de conciliación con Meta. La acción conservadora de 30 días sólo incluye filas publicadas, fallidas y canceladas cuya marca de tiempo de finalización coincidente sea antigua. Excluye filas pendientes, en proceso y cualquier fila con `requires_manual_review`.

El reintento administrativo es una transición bloqueada en el backend. Exige una fila fallida marcada explícitamente como reintentable, intentos restantes, ausencia del indicador de revisión manual y ausencia de un ID de medio de Meta. Las filas en proceso o ambiguas no pueden reintentarse ni cancelarse desde el panel. La cancelación se limita a filas pendientes no reclamadas o filas fallidas no ambiguas. Los indicadores de capacidad del frontend sirven de orientación; el backend repite todas las comprobaciones después de obtener el bloqueo.

Los recursos referenciados por programaciones o publicaciones no pueden eliminarse. Un recurso usado por una programación activa no puede desactivarse ni reemplazarse; primero se deben reasignar o desactivar esas programaciones.

## Entorno del POS

Agregar estos valores al entorno del POS desplegado, nunca al código del frontend:

```dotenv
INSTAGRAM_AUTOMATION_ENABLED=false
INSTAGRAM_AUTOMATION_TIMEZONE=America/Argentina/Cordoba
INSTAGRAM_AUTOMATION_MAX_ATTEMPTS=3
INSTAGRAM_AUTOMATION_PROCESSING_TIMEOUT_MINUTES=20
INSTAGRAM_AUTOMATION_MEDIA_URL_TTL_MINUTES=90
INSTAGRAM_AUTOMATION_MEDIA_DISK=local
INSTAGRAM_AUTOMATION_PUBLIC_MEDIA_BASE_URL=https://tuki-notebook.example.ts.net:8443
N8N_AUTOMATION_TOKEN=
```

| Variable | Significado |
| --- | --- |
| `INSTAGRAM_AUTOMATION_ENABLED` | Interruptor de emergencia del entorno. Mantenerlo en `false` hasta verificar el despliegue. Tanto éste como el interruptor de la UI deben estar activados para reclamar trabajo. |
| `INSTAGRAM_AUTOMATION_TIMEZONE` | Zona horaria comercial usada para interpretar las programaciones. |
| `INSTAGRAM_AUTOMATION_MAX_ATTEMPTS` | Cantidad máxima de reclamos para fallas reintentables. |
| `INSTAGRAM_AUTOMATION_PROCESSING_TIMEOUT_MINUTES` | Antigüedad a partir de la cual una fila en proceso sin resolver se convierte en una falla que requiere revisión manual. |
| `INSTAGRAM_AUTOMATION_MEDIA_URL_TTL_MINUTES` | Tiempo de vida de la URL firmada del JPEG. Debe cubrir el intento de descarga de Meta sin volverse permanente. |
| `INSTAGRAM_AUTOMATION_MEDIA_DISK` | Disco privado de Laravel que contiene los medios de Story. |
| `INSTAGRAM_AUTOMATION_PUBLIC_MEDIA_BASE_URL` | Origen HTTPS público dedicado que se usa sólo cuando Meta descarga medios temporales y firmados de Story. |
| `N8N_AUTOMATION_TOKEN` | Secreto bearer independiente y de alta entropía, compartido únicamente con la credencial del POS en n8n. |

Generar el token de automatización con un gestor de contraseñas o `openssl rand -hex 32`. No reutilizar `APP_KEY`, una contraseña de usuario ni el token de Meta. Después de cambiar los valores del despliegue, reiniciar el runtime y reconstruir la caché de configuración de Laravel según lo requiera el proceso de despliegue.

`POS_BASE_URL` y el origen público de medios son deliberadamente diferentes. n8n llama al runtime operativo mediante el alias de la red Docker privada `http://tepinta-operational`; el puerto del host permanece enlazado a `127.0.0.1:8082` y no se expone a la LAN. `host.docker.internal:8082` no puede alcanzar desde un contenedor un enlace limitado al loopback del host, por lo que no se usa en este camino; Meta recibe el origen HTTPS configurado para medios. Laravel firma la ruta relativa completa del medio y su consulta —recurso, nombre de archivo fijo, vencimiento y firma— y luego adjunta el origen público dedicado. La ruta valida una firma relativa, por lo que no es necesario cambiar el host; modificar el recurso, la ruta, el vencimiento o la consulta firmada también invalida la solicitud.

El host debe ejecutar el scheduler de Laravel una vez por minuto. El runtime operativo de Compose proporciona un servicio `scheduler` dedicado que ejecuta `php artisan schedule:work`. Para un host convencional sin Compose, la entrada cron equivalente es:

```cron
* * * * * cd /path/to/tepinta3.0 && php artisan schedule:run >> /dev/null 2>&1
```

Verificar con `php artisan schedule:list` y el estado de salud del contenedor del scheduler; no ejecutar una segunda implementación del scheduler dentro de n8n.

## Contrato de la API de automatización

Todas las solicitudes a la API requieren `Authorization: Bearer <N8N_AUTOMATION_TOKEN>` y tienen limitación de frecuencia.

### Reclamar una publicación

```http
GET /api/automation/instagram/stories/pending
```

Devuelve `204 No Content` cuando no hay trabajo elegible. Un reclamo atómico exitoso devuelve:

```json
{
    "id": 123,
    "scheduled_for": "2026-08-24T11:00:00-03:00",
    "attempt": 1,
    "asset": {
        "name": "Pedidos abiertos",
        "mime_type": "image/jpeg",
        "width": 1080,
        "height": 1920,
        "media_url": "https://pos.example.com/media/instagram/…/story.jpg?expires=…&signature=…"
    }
}
```

La URL del medio es una ruta firmada temporal de Laravel, restringida al recurso seleccionado y a un nombre de archivo fijo. Devuelve el tipo MIME correcto, impide el recorrido de rutas, oculta las rutas de almacenamiento y no requiere una sesión para que Meta pueda descargarla.

### Registrar un resultado exitoso

```http
POST /api/automation/instagram/stories/{publication}/published
Content-Type: application/json

{"container_id":"…","media_id":"…"}
```

### Registrar una falla

```http
POST /api/automation/instagram/stories/{publication}/failed
Content-Type: application/json

{"error":"sanitized summary","retryable":true,"ambiguous":false}
```

`ambiguous` es opcional y su valor predeterminado es `false`. El flujo envía `ambiguous: true` cuando `media_publish` puede haber llegado a Meta pero no produjo un resultado confiable; el POS fuerza entonces una falla terminal con revisión manual independientemente de `retryable`. El POS vuelve a sanear y limitar los errores externos antes de almacenarlos. Nunca se deben usar tokens, encabezados de autorización, URL firmadas de medios, trazas de pila ni payloads sin procesar de n8n como valor de `error`.

## Gestión de medios

La UI administrativa acepta cargas JPEG o PNG reales de hasta 8 MiB y valida el MIME del archivo junto con las dimensiones de la imagen decodificada, en lugar de confiar sólo en la extensión. Los archivos PNG se aplanan sobre blanco y se normalizan a JPEG al cargarlos porque la documentación actual de Content Publishing de Meta admite imágenes JPEG, no PNG, para publicar imágenes. La API siempre entrega a n8n una URL firmada para ese JPEG almacenado.

Las imágenes cargadas se copian al almacenamiento privado persistente del servidor en `storage/app/private/instagram`; la base de datos conserva esa ruta administrada y nunca la ubicación original del equipo desde el que se eligió el archivo. Por eso mover o borrar la descarga original no afecta un recurso que se haya cargado correctamente. El directorio se comparte entre Sail y el runtime operativo mediante el grupo del proyecto: los procesos usan `umask 0007`, Flysystem crea archivos `0660` y directorios `0770`, y el directorio montado conserva el bit `setgid`. No se debe enlazar este árbol desde `public/storage` ni habilitar permisos para otros usuarios; Meta sólo accede a un JPEG mediante la ruta temporal firmada.

Las pruebas no usan ese árbol. Cuando `APP_ENV=testing`, el disco local apunta a `storage/framework/testing/disks/local`, incluidos los fixtures de Playwright, para que una ejecución E2E nunca cree ni reemplace medios privados de development.

Se recomienda 1080 × 1920 (9:16). Se conservan otras dimensiones válidas y la UI muestra una advertencia cuando la relación de aspecto no se aproxima a 9:16. Confirmar la previsualización antes de enviar a cola porque esta primera versión no recorta ni genera diseños.

## Despliegue de n8n Community Edition

El despliegue independiente reside en `ops/n8n/`; no modifica el Compose raíz de Sail ni el runtime de `deploy/tailscale/`.

```bash
cd ops/n8n
cp .env.example .env
openssl rand -hex 32 # paste into N8N_ENCRYPTION_KEY
# Fill POS_BASE_URL, META_GRAPH_API_VERSION, and META_INSTAGRAM_USER_ID.
docker compose config
docker compose pull
docker compose up -d
docker compose ps
docker compose logs --tail=100 n8n
```

El archivo de Compose enlaza n8n únicamente a `127.0.0.1:5678`, persiste `/home/node/.n8n` en el volumen `n8n-data`, deshabilita diagnósticos y plantillas, habilita la depuración de ejecuciones y no se une a la red de base de datos del POS. Acceder de forma segura mediante un túnel SSH:

```bash
ssh -L 5678:127.0.0.1:5678 user@server
```

Luego abrir `http://localhost:5678`. Si se expone el editor mediante un proxy inverso, primero configurar HTTPS, el valor final de `N8N_HOST`, `N8N_PROTOCOL=https`, `N8N_EDITOR_BASE_URL`, los saltos de proxy, el firewall y los controles de acceso. El flujo de trabajo no tiene un webhook entrante, por lo que no es necesario exponer públicamente el editor.

El flujo sólo lee valores de conexión no secretos desde el entorno del contenedor. Por lo tanto, se requiere `N8N_BLOCK_ENV_ACCESS_IN_NODE=false`. Limitar el acceso al editor de n8n al propietario de confianza: quien pueda modificar nodos HTTP o Code también puede leer valores del entorno del contenedor. Los tokens permanecen en credenciales cifradas de n8n y no están presentes en `ops/n8n/.env.example` ni en el flujo exportado.

## Importar y configurar el flujo de trabajo

1. Importar `ops/n8n/workflows/instagram-stories-publisher.json`.
2. Crear una credencial **Header Auth** llamada `Te Pinta POS Automation`:
    - Nombre: `Authorization`
    - Valor: `Bearer <same N8N_AUTOMATION_TOKEN as the POS>`
3. Crear otra credencial **Header Auth** llamada `Meta Instagram Graph API`:
    - Nombre: `Authorization`
    - Valor: `Bearer <long-lived Meta access token>`
4. Asignar la credencial del POS a **Claim Next Publication**, a ambos callbacks de resultado y a ambos callbacks de falla.
5. Asignar la credencial de Meta a **Create Story Container**, **Check Container Status 1–5** y **Publish Story Container**.
6. Ejecutar manualmente el flujo mientras la automatización global continúa desactivada; el reclamo debe finalizar con `204`.
7. Crear en el POS un ítem manual confirmado en la cola, habilitar los interruptores de la UI y del entorno, y luego ejecutar el flujo una vez.
8. Verificar la Story en Instagram y la fila `published` del historial antes de activar el disparador programado.

El flujo importado comienza inactivo. Sondea cada dos minutos, reclama como máximo una publicación, envía `media_type=STORIES` junto con la `image_url` firmada, espera hasta cinco minutos a que el contenedor informe `FINISHED`, publica el `creation_id` devuelto e informa los ID al POS. Una falla del contenedor o de disponibilidad anterior a `media_publish` puede ser reintentable; una publicación incierta nunca se reintenta automáticamente.

## Configuración de Meta

El flujo incluido usa de forma predeterminada la API de Instagram con el host de Facebook Login, `https://graph.facebook.com`. Usar un único modelo de inicio de sesión de Meta de forma coherente; el host, el token, la configuración de la aplicación y los permisos deben pertenecer a ese mismo modelo.

### Camino de Facebook Login usado por el host predeterminado

1. Convertir el perfil de Instagram de Te Pinta en una cuenta **Business**. Actualmente, Meta limita las Stories publicadas mediante la API a cuentas Business.
2. Vincularla con la Facebook Page correspondiente.
3. Crear una aplicación Meta Business y agregar los productos necesarios de Instagram/Facebook Login.
4. Solicitar los permisos de publicación actuales para este camino de inicio de sesión, incluidos `instagram_basic` e `instagram_content_publish`, además de los permisos de Page que Meta exige para descubrir la cuenta vinculada.
5. Completar App Review/Advanced Access cuando se publique en una cuenta que no pertenezca a un rol de la aplicación.
6. Descubrir y verificar el ID de usuario de Instagram de la cuenta Business vinculada; colocarlo en `META_INSTAGRAM_USER_ID`.
7. Generar y conservar de forma segura el token adecuado y de larga duración de usuario o usuario del sistema. Almacenarlo únicamente en la credencial de n8n.
8. Seleccionar una versión explícita y compatible de Graph en `META_GRAPH_API_VERSION`; nunca seguir silenciosamente un endpoint sin versión.

Si se usa **Instagram Login**, establecer `META_GRAPH_BASE_URL=https://graph.instagram.com` y seguir los permisos actuales de ese modelo, como `instagram_business_basic` e `instagram_business_content_publish`. No enviar un token de Instagram Login al host de Facebook Login ni mezclar nombres de permisos.

### Referencias oficiales verificadas

Estos detalles se volvieron a comprobar con la documentación oficial de Meta el 23 de agosto de 2026. Volver a verificarlos antes de la primera activación en producción y antes de cada actualización de la versión de Graph:

- [Guía de Content Publishing](https://developers.facebook.com/docs/instagram-platform/content-publishing/)
- [Crear un contenedor de medios (`/{ig-user-id}/media`)](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media/)
- [Publicar un contenedor de medios (`/{ig-user-id}/media_publish`)](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media_publish/)
- [Estado del contenedor](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-container/)
- [Límite de publicación de contenido](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/content_publishing_limit/)
- [API de Instagram con Facebook Login](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-facebook-login/)
- [API de Instagram con Instagram Login](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/)

Restricciones actuales relevantes para esta versión: los medios deben poder descargarse públicamente mientras Meta crea el contenedor; la publicación de imágenes acepta JPEG; las Stories están disponibles para cuentas Business; la publicación crea primero el contenedor y luego usa `media_publish`; y las cuentas están limitadas actualmente a 100 publicaciones mediante API en un período móvil de 24 horas. Meta puede cambiar permisos, versiones y límites, por lo que `content_publishing_limit` y la referencia del endpoint de la versión seleccionada deben considerarse las autoridades en tiempo de ejecución.

## Cargar las primeras dos imágenes

1. Abrir **Marketing → Instagram → Biblioteca** como administrador.
2. Cargar el archivo diseñado **Ya estamos tomando pedidos**, darle un nombre claro y seleccionar `orders_open`.
3. Cargar **Menú** y seleccionar `menu`.
4. Confirmar ambas previsualizaciones, sus dimensiones y las advertencias de relación de aspecto.
5. Editar **Pedidos abiertos**, asignar el primer recurso, verificar 11:00/todos los días y habilitarlo.
6. Editar **Menú**, asignar el segundo recurso, verificar 18:30/todos los días y habilitarlo.
7. Dejar **Nueva variedad** en la biblioteca como `new_product`; usar **Enviar a cola** sólo cuando sea necesario.

Reemplazar un recurso actualiza sus usos futuros. Las filas de publicaciones existentes conservan la relación con el recurso y el historial. La eliminación se rechaza mientras existan programaciones o publicaciones que dependan de él; se debe desactivar en su lugar.

## Procedimiento de prueba seguro

1. Mantener inactivas las filas programadas o elegir una hora que no pueda vencer durante la prueba.
2. Asegurarse de que los interruptores globales del entorno y de la UI estén activados sólo durante la prueba.
3. Seleccionar un recurso verificado y elegir **Enviar a cola**.
4. Leer y aceptar la confirmación; el botón crea trabajo `pending` y no contacta a Meta.
5. Ejecutar manualmente el flujo de n8n.
6. Verificar exactamente una Instagram Story, una fila `published` en el historial del POS, `attempts=1` y los ID almacenados del contenedor y el medio.
7. Si el flujo informa un estado ambiguo de publicación, no presionar reintentar. Seguir el procedimiento de conciliación anterior.

## Apagado inmediato

Para una detención del operador, desactivar **Automatización de Instagram** en la UI. Ante un incidente o un token de n8n comprometido, también establecer `INSTAGRAM_AUTOMATION_ENABLED=false` en el runtime del POS y recargar su configuración. El endpoint de reclamo dejará de devolver trabajo.

Si las credenciales pueden estar comprometidas:

1. Desactivar el flujo de n8n.
2. Rotar `N8N_AUTOMATION_TOKEN` en el POS y en la credencial del POS de n8n.
3. Revocar o rotar el token de Meta en Meta y luego actualizar la credencial de Meta en n8n.
4. Revisar el historial reciente del POS, las ejecuciones de n8n y la actividad de Instagram antes de volver a habilitar.

Detener n8n es una medida adicional de contención:

```bash
cd ops/n8n && docker compose stop n8n
```

No eliminar filas pendientes o en proceso para ocultar un incidente; constituyen el registro de auditoría y recuperación.

## Copias de seguridad y restauración

El volumen nombrado `n8n-data` contiene la base de datos SQLite, las credenciales cifradas, el estado del flujo y la configuración de la instancia. La copia de seguridad sólo puede usarse con la `N8N_ENCRYPTION_KEY` correspondiente.

```bash
cd ops/n8n
docker compose stop n8n
docker run --rm \
  -v n8n_n8n-data:/data:ro \
  -v "$PWD/backups:/backup" \
  alpine:3.22 \
  tar -czf "/backup/n8n-$(date +%F-%H%M%S).tgz" -C /data .
docker compose start n8n
```

Confirmar primero el nombre real del volumen con `docker volume ls`; Compose le antepone el nombre del proyecto. Almacenar copias cifradas fuera del servidor tanto del archivo como de la clave de cifrado, separadas del repositorio. Probar periódicamente la restauración en una instancia aislada de n8n. La base de datos del POS y los medios privados de Story requieren su propio proceso de copia de seguridad de la aplicación.

Restaurar únicamente mientras n8n está detenido, en un volumen de reemplazo vacío, y luego iniciar con la clave de cifrado original. Nunca combinar dos directorios de datos SQLite activos.

## Actualización de versiones de n8n y Graph

### n8n

1. Realizar una copia de seguridad del volumen y de la clave de cifrado.
2. Leer las notas de versión y de cambios incompatibles de n8n.
3. Cambiar `N8N_IMAGE` a una versión exacta revisada; no usar `latest`.
4. Ejecutar `docker compose pull && docker compose up -d`.
5. Revisar los registros, ejecutar la auditoría de seguridad de n8n y realizar manualmente un reclamo sin trabajo.
6. Ejecutar una prueba controlada de Story antes de restaurar las programaciones normales.

La reversión implica restaurar en conjunto la copia compatible del volumen y la imagen exacta anterior. Las migraciones de base de datos realizadas por una versión más nueva de n8n pueden impedir que alcance con volver a cambiar la etiqueta de la imagen.

### Meta

1. Revisar el registro de cambios de versiones de Meta y todas las referencias de endpoints enlazadas anteriormente.
2. Probar la nueva versión con una publicación manual controlada.
3. Cambiar `META_GRAPH_API_VERSION` sólo después del resultado exitoso y reiniciar n8n.
4. Rotar la credencial de Meta independientemente de los cambios de versión; nunca colocar el token en el JSON del flujo.

## Resolución de problemas

| Síntoma | Comprobación | Acción segura |
| --- | --- | --- |
| El reclamo devuelve `401` | Los valores bearer del POS y n8n difieren o el token está vacío. | Rotar y actualizar ambos lados; nunca registrar el token. |
| El reclamo devuelve `204` | Interruptores del entorno o UI, hora de vencimiento, indicadores de recurso o actividad de la programación, intentos. | Corregir la configuración; no crear manualmente filas duplicadas. |
| Meta no puede obtener `media_url` | DNS/TLS público, vencimiento, desajuste del reloj, firewall, origen limitado a la tailnet. | Probar la URL firmada exacta desde fuera de la red privada sin una sesión. No hacer público todo el directorio de almacenamiento. |
| El contenedor rechaza la imagen | MIME, codificación JPEG real, tamaño del archivo, versión de Graph. | Volver a cargar o normalizar; revisar las restricciones actuales del endpoint de medios de la versión seleccionada. |
| Error de permisos | Tipo de cuenta, vínculo con la Page, modelo de inicio de sesión del token, alcances, App Review. | Corregir la aplicación o el token de Meta; no cambiar a API privadas ni a automatización del navegador. |
| La publicación permanece en proceso | Falla de n8n o del callback. | Esperar a que el timeout marque la revisión manual y luego conciliar; nunca volver a poner en cola automáticamente. |
| Falla el callback de publicación | Disponibilidad, token o transición de estado del POS. | No reintentar el flujo porque Meta puede haber publicado. Conciliar manualmente. |
| Error de límite de frecuencia | `content_publishing_limit`, medios recientes publicados mediante API, otras herramientas. | Pausar los reclamos y esperar la cuota; no repetir reintentos en bucle. |
| n8n no puede descifrar credenciales | Cambió la clave de cifrado o el volumen es incorrecto. | Restaurar la clave y la copia correspondientes; no recrear credenciales a ciegas durante un incidente. |

La única integración compatible es HTTP junto con la API oficial de Meta. Selenium, Puppeteer, las API privadas de Instagram, la automatización de inicio de sesión en navegador y la emulación de dispositivos están intencionalmente fuera de alcance.
