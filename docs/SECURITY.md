# Seguridad

Laravel es la autoridad para autenticación, autorización, validación, elegibilidad del catálogo, precios, totales y estado del pedido. La visibilidad de la UI y los botones deshabilitados no son controles de seguridad.

## Límite administrativo

Todas las rutas de módulos bajo `/admin` usan `auth`, `verified`, `admin` y `admin.request.log`. Las solicitudes de clientes, pedidos y configuración también se autorizan mediante policies o verificaciones explícitas de administrador. El middleware web de Laravel conserva la protección CSRF.

Para cada escritura administrativa:

1. Resolver el recurso en el backend.
2. Autorizar la acción.
3. Validar un payload con lista de campos permitidos.
4. Ejecutar la acción o transacción específica.
5. Devolver un recurso explícito o una redirección.

Los pedidos administrativos pagados requieren un método de pago respaldado por enum (`cash`, `bank_transfer` o `debit_card`); los pedidos no pagados lo prohíben y eliminan. Un total cero calculado por el backend se normaliza como `not_required` sin método, por lo que un descuento del 100 % nunca inventa un pago. Los inputs de descuento administrativo contienen únicamente un tipo y valor validados. El backend calcula y persiste `discount_amount`; las solicitudes públicas prohíben los campos de descuento y método de pago.

## Edición segura de pedidos administrativos

- Las rutas de edición, habilitación de edición y actualización resuelven `Order` por su `public_id` inmutable, requieren el stack de middleware administrativo, autorizan la policy del pedido y usan solicitudes web protegidas por CSRF.
- `UpdateAdminOrderRequest` reutiliza la lista de campos permitidos del checkout y agrega un estado operativo validado por enum más el `expected_version` obligatorio. Se prohíben IDs, timestamps, metadatos de revisión, versión de edición y todos los campos de precio enviados por el cliente.
- `UpdateOrder` bloquea la fila y rechaza una versión obsoleta. Las acciones que solo cambian estado o pago también avanzan `edit_version`, lo que cierra la posibilidad de sobrescritura silenciosa entre superficies.
- Los pedidos `delivered` o `cancelled` se rechazan por defecto. Habilitar su edición exige un motivo de 3 a 300 caracteres y crea un permiso de sesión autenticada vinculado al administrador, pedido y versión. Autoriza una sola edición exitosa y nunca modifica el estado por sí mismo.
- Una edición actualiza únicamente el snapshot de dirección del pedido; no sobrescribe `customer.address`. Cambiar el cliente actualiza la referencia y los snapshots del pedido sin mutar el perfil privado del cliente.
- `order_revisions.changed_fields` almacena nombres de campos y un indicador de cambio económico, no valores. No contiene teléfonos, direcciones ni notas completas. El motivo de edición de un pedido cerrado tiene longitud limitada, debe ser breve y no debe contener información privada innecesaria.

## Límite financiero de Management

