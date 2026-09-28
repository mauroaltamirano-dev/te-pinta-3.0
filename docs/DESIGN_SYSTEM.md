# Sistema de diseño

Te Pinta utiliza shadcn/ui como capa accesible de primitivas técnicas, no como identidad visual. Las experiencias pública y administrativa comparten tokens, pero tienen composiciones y densidades diferentes.

## Lineamientos visuales

- **Público:** pedidos de comida con enfoque mobile-first, basados en la referencia provista de Food App: imágenes de producto destacadas, jerarquía clara de categorías y productos y llamadas directas a realizar el pedido.
- **Administración/POS:** interfaz operativa con enfoque desktop/tablet-first, basada en la referencia oscura provista de Food POS: navegación densa pero legible, catálogo a la izquierda, pedido actual a la derecha y reconocimiento rápido de estados.

Nunca se copian nombres de plantillas, productos de ejemplo ni contenido ficticio. La referencia canónica para implementar Products es `docs/design/references/admin-products.png`; su composición se adapta a Te Pinta en lugar de copiarse como un dashboard genérico.

## Responsabilidades

| Capa | Ubicación | Regla |
| --- | --- | --- |
| Tokens y tema de Tailwind | `resources/css/app.css` | Fuente única para decisiones de color, tipografía, espaciado, radio, sombra, breakpoint y estado |
| Primitivas de shadcn | `resources/js/components/ui/` | Primitivas accesibles de bajo nivel; evitar comportamiento de funcionalidad o negocio |
| UI compartida de Te Pinta | `resources/js/components/shared/` | Composición reutilizable de marca, como la presentación de dinero o estados |
| UI de funcionalidad | `resources/js/features/<feature>/components/` | Componentes específicos del negocio que pertenecen a una funcionalidad |
| Layouts | `resources/js/layouts/` | Estructuras de las experiencias pública, administrativa/POS y de autenticación |

## Reglas de tokens

- Consumir variables semánticas (`background`, `surface`, `primary`, `destructive`, etc.) en lugar de colores directos en los componentes.
- Añadir un token solo cuando represente una decisión de diseño reutilizable.
- Preservar el foco visible, el contraste, la navegación por teclado, las preferencias de movimiento reducido y la usabilidad de los objetivos táctiles.
- Tratar el comportamiento responsive como una recomposición, no como una reducción proporcional.
- Las páginas públicas parten del ancho móvil; administración/POS parte de flujos para tablet o escritorio y debe seguir siendo usable en anchos razonablemente menores.

### Identidad pública

La paleta pública vive en `resources/css/app.css`: `public-ink` (`#1b1b1b`), `public-canvas` (`#b84f3f`) y `public-earth` (`#f1f1f1`). Home y la navegación mantienen el canvas rojo; `/menu` usa `public-earth` como lienzo y las tarjetas de producto reutilizan el rojo `public-canvas` con tokens locales legibles para texto secundario y divisores. Las acciones sobre las tarjetas usan `public-earth-ink` (`#7d291f`) con texto blanco como rojo más profundo para distinguirlas del fondo sin recurrir a negro. El hero de `/menu` puede usar el asset transparente `public/images/brand/bg-empanada.png` como ilustración decorativa de bajo contraste, detrás del contenido e ignorada por tecnologías de asistencia e interacción. Los roles semánticos (`background`, `foreground`, `primary`, `card`, `muted`, `border` y `ring`) se aplican mediante `.public-theme`, no mediante una segunda hoja de estilos ni modificando `admin-*`. `public-earth-ink` es el rojo de soporte para texto pequeño sobre earth y acciones; el canvas no alcanza ese contraste para texto pequeño. El texto sobre canvas usa blanco (`4.97:1`); ink sobre earth alcanza `15.25:1`.

