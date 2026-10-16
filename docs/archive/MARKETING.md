# Marketing

Marketing v1 es responsable de una decisión de presentación comercial: qué Product existente recomienda Te Pinta en la tienda pública. Conserva programaciones pasadas, actuales y futuras sin modificar Products, precios, Orders ni la automatización de Instagram.

## Alcance actual

| Capacidad | Contrato |
| --- | --- |
| Recomendación | Una ventana habilitada de Featured Product puede estar vigente en un instante determinado. |
| Programación | Se conservan los registros pasados, actuales y futuros. El inicio es obligatorio; el final puede quedar abierto. |
| Administración | `/admin/marketing` crea, edita, previsualiza y deshabilita recomendaciones. `/admin/settings/products` integra la programación del Product seleccionado mediante los mismos comandos de Marketing. No existe una acción habitual de eliminación física. |
| Inicio público | Muestra la recomendación sólo mientras su Product continúe disponible para la venta pública. |
| Dashboard | Muestra un resumen compacto de Marketing con el estado actual, próximo, no disponible o vacío. |

Esta versión no incluye descuentos, promociones, combos, cupones, campañas, precios personalizados, carga de banners ni integración con Instagram.

## Responsabilidad

`Marketing` es responsable de la programación de Featured Product, el título y el texto breve opcionales, las reglas temporales y su modelo de vista seguro para el público. `Products` sigue siendo responsable del nombre, la descripción, la imagen, la categoría, la disponibilidad y los precios públicos actuales del producto.

La relación no captura snapshots de los campos del catálogo. Por lo tanto, una recomendación histórica permanece vinculada a su Product, mientras que cada previsualización o presentación pública actual refleja deliberadamente los datos públicos actuales del Product.

La vista de Products consulta `GET /admin/marketing/products/{product}/featured`, una lectura autorizada propiedad de Marketing que devuelve sólo la programación de ese Product, su elegibilidad para nuevas recomendaciones y el horario comercial. La creación, edición y desactivación siguen usando las acciones existentes de Marketing, incluida la exclusión temporal global; Products no almacena otro indicador de destacado. Un Product no disponible conserva su historial, pero no puede iniciar una nueva recomendación.

Marketing e Instagram son dominios hermanos:

- Marketing decide **qué** promocionar o destacar.
- Instagram decide **cómo y cuándo** se publica el contenido de Story mediante su propia cola, el flujo de trabajo de n8n y el límite con Meta.

Marketing v1 no llama a Instagram ni almacena campos de Instagram. Consulte [ADR 0005](decisions/0005-marketing-and-instagram-are-separate-domains.md).

## Contrato temporal

Cada recomendación almacena un indicador de habilitación y un intervalo semiabierto:

```text
[starts_at, ends_at)
```

- `starts_at` es inclusivo y obligatorio.
- `ends_at` es exclusivo y opcional.
- Un `ends_at` nulo indica que el intervalo continúa indefinidamente.
- Los instantes se persisten en UTC.
- La entrada y presentación para administradores usa la zona horaria comercial configurada de Orders, actualmente `America/Argentina/Buenos_Aires` de forma predeterminada. La zona horaria del navegador nunca es la autoridad.

El estado administrativo derivado usa esta precedencia:

| Estado | Regla |
| --- | --- |
| Inactivo | `is_enabled = false` |
| Finalizado | habilitado y `ends_at <= now` |
| Programado | habilitado y `starts_at > now` |
| Activo | habilitado, `starts_at <= now` y (`ends_at IS NULL` o `now < ends_at`) |

No se necesita cron para mover una recomendación entre estos estados.

## Exclusión mutua y concurrencia

Dos intervalos habilitados no pueden superponerse. Los intervalos adyacentes son válidos porque el final es exclusivo:

```text
10:00 -> 18:00
18:00 -> 22:00
```

Un intervalo abierto habilitado se superpone con cualquier intervalo habilitado posterior. Los registros deshabilitados no bloquean la programación.

Las acciones de la aplicación realizan una comprobación previa y comprensible de superposición dentro de una transacción. PostgreSQL conserva la autoridad final de concurrencia mediante una restricción de exclusión GiST parcial sobre el `tstzrange(starts_at, coalesce(ends_at, infinity), '[)')` habilitado. Esto protege escrituras simultáneas, incluido el caso sin filas existentes que un bloqueo de fila por sí solo no puede serializar. SQLSTATE `23P01` se traduce al mismo error de validación comprensible que muestra la comprobación previa.