- `/admin/management` y todas sus mutaciones permanecen dentro de `auth`, `verified`, `admin`, CSRF y `admin.request.log`. Los Form Requests autorizan mediante las policies de Wallet, FinancialTransaction, ManagementOutflow, ManagementTransfer, DistributionRule y ManagementDistribution; ocultar una acción en React no autoriza nada.
- El backend vuelve a resolver cada wallet ID y valida su estado bajo lock. Los movimientos, egresos y transferencias nuevos rechazan billeteras archivadas; una reversión puede compensarlas únicamente a través de `ReverseFinancialTransaction`.
- El cliente envía un importe positivo y un tipo permitido. El backend determina el signo; React no puede enviar entries, un saldo, un administrador creador, una referencia de reversión ni otro tipo arbitrario.
- Para un egreso semántico, el backend resuelve la categoría, exige que esté activa y que pertenezca al tipo solicitado. El cliente no puede crear categorías, cambiar su tipo ni utilizar una inactiva a través del endpoint de egresos.
- La creación de `ManagementOutflow`, cabecera y entry negativa es atómica. Los egresos, transacciones y entries confirmados no exponen rutas de edición o eliminación, y PostgreSQL también rechaza su mutación directa.
- Una transferencia exige origen y destino distintos, resuelve ambas billeteras bajo lock ascendente y vuelve a comprobar que estén activas. El backend crea exactamente dos entries opuestas; el cliente no puede enviar signos ni impactos parciales.
- La creación de `ManagementTransfer`, cabecera y ambas entries es atómica e inmutable. Una reversión autorizada puede compensar cualquiera de sus billeteras aunque haya sido archivada después, sin reactivarla ni habilitar transferencias ordinarias nuevas.
- Crear una regla o versión valida nombre, destinos activos, billeteras no repetidas, orden, porcentajes positivos y suma exacta de `10.000` bps. El cliente no puede establecer el creador, mutar una versión anterior ni usar una wallet archivada.
- Activar o desactivar una regla requiere autorización administrativa y no modifica versiones ni aplicaciones históricas. Una distribución nueva vuelve a bloquear y validar regla, versión y billeteras aunque la UI las haya mostrado como elegibles.
- El cliente envía versión, importe positivo, fecha y descripción opcional; nunca envía entries ni snapshots autoritativos. El backend recalcula floor y remainder, rechaza toda allocation cero y persiste distribución, cabecera, N entries y snapshots en una única transacción.
- Distribuciones, snapshots y versiones confirmados son inmutables. Una reversión puede compensar destinos archivados porque copia los impactos originales con signo opuesto; no habilita su selección para nuevas versiones o aplicaciones.
- El saldo y los indicadores provienen del modelo de lectura del backend. Los retiros personales permanecen separados de los egresos operativos y las distribuciones no se clasifican como ingreso o egreso; React no recalcula estas reglas. Toda distribución posee una wallet origen real, debita el importe completo y acredita el mismo total.
- `GET /admin/management/audit` (`admin.management.audit.index`) reutiliza el mismo límite de autenticación, verificación y administrador. El backend valida período, billetera, severidad, estado y `type`, que puede ser un tipo de operación permitido o un código de finding conocido; React sólo presenta el read model autorizado.
- La auditoría no expone endpoints para editar o reparar ledger, cabeceras ni entidades semánticas. Un finding crítico permite inspeccionar el detalle; un pago pendiente sólo navega al reintento financiero ya autorizado y validado por Management.
- Conceptos, contrapartes y descripciones son datos administrativos. No se registran completos en logs y no existe endpoint público de Management.
- Cada comprobante pertenece a una `FinancialTransaction` resuelta por el backend. Carga, consulta, preview, descarga y tombstone permanecen bajo `auth`, `verified`, `admin`, CSRF, `admin.request.log` y las policies de Management; conocer o cambiar un `attachment_id` no evita esa autorización. Las rutas anidadas vuelven a comprobar que el attachment pertenezca a la cabecera indicada.
- Los bytes se guardan en el disco privado `local`, nunca bajo `storage/public`. `GET|POST /admin/management/transactions/{financialTransaction}/attachments` lista o carga; `GET|DELETE /admin/management/transactions/{financialTransaction}/attachments/{managementAttachment}` sirve bytes o crea el tombstone. El `GET` sólo acepta `disposition=inline|attachment`. La serialización devuelve nombre, MIME, tamaño y fecha, pero nunca `disk`, `path` ni información interna del filesystem.
- El backend acepta hasta `10 MB` y sólo PDF, JPEG, PNG y WEBP. Comprueba el MIME real y el tamaño del contenido; una extensión o un `Content-Type` declarado por el cliente no autoriza el archivo. EXE, JS, HTML, SVG, ZIP y cualquier formato no permitido se rechazan. Los límites de PHP y del proxy deben admitir al menos ese payload multipart o configurarse de forma coherente antes de producción.
- El path se genera con UUID bajo `management/attachments/{year}/{month}/`; no incorpora nombre de cliente, proveedor ni archivo original. La lectura comprueba tanto el registro como el prefijo permitido, rechaza traversal y responde `404` ante archivo ausente o ubicación inesperada sin revelar la ruta física.
- El nombre original es metadata no confiable: se normaliza para presentación y `Content-Disposition`, nunca se interpola en paths ni encabezados sin sanitización. Preview y descarga usan el MIME validado, `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`, una CSP sandbox cerrada y una disposición acorde al formato permitido.
- Una carga confirmada no puede reemplazarse. El tombstone exige un motivo de 3 a 240 caracteres, se completa una sola vez, registra quién y cuándo, bloquea futuras lecturas normales y conserva el blob físico. PostgreSQL bloquea la mutación de metadata y el `DELETE` físico. No existe eliminación silenciosa, purga automática ni traslado de comprobantes al revertir una operación.
- Storage y PostgreSQL no comparten atomicidad. La acción elimina el archivo recién escrito si falla la creación de metadata; si el write falla no crea la fila. Ese cleanup no se aplica a evidencia confirmada ni a su tombstone.