`PublicLayout` aplica el tema a la navegación, el contenido y el pie de página. La cabecera utiliza el wordmark completo blanco elegido por el negocio (`logo-completo-blanco.png`) sobre rojo; el pie conserva el monograma blanco original, todos almacenados localmente en `public/images/brand/`. Home presenta el hero fotográfico suministrado por el negocio a ancho completo, títulos serif y una sola acción principal hacia `/menu`; las superficies y el espaciado separan las secciones sin las anteriores líneas horizontales completas ni los anillos del placeholder. Estos assets son identidad editorial del sitio, no fotografías asignadas a variedades del catálogo.

`FeaturedProductCard` aplica el tema localmente porque se reutiliza en la vista previa administrativa de Marketing. Home muestra la recomendación sólo cuando el backend la entrega; sin una recomendación elegible, muestra un enlace fotográfico al menú, no una oferta o un producto ficticio. La imagen administrada del Product se conserva en su tarjeta. La presentación sigue usando `Button`, `formatPesos` y el `CartProvider` existentes; ni el layout ni Home calculan precios, mantienen un catálogo paralelo o crean pedidos.

## Reutilización de componentes

Antes de añadir un componente:

1. Revisar `components/ui` en busca de una primitiva existente.
2. Revisar `components/shared` en busca de una composición de Te Pinta.
3. Revisar la funcionalidad propietaria.
4. Añadir un wrapper solo cuando centralice una política visual o de comportamiento real.

No duplicar botones, diálogos, inputs, tarjetas de producto, presentaciones de dinero ni insignias de estado con nombres diferentes.

## Flujo de implementación con Figma

Cuando estén disponibles la integración con Figma y los nodos exactos:

1. Recuperar el contexto de diseño del nodo exacto.
2. Capturar una imagen del frame exacto.
3. Reutilizar los assets exportados o de referencia en lugar de aproximarlos.
4. Asignar las decisiones visuales a tokens semánticos y componentes compartidos.
5. Implementar la composición responsive.
6. Verificar viewports representativos e interacciones mediante capturas de Playwright.
7. Registrar aquí las decisiones significativas sobre tokens o componentes.

## Composición de gestión de Products

`resources/js/pages/admin/products/index.tsx` se integra con la estructura administrativa oscura global mediante `resources/js/layouts/settings-layout.tsx`, responsable del panel de navegación local de Settings para General y Products. La funcionalidad Products preserva la jerarquía de la referencia: filtros y gestión de categorías en la parte superior, seguidos por una grilla responsive y densa de tarjetas de producto encabezada por la tarjeta para agregar un producto.

Las tarjetas de producto usan imágenes circulares, un placeholder de imagen coherente, filas compactas de precio base, insignias de estado vegetariano/no disponible y una única acción clara de edición. La edición de productos y categorías utiliza diálogos enfocados y accesibles para mantener los errores de validación junto a sus campos.

Los cambios se guardan por producto o categoría. Cerrar o cancelar un diálogo descarta su estado local sin guardar; los controles globales Guardar/Descartar no se reprodujeron intencionalmente porque el servidor persiste cada recurso de forma independiente y un buffer global ficticio representaría incorrectamente ese comportamiento.

### Paleta de superficies administrativas

La estructura administrativa utiliza una paleta semántica reducida obtenida de los nodos de Figma verificados. Los componentes consumen los tokens de tema `admin-*` definidos en `resources/css/app.css`; no deben introducir valores oscuros o coral casi duplicados.

| Nodo Figma verificado | Responsabilidad |
| --- | --- |
| `177:735` | Geometría del panel lateral global y estado activo |
| `196:1985` | Composición administrativa completa de 1194 × 834 y lienzo |
| `201:2202` | Jerarquía y superficies del panel Settings |
| `186:100` | Superficie, borde y banda de acción de la tarjeta de producto |
| `175:888` | Tratamiento de selección de navegación |
| `177:2031` | Contenido de fila e indicador activo de Settings |
| `207:40` | Ritmo del dashboard de Orders, tarjetas de resumen y tabla de informe |
| `232:8962` | Overlay de detalle de pedido y geometría del panel derecho |
| `232:9613` | Jerarquía compacta del contenido de detalle de pedido |
| `175:798` | Peso y geometría de iconos de referencia |
| `190:1280` | Espaciado y tipografía de las tarjetas de resumen |