La base de datos también rechaza un final que no sea estrictamente posterior a su inicio.

## Elegibilidad pública

La actividad temporal y la visibilidad pública son conceptos distintos.

Una recomendación es visible públicamente sólo cuando se cumplen todas estas condiciones:

1. Su programación está habilitada y activa en este momento.
2. Su Product no está eliminado de forma lógica y está disponible.
3. La Category del Product está activa y no está eliminada de forma lógica.

Un Product no disponible o eliminado no borra ni deshabilita su historial de Marketing. La administración y el Dashboard pueden indicar que la recomendación temporalmente activa no es visible. Si el Product vuelve a estar disponible para la venta antes de que finalice el intervalo, recupera su visibilidad pública sin reescribir la recomendación.

Las recomendaciones nuevas sólo pueden seleccionar Products actualmente disponibles para la venta. Las asociaciones históricas existentes siguen siendo legibles aunque el Product deje de estar disponible o se elimine de forma lógica más adelante.

## Modelo de vista público y previsualización

Un único modelo visual seguro para el público alimenta tanto la tarjeta del Inicio público como la previsualización administrativa. Contiene únicamente:

- slug público, nombre, descripción, URL de imagen y precios públicos actuales del Product;
- título y texto breve opcionales de Marketing.

Nunca contiene el ID interno de Featured Product, el ID interno de Product, la identidad del administrador, la ruta interna de almacenamiento, metadatos privados ni marcas de tiempo innecesarias de la base de datos.

El componente de presentación compartido no tiene permisos administrativos ni comportamiento de mutación. Los metadatos administrativos, como estado, inicio y fin, controles de edición y advertencias, permanecen fuera de él. Cuando falta la imagen del producto, tanto la previsualización como la presentación pública usan el mismo reemplazo neutro.

## Inicio público y carrito

El Inicio público recibe de Laravel la recomendación canónica visible en ese momento. Si no existe una recomendación elegible, la propiedad es nula y no se muestra ningún espacio promocional vacío.

La llamada a la acción usa el `CartProvider` público existente y agrega una unidad mediante el slug de Product. Marketing no es responsable del almacenamiento de sesión, las cantidades, los precios ni el checkout. Los precios actuales son informativos; Orders vuelve a calcular la cotización autoritativa en el backend como siempre.

## Resumen del Dashboard

Dashboard consume un resumen compacto propiedad de Marketing; no reproduce las reglas de programación ni de elegibilidad.

- Un Product temporalmente activo y disponible para la venta se muestra como recomendación activa.
- Un Product temporalmente activo pero no disponible para la venta se muestra como no visible públicamente junto con el motivo.
- Cuando no hay una recomendación activa, se muestra la primera recomendación futura habilitada.
- Cuando no existe ninguna de las anteriores, el Dashboard muestra un estado vacío de Marketing.

El resumen enlaza a `/admin/marketing` y no convierte al Dashboard en responsable de los comandos de Marketing.

## Seguridad y registros

- Todas las rutas administrativas de Marketing usan el límite existente de autenticación, verificación, rol de administrador, CSRF y `admin.request.log`.
- Las Form Requests autorizan mediante la política de Marketing y validan un payload de texto plano con lista permitida.
- `headline` admite como máximo 80 caracteres y `short_copy`, 240; no se acepta HTML arbitrario.
- Las respuestas públicas usan únicamente el modelo visual seguro descrito anteriormente.
- Los registros estructurados de mutaciones usan identificadores internos para correlación y nunca serializan payloads de títulos o textos ni modelos completos de Product o Request.

## Pospuesto intencionalmente

- descuentos y precios especiales;
- promociones, reglas 2x1 y condiciones de campañas;
- combos más allá del comportamiento existente de Orders;
- cupones o códigos promocionales;
- cargas de banners dedicadas;
- flujos de trabajo de Marketing hacia Instagram;
- cambios en la automatización de Instagram Story.

Estas capacidades requieren un diseño de dominio separado. Un Featured Product es una decisión de presentación, no una regla económica.