## Límite del checkout público

| Control                      | Implementación                                                                                                   |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| Identificadores de catálogo  | Slugs estables de producto/addon; se rechazan los IDs internos                                                   |
| Elegibilidad de producto     | Los Products deben estar disponibles, no eliminados lógicamente y pertenecer a una categoría activa no eliminada |
| Autoridad de precios         | `OrderPricingEngine` vuelve a cargar precios y configuración del servidor al crear                               |
| Autoridad de estado/pago     | El estado público es `pending_confirmation`; el pago siempre es `unpaid`                                         |
| Privacidad del cliente       | La coincidencia por teléfono ocurre solo dentro de `CreateOrder`; no existe un endpoint de consulta              |
| Protección contra duplicados | Clave de idempotencia obligatoria, HMAC con clave en reposo, huella del payload y restricción única              |
| Control de abuso             | Preview: 20 por minuto; creación: 5 por minuto mediante middleware throttle de Laravel                           |

Las solicitudes públicas no pueden enviar `customer_id`, `status`, `payment_status`, `total`, `shipping_fee` ni `combined_price`. Los IDs internos adicionales del catálogo se rechazan mediante validación estricta de claves de array. Las cantidades y los tamaños de arrays tienen límites.

El checkout normaliza el teléfono obligatorio y puede asociar el pedido a un cliente activo existente. Nunca devuelve su nombre, dirección, notas, historial ni ID interno; nunca precarga datos privados del perfil ni sobrescribe el perfil coincidente. El pedido almacena por separado los snapshots provistos por el visitante.

Los reintentos idempotentes exactos devuelven el pedido existente. Reutilizar una clave con un payload diferente falla la validación. El HMAC almacenado evita persistir tokens directos del checkout.

## Datos privados e históricos

- Las notas de clientes son exclusivamente administrativas. No se copian a pedidos, recursos públicos ni resúmenes de WhatsApp.
- Las notas de pedidos son independientes y pueden aparecer en el resumen del pedido o mensaje de WhatsApp.
- No registrar teléfonos completos, direcciones, notas, payloads de solicitudes, tokens, cookies ni secretos.
- El checkout público solo devuelve el ID de Order, estado, estado de pago, desglose nullable de precios cocidos base y crudos del backend, total/resumen del backend y URL de WhatsApp. Los campos técnicos `cooking_*` se conservan únicamente por compatibilidad histórica heredada y no representan un recargo vigente.
- Las vistas históricas usan snapshots del pedido; los datos actuales de clientes o catálogo no pueden reescribirlos silenciosamente.

## Eliminación recuperable

- La eliminación de pedidos y clientes es lógica. El listado de papelera y la restauración están protegidos por el middleware y las policies administrativas habituales.
- No existe una ruta ni UI de eliminación forzada. Eliminar o restaurar un cliente nunca propaga cambios, elimina ni reescribe pedidos asociados.
- Al restaurar un cliente, el backend vuelve a comprobar la unicidad activa del teléfono normalizado. Un conflicto se rechaza sin mutaciones; el sistema no fusiona registros ni borra teléfonos para permitir la restauración.
- Los recursos de restauración exponen únicamente los campos requeridos por sus pantallas administrativas de papelera.

## Límite de automatización de Instagram

- La página administrativa permanece protegida por middleware de `auth`, usuario verificado, administrador, CSRF y registro de solicitudes.
- n8n usa un bearer token independiente y de alta entropía proveniente de `N8N_AUTOMATION_TOKEN`; nunca recibe del POS una sesión de usuario, credenciales de base de datos ni un token de acceso de Meta.
- Las rutas de automatización tienen rate limit antes de autenticar el token. Los callbacks validan el estado actual de publicación y censuran el bearer token, el token de acceso y el material de la query firmada antes de persistir errores externos.
- Los archivos de historias usan datos de imagen decodificados y validados, una ruta generada en disco privado, normalización JPEG estándar, dimensiones/píxeles limitados y una ruta temporal firmada con nombre de archivo fijo. El controlador de medios rechaza rutas fuera de `instagram/stories/` y nunca revela rutas internas.
- Una publicación obsoleta o incierta no puede reintentarse automáticamente porque la publicación en Meta y el callback al POS no son una transacción atómica. Se marca para revisión manual con el fin de priorizar la prevención de duplicados.