| Token semántico | Valor | Uso |
| --- | --- | --- |
| `admin-canvas` | `#252836` | Área de trabajo administrativa raíz |
| `admin-surface` | `#1F1D2B` | Panel global, paneles de Settings, diálogos y tarjetas de producto |
| `admin-surface-raised` | `#2D303E` | Secciones de detalle del drawer y contenido elevado similar a formularios |
| `admin-border` | `#393C49` | Bordes de un píxel de las tarjetas de producto y divisores internos |
| `admin-accent` | `#EA7C69` | Controles activos, iconos, etiquetas y énfasis de foco |
| `admin-selection` | `rgba(234, 124, 105, .16)` | Filas de navegación seleccionadas |
| `admin-action` | `rgba(234, 124, 105, .24)` | Acciones de tarjetas de producto y superficies de acento moderado |
| `admin-text-primary` | `#FFFFFF` | Texto principal |
| `admin-text-secondary` | `#ABBBC2` | Texto complementario |
| `admin-text-tertiary` | `#889898` | Metadatos discretos y placeholders |
| `ticket-paper` | `#FFF8ED` | Superficie compacta del recibo del pedido |
| `ticket-ink` | `#2F2925` | Texto principal del recibo |
| `ticket-muted` | `#796E66` | Metadatos del recibo |
| `ticket-rule` | `#D9CBBD` | Separadores punteados y bordes discretos del recibo |

Orders utiliza la familia Barlow 400/500/600 incluida localmente en `resources/fonts/` mediante `font-orders`, que coincide con la tipografía verificada en Figma sin solicitar una fuente en tiempo de ejecución. Su licencia OFL se encuentra en `resources/fonts/OFL.txt`. El texto visible de Orders está en español de Argentina; los enums e identificadores de código permanecen en inglés.

## Composición de Orders y navegación de detalles

### Inicio de Quick Order

`resources/js/pages/admin/home.tsx` sigue la referencia POS de 1194 × 834 como una composición responsive real: el panel existente de 104 px, una columna de catálogo fluida que alcanza 681 px y un panel de pedido actual de 409 px. El catálogo conserva márgenes internos de 24 px; en el ancho de referencia quedan 633 px para tres tarjetas de 192 px con separaciones de 28 px e imágenes circulares de 132 px. La altura de cada tarjeta puede crecer para contener el nombre, el precio explícito de la docena cocinada y los tres objetivos de cantidad sin desbordarlos. En teléfonos se convierte intencionalmente en un catálogo denso de dos columnas, con imágenes compactas y objetivos de cantidad de 44 px, en lugar de escalar las tarjetas de escritorio a una única columna larga.

Las pestañas de categorías, las cantidades seleccionadas, los estados vacío/cargando/error y la acción coral usan los tokens `admin-*` existentes. La etapa de continuación utiliza la primitiva Sheet compartida, con un overlay negro al 70 % y un panel máximo de 814 px dividido en dos columnas de contenido. Reutiliza los campos de Te Pinta en lugar de la UI de pago de la plantilla. Por debajo del umbral de dos columnas determinado por el contenido, el pedido se convierte en una hoja inferior accesible en lugar de reducir las columnas del POS.

`use-admin-order-draft.ts` es responsable del comportamiento local en memoria, la coordinación del preview con el backend, la protección contra respuestas obsoletas y el bloqueo del envío. `AdminOrderDetailsFields`, `AdminProductSelector` y `PriceSummary` se comparten con `/admin/orders/create`; el catálogo y el panel de pedido actual específicos de Home permanecen como presentaciones independientes.

`resources/js/pages/admin/orders/index.tsx` adapta las referencias provistas (`docs/design/references/orders-cards-desktop.png`, `orders-cards-mobile.png` y `docs/design/reference/Captura de pantalla 2026-08-16 123430.png`) al espacio de trabajo oscuro existente de Orders. La búsqueda y las acciones principales encabezan la cabecera; las métricas compactas son secundarias; las pestañas de estado y los controles de filtro/vista preceden al contenido; y las analíticas permanecen en la columna derecha. Adopta una jerarquía orientada al escaneo sin copiar la paleta clara, la navegación de restaurante, los conceptos de servicio de mesa, los datos de ejemplo ni las llamadas a acciones de pago de las referencias.

Seleccionar un identificador TP solicita `/admin/orders?order=TP-*` mediante Inertia y abre `OrderTicketDialog` sin ocultar la lista filtrada. Su superficie cálida de papel es un recibo compacto de producción, no un segundo detalle completo: muestra snapshots estructurados del pedido y exactamente las acciones Copiar, Editar y cerrar. Al cerrarlo se elimina solo la selección, de modo que avanzar y retroceder en el navegador conserva su sentido. Los enlaces directos a `/admin/orders/{public_id}` todavía muestran la página completa `OrderDetailContent`. Editar un pedido cerrado sigue invocando el mismo flujo de habilitación con motivo.

Cada tarjeta de pedido contiene los mismos controles operativos rápidos que la fila del informe anterior. El estado usa el Select tipado existente y muestra el color correspondiente en el disparador y el menú. El pago sigue siendo un switch; al activarlo se abre la elección de método existente sobre una superficie `admin-surface` con selector `admin-surface-raised`, porque un pedido pagado sin `cash`, `transfer` o `debit` violaría la invariante del backend. La selección TP todavía abre el diálogo de recibo compartido.

Las tarjetas usan `admin-surface`, divisores discretos `admin-border`, énfasis coral para TP/total y texto de snapshots en lugar de imágenes del catálogo. Muestran todos los ítems persistidos, la composición de docenas combinadas y los toppings sin truncamiento, desplazamiento interno de tarjeta ni masonry. La grilla responsive mantiene un orden de lectura por filas; la lista compacta alinea identidad, variedades y acciones en columnas en escritorio y reduce el espaciado en dispositivos móviles sin ocultar contenido operativo. El selector segmentado persiste `view=grid|list` en la URL.

En viewports amplios, Orders mantiene la proporción de Figma `646:372` entre contenido principal e información, con una separación de 24 px dentro de la altura exacta del viewport disponible después del margen interno de AdminLayout. El documento queda bloqueado a ese viewport. La cabecera, las métricas compactas, las pestañas, los controles de vista/filtro y el recuento de resultados permanecen fijos; solo `orders-results-scroll` controla el desplazamiento vertical de resultados y paginación, con contención de overscroll y sin desbordamiento horizontal. Su distribución mantiene una primera fila típica de la grilla —incluidos el pago y el recibo— completamente visible antes de desplazar las filas siguientes. `OrdersInsightsPanel` queda limitado a la misma altura y solo puede desplazarse dentro de su propio panel si el contenido excede la capacidad. Por debajo de `xl`, Orders vuelve al flujo natural del documento. Los recuentos de productos, las imágenes de catálogo solo en Insights y las distribuciones de entrega y condición provienen de agregados del backend, no de datos de plantilla.

Los paneles de Settings utilizan una superficie opaca con un radio de 8 px, sin borde exterior ni sombra, y una separación de 24 px. Sus regiones de cabecera, filtros y contenido permanecen transparentes sobre esa superficie. Las tarjetas de Product utilizan la misma superficie de 8 px con un borde de un píxel y sin sombra; la acción de edición es una banda de 52 px de alto. Figma no define ninguna variante hover para estas superficies, por lo que hover no debe inventar un nuevo relleno.