La configuración operativa, el apagado ante incidentes, la rotación de credenciales y los backups se documentan en [instagram-automation.md](instagram-automation.md).

## Límite de Marketing

- `/admin/marketing` permanece dentro del grupo estándar de middleware de autenticación, verificación, administrador, CSRF y `admin.request.log`. Los Form Requests autorizan crear, actualizar o deshabilitar mediante la policy de Marketing; ocultar controles en React no es autorización.
- El input administrativo admite únicamente texto plano permitido, selección de Product, estado habilitado y valores de programación en horario local del negocio. El titular y el texto no pueden contener HTML confiable, y el backend convierte los valores locales a UTC en lugar de confiar en la interpretación de zona horaria del navegador.
- Las recomendaciones nuevas aceptan solo Products que actualmente puedan venderse al público. Las relaciones históricas existentes siguen visibles para administradores si luego un Product deja de estar disponible o se elimina lógicamente.
- PostgreSQL impide que se superpongan ventanas habilitadas durante escrituras concurrentes. La validación del cliente y una comprobación previa de la aplicación mejoran el feedback, pero no son el límite de integridad.
- Home pública recibe únicamente slug, nombre, descripción, URL pública de imagen y precios públicos actuales de Product, además de un titular y texto opcionales. Nunca recibe el ID de Featured Product, ID interno de Product, identidad del administrador, ruta de almacenamiento, atributos privados ni timestamps innecesarios de la base de datos.
- Marketing no modifica precios de Product ni el comportamiento de Orders. La CTA pública ingresa al carrito existente mediante slug; el checkout vuelve a validar de forma independiente la elegibilidad y los precios de Product.
- Marketing no posee credenciales ni rutas de escritura de Instagram, n8n o Meta. Instagram sigue siendo un límite administrativo y de automatización independiente.

## Instalación móvil y seguridad del origen

El manifest web permite instalar la UI administrativa autenticada, pero no debilita los límites normales de sesión, CSRF, autorización ni cookies seguras. Intencionalmente no existe un service worker: Te Pinta no almacena en caché HTML autenticado, datos de pedidos ni escrituras para uso sin conexión.

Instalar únicamente desde el origen HTTPS estable de Tailscale después de cambiar deliberadamente la ruta. `SESSION_SECURE_COOKIE=true`, una sesión cifrada en base de datos, `HttpOnly` y `SameSite=Lax` forman parte de la plantilla operativa. «Recordarme» puede facilitar la sesión del navegador, pero no reemplaza el bloqueo del dispositivo ni el cierre explícito de sesión en un teléfono compartido o perdido.

El runtime de Tailscale confía únicamente en su invocador directo como proxy inverso (`TRUSTED_PROXIES=REMOTE_ADDR`). El servicio permanece vinculado a loopback, por lo que Laravel puede respetar el esquema HTTPS reenviado por Tailscale Serve sin extender esa confianza al runtime de desarrollo expuesto a la LAN.

Fortify deriva de `APP_URL` el ID de relying party de la passkey y el origen permitido. Las passkeys registradas en `localhost` pertenecen a otro origen y no pueden autenticar en la URL `*.ts.net`; después de iniciar sesión con contraseña, registrar una passkey en el origen HTTPS estable en lugar de ampliar los orígenes permitidos.

## WhatsApp

El número de destino del negocio proviene de `whatsapp_order_phone`. No hay ningún teléfono real escrito directamente en el código. El valor inicial está vacío: el checkout persiste el pedido y luego la generación del enlace devuelve un error explícito de validación «no configurado» hasta que un administrador defina el número.

La aplicación solo genera una URL `wa.me`. No envía mensajes, no autentica al visitante ni infiere que el pedido fue confirmado.

## Lista de verificación de producción

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure cookies.
- [ ] PostgreSQL no es accesible públicamente y usa privilegios mínimos.
- [ ] Los secretos provienen de la plataforma de despliegue, no de Git.
- [ ] El destino de WhatsApp y el costo de envío están configurados deliberadamente.
- [ ] Los logs y el monitoreo tienen acceso controlado y no contienen payloads con PII.
- [ ] Pasan las pruebas de invitado/administrador, manipulación de precios, campos públicos, idempotencia y rate limit.
- [ ] El `APP_URL` operativo es el origen HTTPS estable y la configuración de cookies de sesión es segura.
- [ ] El cambio a Tailscale Serve se verifica explícitamente; el puerto de staging `8082` por sí solo no demuestra exposición.