En escritorio, el espacio de trabajo de Settings ocupa la altura restante debajo de la cabecera administrativa y el margen interno de la página. Tanto la navegación local como los paneles de contenido permanecen dentro de esa región del viewport; cualquier desbordamiento vertical se desplaza dentro de su propio panel. Por debajo del breakpoint de dos columnas, Settings vuelve al flujo normal del documento.

## Composición del Dashboard operativo

`/admin/dashboard` traslada la jerarquía provista del dashboard de gestión al lenguaje POS oscuro establecido de Te Pinta. Mantiene `admin-canvas`, `admin-surface`, el discreto `admin-border`, Barlow mediante `font-orders`, el énfasis principal coral y los tokens semánticos de estado existentes. No incorpora el fondo claro, los colores principales violeta o verde, la terminología financiera, la estructura visual de restaurante, los productos de ejemplo ni las métricas decorativas de gastos o saldo de la referencia.

Todos los paneles de Dashboard siguen una anatomía de tarjeta compartida: superficie con borde discreto, radio coherente, padding compacto, cabecera breve y una región de contenido. Las tarjetas KPI se mantienen uniformes y densas. En escritorio se compone una columna operativa amplia y otra más estrecha de información, donde las alertas y los pedidos de hoy tienen mayor peso visual que los gráficos. Evolución es el único gráfico principal; las visualizaciones de distribución siguen siendo barras segmentadas o donuts sencillos y utilizan equivalentes textuales accesibles.

La página utiliza un flujo natural del documento en lugar de bloquearse al viewport. Los paneles no crean desplazamiento anidado solo para imitar un lienzo fijo de Figma. En móviles, los pedidos urgentes, el resumen de pedidos de hoy y los KPI se recomponen en una o dos columnas; luego aparecen los paneles informativos y las tablas de escritorio se convierten en tarjetas compactas sin desbordamiento horizontal. Los controles conservan objetivos táctiles de 44 px y etiquetas visibles en español o nombres accesibles.

Los identificadores TP reutilizan `resources/js/features/orders/components/order-detail-sheet.tsx`. La hoja es visual y funcionalmente el mismo detalle operativo perteneciente a Orders que se usa en otros lugares; Dashboard no inventa otra estructura de recibo o detalle.

Las fuentes estructurales disponibles son `docs/design/reference/dashboard/Captura de pantalla 2026-08-16 004332.png` y `docs/design/reference/dashboard/figma.txt`. Los nombres solicitados `dashboard-manager-reference.png`, `dashboard-orders-reference.png` y `dashboard-figma-spec.txt` no están presentes en el repositorio. La captura y el CSS extraído de Figma orientan la jerarquía, el equilibrio 2/3 + 1/3, el espaciado y el ritmo de tarjetas; nunca se copian las posiciones absolutas ni la paleta de origen.

La verificación visual de Dashboard debe producir `storage/app/dashboard-desktop.png`, `storage/app/dashboard-mobile.png` y `storage/app/dashboard-order-drawer.png`, y luego recibir al menos una pasada de refinamiento. Son artefactos de revisión no versionados; no se consideran verificados hasta que el informe final de pruebas registre la ejecución de las capturas.

## Composición de Featured Product de Marketing

`/admin/marketing` utiliza el lienzo POS oscuro existente, la tipografía Barlow, superficies compactas, bordes discretos y énfasis coral en las acciones. No agrega una paleta de Marketing, efectos de vidrio, gradientes, tarjetas de dashboard sobredimensionadas ni una apariencia genérica de creador de campañas.

En escritorio, la gestión de recomendaciones y un preview público fiel se ubican lado a lado, seguidos por programaciones próximas/anteriores compactas y una lista discreta de **Próximamente**. En dispositivos móviles se ordenan verticalmente la recomendación actual, el preview, la programación y la información de alcance futuro, sin desbordamiento horizontal. Los estados inactivo, programado, activo, finalizado y no visible públicamente usan los tratamientos semánticos de insignias existentes en lugar de nuevos colores directos.

La presentación pública de Featured Product pertenece a `resources/js/features/marketing/` y solo acepta el modelo visual seguro para el público. Tanto Home pública como el preview administrativo renderizan ese mismo componente; el estado administrativo, las fechas, las advertencias y los controles de mutación lo envuelven, pero nunca ingresan al componente público. El preview utiliza un lienzo acotado similar al storefront en lugar de una tarjeta administrativa ficticia independiente.

La tarjeta pública tiene una jerarquía más marcada que un ítem normal del menú —imagen, antetítulo **Recomendación de la casa**, titular opcional, nombre de Product, texto opcional, precio actual de la docena cocinada y una CTA— y conserva el lenguaje existente de tipografía, espaciado, bordes y botones del sitio público. Cuando falta la imagen se utiliza un fallback neutro compartido por la experiencia pública y el preview. En dispositivos móviles la imagen sigue siendo legible sin convertir la recomendación en un banner del tamaño del viewport.

La CTA invoca la acción existente del carrito público y añade una unidad; no introduce un control de cantidad ni un estado de carrito específicos de Marketing. Cuando no existe una recomendación públicamente elegible, Home no renderiza una tarjeta placeholder ni reserva una región vacía.

La verificación visual de Marketing produce `storage/app/marketing-admin-desktop.png`, `storage/app/marketing-admin-mobile.png`, `storage/app/marketing-public-home.png`, `storage/app/marketing-public-mobile.png` y `storage/app/dashboard-marketing-summary.png`, seguida por al menos una pasada de refinamiento inspeccionada. Estos artefactos no se versionan y no se consideran verificados hasta informarlos después de su ejecución.

## Composición de gestión de Customers

Customers utiliza la paleta `admin-*` existente, Barlow mediante `font-orders`, superficies de ocho píxeles, bordes discretos, metadatos compactos, énfasis coral para foco/acción y la navegación administrativa global. No introduce un segundo dashboard ni un conjunto de tokens específico de Customers. Toda la presentación visible de Customers está en español, mientras que las claves internas de recursos y los enums permanecen sin cambios.

`resources/js/features/customers/` es responsable de las composiciones significativas de la funcionalidad: formulario reutilizable, lista adaptativa, resumen de perfil, historial de pedidos, etiquetas de presentación tipadas y formato. `/admin/customers` compone un espacio de trabajo compacto de búsqueda/recuento/lista/paginación. Desde `md` utiliza una tabla densa con acciones discretas mediante iconos; por debajo de `md` se convierte en filas de cliente táctiles de ancho completo con la misma información esencial de teléfono, pedidos, gasto y pedido más reciente.

El perfil agrupa cinco datos de resumen fáciles de recorrer sobre un historial compacto del más reciente al más antiguo y una superficie discreta de notas privadas. Los datos de contacto opcionales ausentes usan textos discretos como **Sin teléfono** o **Sin dirección**. Un cliente sin pedidos muestra **Sin compras registradas**, no una métrica de valor cero visualmente prominente. El historial sigue la misma regla responsive: columnas de tabla en escritorio y filas compactas en dispositivos móviles.

El historial de pedidos es una superficie de integración, no un segundo diseño de detalle. Al seleccionar un identificador TP se abre `resources/js/features/orders/components/order-detail-sheet.tsx`; esa estructura responsive perteneciente a Orders renderiza el mismo `OrderDetailContent` que usa el detalle completo. En escritorio se muestra una hoja derecha acotada; en dispositivos móviles ocupa todo el ancho disponible; al cerrarla se elimina solo la selección de la query `order`, por lo que el perfil sigue siendo el contexto de navegación.

La papelera de clientes conserva la jerarquía operativa oscura, muestra la fecha de eliminación y el recuento de pedidos asociados y se recompone de tabla de escritorio a filas móviles. Restaurar sigue siendo la única acción destacada; la eliminación destructiva no se promociona ni se agrega.

## Composición de Gestión

`/admin/management` reutiliza `font-orders`, `admin-canvas`, `admin-surface`, `admin-border`, `admin-action`, `admin-accent` y `admin-text-*`. No introduce verde bancario, gradientes, glassmorphism ni un dashboard SaaS paralelo. Los importes usan números tabulares y el color destructivo existente sólo para egresos o acciones que lo requieren; el saldo negativo se presenta sin ocultarlo.

La jerarquía estable es: encabezado y acciones, tres cards de resumen del backend, grilla de billeteras y movimientos filtrables. Las billeteras activas aparecen primero; las archivadas permanecen visibles con tratamiento secundario y acciones de reactivación. Crear/editar billetera, registrar un movimiento y revertir reutilizan los diálogos shadcn existentes; el formulario de movimiento recibe siempre un importe positivo y explica que el backend decide su impacto.

Desde `sm`, los indicadores usan tres columnas y las billeteras se distribuyen en una grilla adaptable. En mobile, los indicadores, billeteras y movimientos siguen el flujo vertical; el historial usa filas/cards en lugar de una tabla horizontal. Las acciones y campos interactivos mantienen al menos 44 px. Los estados vacíos explican el próximo paso real y nunca muestran métricas hardcodeadas.

## Navegación administrativa global

`resources/js/components/shared/admin-sidebar.tsx` es responsable de los destinos administrativos globales y de la lógica de ruta activa. `AdminLayout` solo compone este panel con la cabecera administrativa y el contenido; la navegación local de cada funcionalidad permanece dentro de su layout, como `SettingsLayout`.

Marketing e Instagram son destinos hermanos independientes. `/admin/marketing` debe usar coincidencia activa exacta para que la URL heredada `/admin/marketing/instagram` no marque ambas entradas como activas; la ruta anidada heredada no implica una jerarquía visual ni de dominio.

El destino visible **Gestión** enlaza a `/admin/management`. La URL anterior `/admin/inventory` no se conserva como alias porque sólo era un placeholder sin comportamiento; Inventory queda reservado para un futuro dominio de stock.

`resources/js/components/brand-logo.tsx` centraliza `public/icons/te-pinta-logo.svg`, el monograma vectorial canónico y autosuficiente utilizado por el panel administrativo de escritorio, el layout simple de autenticación y el favicon. El SVG conserva los trazados originales blancos, incorpora el fondo `admin-accent` y un margen vectorial uniforme; no contiene raster, scripts ni referencias externas. Los wrappers administrativos y de autenticación mantienen `bg-admin-accent`, coherente con el fondo del propio icono. Apple touch y la instalación PWA conservan los derivados PNG versionados de `public/icons/te-pinta-logo-*.png`, incluidos los tamaños y la variante maskable que requieren esas plataformas.

En anchos de escritorio y tablet, el panel sigue el nodo Figma `177:735`: 104 px de ancho con la superficie `#1F1D2B`, un bloque de marca redondeado de 56 px, seis posiciones de navegación de 102 px, wrappers de iconos de trazo fino de 24 px y un control de cierre de sesión alineado abajo. Los controles de escritorio comienzan en x=24. El control activo usa `#EA7C69`, un radio de 8 px, la sombra coral especificada y un tratamiento empotrado `#252836` en el borde derecho. En teléfonos, la navegación se convierte en una barra inferior consciente del área segura, con Inicio, Pedidos, Clientes y Más; los destinos secundarios y el cierre de sesión se encuentran en la hoja inferior Más.

Las capturas comparativas de Playwright se generan en `storage/app/` y no se versionan intencionalmente. Los nodos de Figma y el contrato semántico de este documento son la fuente de verdad duradera.
