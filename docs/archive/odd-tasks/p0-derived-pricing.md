# P0 derived pricing reconstruction
Objective: prepare P0-A only; preserve active checkout, Inventory and historical economics.
Branch: feat/p0-derived-pricing from d3c970c40aca28ccab55ca8a1821b1a6cc226581. Delivery: candidate only, explicit review and exact-candidate commit authorization required. Runner: isolated PostgreSQL + PHPUnit.

## Specs

- **S1**: “Comenzar únicamente por P0-A.” No P0-B/C activation or commits.
- **S2**: “precio_media_homogénea = D / 2 + R_producto”. Preserve existing `half_dozen_price`; derive one surcharge unambiguously without repricing existing rows.
- **S3**: “precio_media_combinada = Σ(cantidad_sabor × precio_docena_sabor / 12) + R_combinada”; `combined_half_dozen_surcharge = 1500`, configurable in Settings, once per mixed six.
- **S4**: “precio_docena_combinada = Σ(cantidad_sabor × precio_docena_sabor / 12)”; no surcharge for dozens.
- **S5**: “D / 12 < U”; “(D / 2 + R_producto) / 6 < U”; “D / 12 + R_combinada / 6 < U”. Validate participating tiers/condition, never silently repair prices; rounded total must preserve positive savings.
- **S6**: “Asignar las variedades a los bloques de menor costo comercial válido.” Preserve deterministic dozen/six/unit decomposition, real quantities and independent exhaustive oracle proof. `unit` products never enter empanada allocation.
- **S7**: “Para obtener el importe monetario final, utilizar pesos enteros mediante redondeo HALF-UP al peso más cercano.” No floats or nearest-100 rounding; reconcile integer projections and expose exact adjustment.
- **S8**: “No reemplazar silenciosamente los descuentos existentes con una fórmula distinta.” Effective raw unit/half/dozen prices retain existing discounts. Diagnose surcharge equivalence and invalid raw fixtures; no raw activation in P0-A.
- **S9**: “El trigger de PostgreSQL que protege pedidos con consumo de inventario debe contemplar los nuevos campos de pricing y sus numeradores exactos.” Reserve exact snapshots additively, without historical repricing or activation; guard every added economic field after consumption.
- **S10**: “No realizar commits sin autorización explícita del usuario sobre el candidato exacto y después de cerrar el proceso de revisión correspondiente.” Private testing PostgreSQL only; no push/PR/merge/deploy, no AUX-2/POS-3/incomplete Quick Order.

## Tasks

- T1 [S2–S8] inline, candidate verified (not closed; commit unauthorized): pure exact pricing foundation and independent oracle; observed RED then GREEN required; no commit authorized.
- T2 [S2,S3,S5,S8] inline, candidate verified (not closed; commit unauthorized): explicit backend surcharge configuration/compatibility, administration and tests without activating pricing; no commit authorized.
- T3 [S9,S10] inline, candidate verified (not closed; commit unauthorized): additive reserved exact snapshot schema, economic guards, historical/migration regression proof; no commit authorized.
- T4 [S1–S10] inline, functional checks complete; fresh native review pending: normalization, functional checks, self-review and requested fresh native Risk/Readability/Reliability review; stop before commit/P0-B.

## Log

### L1 — Original user request (2026-10-15)

# TE PINTA 3.0 — P0 DERIVED PRICING RECONSTRUCTION

## PRIORIDAD CRÍTICA — NECESIDAD COMERCIAL INMEDIATA

Necesitamos dejar de calcular manualmente los pedidos de empanadas combinadas.

Actualmente utilizamos calculadora para determinar los precios de pedidos con diferentes variedades. Esto retrasa la atención de clientes por WhatsApp, teléfono y mostrador.

El objetivo de este trabajo es implementar cuanto antes un sistema de precios combinados correcto y un cotizador administrativo rápido.

**Las reglas comerciales principales están confirmadas por el dueño del negocio y se detallan a continuación.**

No volver a utilizar un precio fijo único para todas las docenas combinadas.

No implementar reglas alternativas sin autorización.

---

## 1. CONTEXTO DEL REPOSITORIO

La rama histórica:

`feat/derived-combination-pricing`

HEAD conocido:

`9e5eae04e3b6a289c416040af489207847d909d3`

Worktree:

`/home/tuki/workspaces/tepinta3.0-worktrees/derived-pricing`

Contiene un algoritmo funcional y probado:

- DerivedTierAllocator.
- Aritmética exacta.
- Asignación determinista por menor costo.
- Media docena combinada.
- Docena combinada.
- Snapshots versionados.
- Tests del motor.

Resultado de auditoría:

21 tests / 70 assertions aprobados.

**No hacer cherry-pick directo de esa rama.**

Es anterior a POS-0 y no separa correctamente productos de familia `empanada` y `unit`.

La base moderna conocida es:

`d3c970c40aca28ccab55ca8a1821b1a6cc226581`

Rama:

`feat/admin-operational-ux-normalization`

Contiene POS-0…2, AOB-1…7, AUX-0, AUX-FIX-0 y AUX-1.

El worktree UX podría tener modificaciones de otro agente.

Verificar Git y crear un worktree independiente desde el commit exacto limpio, sin copiar modificaciones no aprobadas.

Propuesta:

Branch:
`feat/p0-derived-pricing`

Worktree:
`/home/tuki/workspaces/tepinta3.0-worktrees/p0-derived-pricing`

El checkout histórico permanece READ-ONLY.

---

# 2. REGLA COMERCIAL DEFINITIVA

## 2.1. Precio unitario

Cada variedad tiene un precio de unidad `U`.

Ejemplo:

Matambre:
- Unidad: $2.200.
- Docena: $22.000.

Caprese:
- Unidad: $1.800.
- Docena: $18.000.

Los valores son ejemplos, no precios que deban hardcodearse.

Los precios reales provienen del catálogo del backend.

## 2.2. Media docena homogénea

La media docena de una sola variedad se calcula mediante:

`precio_media_homogénea = D / 2 + R_producto`

Donde:

- `D`: precio de docena de esa variedad.
- `R_producto`: recargo configurable de media docena homogénea para esa variedad.

**El recargo es configurable por producto, no necesariamente igual para todos.**

Valor inicial comercial de referencia: $2.000.

Ejemplo Matambre:

- Docena: $22.000.
- Mitad: $11.000.
- Recargo propio: $2.000.
- Media docena: $13.000.
- Precio proporcional: $2.166,67.

La unidad cuesta $2.200, por lo tanto la media ofrece un ahorro.

Ejemplo de variedad económica:

- Docena: $18.000.
- Unidad: $1.800.
- Recargo particular: $1.500.
- Media docena: $10.500.
- Precio proporcional: $1.750.

Es necesario poder definir ese recargo individualmente porque utilizar $2.000 produciría una media docena de $11.000, más cara que comprar seis unidades por separado ($10.800).

No alterar silenciosamente ningún precio.

## 2.3. Media docena combinada

Una media docena combinada debe contener exactamente seis empanadas de al menos dos sabores distintos.

El precio se calcula proporcionalmente sobre las docenas y se aplica un único recargo global de $1.500 al grupo.

Fórmula:

`precio_media_combinada = Σ(cantidad_sabor × precio_docena_sabor / 12) + R_combinada`

Donde:

`R_combinada = $1.500`

Ese recargo debe ser configurable desde Settings.

No corresponde aplicar $1.500 por sabor ni por empanada.

Es un único recargo por media docena combinada.

### Ejemplo obligatorio

3 Matambre:
- Docena: $22.000.
- Aporte: 3 × $22.000 / 12 = $5.500.

3 Caprese:
- Docena: $18.000.
- Aporte: 3 × $18.000 / 12 = $4.500.

Subtotal proporcional: $10.000.

Recargo combinado: $1.500.

**Precio final: $11.500.**

Compra de seis unidades sueltas:

3 × $2.200 + 3 × $1.800 = $12.000.

Ahorro del cliente: $500.

## 2.4. Docena homogénea

Una docena de un solo sabor utiliza su precio de docena configurado.

Ejemplo:

12 Matambre = $22.000.

No aplicar recargo adicional.

## 2.5. Docena combinada

Una docena combinada debe contener doce empanadas de al menos dos variedades.

Fórmula:

`precio_docena_combinada = Σ(cantidad_sabor × precio_docena_sabor / 12)`

Sin recargo combinado.

### Ejemplo

3 Matambre:
3 × $22.000 / 12 = $5.500.

3 Salteña:
3 × $20.000 / 12 = $5.000.

6 Caprese:
6 × $18.000 / 12 = $9.000.

**Total docena combinada: $19.500.**

No cobrar automáticamente el antiguo precio fijo de docena combinada.

---

# 3. GARANTÍA DE AHORRO COMERCIAL

**REGLA OBLIGATORIA**

Cada empanada dentro de una presentación de seis o doce debe tener un aporte proporcional inferior al precio de esa misma empanada comprada individualmente, bajo la condición cruda/cocinada correspondiente.

Validar:

### Docena

`D / 12 < U`

### Media homogénea

`(D / 2 + R_producto) / 6 < U`

### Media combinada

`D / 12 + R_combinada / 6 < U`

Con recargo combinado de $1.500:

`R_combinada / 6 = $250`

La condición para un sabor participante es:

`D / 12 + 250 < U`

Estas desigualdades deben calcularse sin floats.

Además verificar que el total cobrado, después del redondeo a pesos, conserve un ahorro positivo respecto de las unidades equivalentes.

Si una configuración incumple estas condiciones:

- No cobrar silenciosamente un precio incoherente.
- No modificar el precio de venta automáticamente.
- Informar qué variedad o configuración genera el conflicto.
- Permitir corregir el recargo o los precios desde administración.

Revisar cómo validar la configuración sin bloquear innecesariamente la consulta o edición de productos históricos no relacionados.

No modificar pedidos anteriores.

---

# 4. ASIGNACIÓN DE GRUPOS

Reutilizar conceptualmente `DerivedTierAllocator`.

Para una cantidad total de empanadas:

- 1–5: unidades.
- 6: media docena.
- 7: media docena + unidad.
- 8: media docena + 2 unidades.
- 10: media docena + 4 unidades.
- 12: docena.
- 18: docena + media docena.
- 24: dos docenas.

Para cantidades superiores, conservar la descomposición determinista de bloques.

Dentro de esa descomposición:

1. Asignar las variedades a los bloques de menor costo comercial válido.
2. Distinguir media homogénea de media combinada.
3. Aplicar el recargo correspondiente al tipo de media.
4. Evitar la pérdida o duplicación de unidades.
5. No utilizar precios unitarios para construir falsos descuentos.
6. Mantener desempates deterministas.
7. Evaluar correctamente pedidos con varias medias o docenas.

**IMPORTANTE:** el algoritmo histórico optimiza una función de precios diferente.

No basta con copiarlo y agregar un recargo después.

La optimización debe considerar que una media homogénea tiene un recargo por variedad, mientras que la media combinada tiene un recargo global.

Demostrar con pruebas contra un oráculo exhaustivo independiente que el allocator selecciona la combinación de menor precio válido dentro de los bloques comerciales permitidos.

---

# 5. PRODUCTOS Y CONFIGURACIÓN

Conservar:

`ProductPricingFamily::empanada`

`ProductPricingFamily::unit`

Pizzas, bebidas, postres y otros productos unitarios jamás deben:

- Entrar en el allocator de empanadas.
- Completar una media docena.
- Completar una docena.
- Recibir recargos de empanadas.
- Alterar el cálculo de grupos.

### Settings

Agregar un parámetro configurable:

`combined_half_dozen_surcharge = 1500`

Utilizar una denominación técnica coherente con los contratos existentes.

No agregar valores monetarios como constantes ocultas del algoritmo.

### Products

Agregar o derivar de manera inequívoca el recargo propio de media docena homogénea:

`homogeneous_half_dozen_surcharge`

Debe poder editarse por variedad.

El sistema debe mostrar:

- Precio unitario.
- Precio de docena.
- Recargo de media homogénea.
- Precio de media homogénea resultante.

No mantener dos fuentes de verdad activas contradictorias para el mismo precio.

El catálogo existente puede contener `half_dozen_price`.

Inspeccionar todas sus lecturas y escrituras antes de decidir migración, derivación, compatibilidad y presentación.

Revisar valores actuales y diferencias antes de transformar configuraciones existentes.

No introducir una migración que cambie silenciosamente precios comerciales de productos reales.

Mantener los valores históricos para los snapshots que los requieran.

---

# 6. REDONDEO

**DECISIÓN COMERCIAL ACTUAL**

No adoptar el antiguo redondeo global a múltiplos de $100.

No redondear los aportes proporcionales de cada sabor antes de sumar.

Conservar aritmética racional exacta en backend.

Para obtener el importe monetario final, utilizar pesos enteros mediante redondeo HALF-UP al peso más cercano.

Ejemplo:

$11.500 exactos → $11.500.

Si una combinación genera un resultado fraccionario, redondear sin pérdida de precisión intermedia.

El backend debe garantizar:

- Total persistible en pesos enteros.
- Desglose que reconcilia con el total.
- Ajuste de redondeo visible cuando corresponda.
- Ahorro comercial preservado.
- Ninguna alteración del precio de pizzas, bebidas o envío por un redondeo global a $100.

Documentar claramente en qué límite monetario se efectúa el redondeo final y cómo se reconcilian las líneas enteras con el total.

No duplicar aritmética comercial en React.

---

# 7. EMPANADAS CRUDAS

El sistema existente distingue cocinadas y crudas mediante descuentos configurados.

Debemos conservar esa funcionalidad.

El pricing derivado debe utilizar las tarifas efectivas de la condición comercial seleccionada.

No aplicar simultáneamente dos descuentos que representen el mismo beneficio.

Analizar cómo conviven:

- Descuento de unidad cruda.
- Descuento de media docena cruda.
- Descuento de docena cruda.
- Nuevo recargo de media homogénea.
- Nuevo recargo de media combinada.

Presentar las equivalencias con los ejemplos y settings actuales.

**Si las reglas vigentes producen resultados contradictorios, solicitar una decisión comercial concreta antes de activar el nuevo cálculo para crudas.**

No reemplazar silenciosamente los descuentos existentes con una fórmula distinta.

Mantener preservados los pedidos históricos.

---

# 8. SNAPSHOTS Y POSTGRESQL

La rama antigua implementó snapshots exactos v2.

Recuperar su arquitectura solamente después de adaptarla a los contratos actuales.

Requisitos:

- Compatibilidad histórica v1/v2 cuando corresponda.
- Sin recálculos retroactivos.
- Persistencia exacta del precio comercial.
- Composiciones de combinadas.
- Ajustes de redondeo.
- Idempotencia.
- Preview y persistencia coherentes.
- Concurrencia controlada.
- Ediciones económicas explícitas.

**ATENCIÓN A AOB-5**

El trigger de PostgreSQL que protege pedidos con consumo de inventario debe contemplar los nuevos campos de pricing y sus numeradores exactos.

No permitir la modificación posterior de datos económicos protegidos mediante nuevos campos que hayan quedado fuera de los guards.

Verificar consumo físico por unidades reales y composición, no por cantidad de paquetes comerciales.

No cambiar reglas de COGS ni de reversals como efecto secundario.

---

# 9. PRODUCT COSTING

AOB-6 ya incluye costing de productos según presentación.

Revisar la adaptación necesaria:

- Unidades.
- Media homogénea.
- Docena homogénea.
- Combinaciones.
- Productos `unit`.
- Packaging estimado.
- Márgenes.

No presentar como definitivo un margen calculado con un precio de venta antiguo.

El costing de canasta combinada puede permanecer diferido si no es requerido para la primera entrega, pero debe declararse explícitamente y no mostrarse un margen ficticio.

---

# 10. PRIORIDAD OPERATIVA — COTIZADOR RÁPIDO

Necesitamos utilizar el nuevo cálculo para atender pedidos cuanto antes.

Crear una pantalla administrativa de cotización, por ejemplo:

`/admin/quick-quote`

Verificar convenciones y rutas existentes antes de definir el destino definitivo.

El cotizador debe permitir:

- Seleccionar variedades.
- Sumar cantidades con un toque.
- Restar cantidades.
- Ver la cantidad total.
- Identificar medias y docenas.
- Calcular las combinaciones.
- Mostrar el precio final.
- Mostrar el desglose.
- Limpiar la selección.
- Comenzar otra cotización.

Debe funcionar en PC y celular.

No pedir:

- Nombre.
- Teléfono.
- Dirección.
- Cliente.
- Forma de pago.

No registrar un pedido todavía.

No modificar inventario, saldos, ledger, pagos ni tablas de pedidos.

Utilizar exclusivamente precios del backend.

Cotización autoritativa mediante Laravel.

Debe ser claro cuando un producto tiene configuración incompleta o inválida.

### UI

Utilizar el diseño administrativo existente AUX-1:

- Barlow.
- Dark theme.
- Accent coral.
- Botones táctiles.
- Estados de carga.
- Manejo de respuestas obsoletas.
- Responsive 320px en adelante.

Diseñar para operación rápida, no como un formulario administrativo tradicional.

No introducir una segunda implementación de pricing solamente para el cotizador.

---

# 11. FUTURO PEDIDO RÁPIDO Y MESAS

El cotizador debe quedar preparado arquitectónicamente para que, en un slice posterior, su selección de productos pueda continuar a Crear pedido rápido sin volver a cargar las variedades.

El futuro flujo confirmado es:

Seleccionar productos → Confirmar pedido → Guardar → Encolar comanda.

Sin exigir cliente para mostrador o mesa cuando el contexto operativo lo permita.

Para mesas:

- Cada ronda genera su propia comanda.
- Las rondas acumulan consumos comerciales.
- El importe visible mientras la mesa permanece abierta es provisional.
- El total comercial definitivo se fija al cerrar la cuenta.
- El cobro real genera movimientos financieros cuando corresponda.
- Las comandas ya emitidas conservan su historial.

**No implementar POS-3, sesiones de mesa, comandas ni impresión térmica en este candidato.**

Sin embargo, no diseñar un motor que impida cotizar consumos comerciales acumulados en el futuro.

No implementar congelamiento de precios de mesa sin una política aprobada.

---

# 12. DESARROLLO POR UNIDADES

No quiero una implementación gigante mezclando pricing, UI, POS y Management.

Organizar en unidades pequeñas.

### P0-A — Pricing Foundation

- Analizar contratos modernos.
- Incorporar aritmética exacta y allocator adaptado.
- Incorporar regla de media homogénea configurable.
- Incorporar recargo global de media combinada.
- Conservar pricing families.
- Definir redondeos y garantías.
- Adaptar Settings y Products.
- Preparar esquema versionado y guards.
- TDD exhaustivo.

Primero entregar el candidato P0-A para revisión.

### P0-B — Pricing Integration

- Integrar la nueva regla al motor compartido.
- Adaptar persistencia.
- Verificar snapshots.
- Verificar checkout y Quick Order existentes.
- Verificar Inventory y Product Costing.
- Probar preview/persistencia.
- Corregir etiquetas y desglose.
- Evitar precios divergentes.

### P0-C — Quick Quote

- Cotizador administrativo rápido.
- Selector táctil.
- Backend preview.
- Desglose y total.
- Sin persistencia de pedido.
- Responsive.
- Acceso sencillo desde administración.

**Priorizar la primera cotización utilizable, pero no activar fórmulas comerciales sin que los tests y las reglas estén cerrados.**

Si existe una forma segura de acortar P0-A/P0-B sin generar dos motores de precios, proponerla y justificarla antes de modificar el alcance.

No iniciar todas las unidades simultáneamente.

---

# 13. TESTS OBLIGATORIOS

Probar los ejemplos comerciales anteriores:

1. 6 Matambre homogéneas.
2. 6 de una variedad económica homogénea.
3. 3 Matambre + 3 Caprese.
4. 3 Matambre + 2 de otra variedad + 1 Caprese.
5. 2 Matambre + 2 Humita + 2 Pollo + 2 Salteña.
6. 12 de un solo sabor.
7. 12 combinadas.
8. 18 combinadas.
9. 24 combinadas.
10. Canasta con empanadas + pizzas + bebidas.
11. Precios de productos incorrectamente configurados.
12. Precios crudos/cocinados.
13. Redondeos fraccionarios.
14. Descuentos administrativos.
15. Preview y persistencia.
16. Snapshots históricos v1/v2.
17. Consumo de inventario y COGS.
18. Costing y pricing families.
19. Idempotencia.
20. Cotizaciones consecutivas con respuestas HTTP fuera de orden.

Usar fixtures explícitos y distinguirlos de precios comerciales reales.

Probar configuraciones donde el recargo homogéneo sea distinto del combinado.

Validar optimización determinista mediante casos límite y oráculo exhaustivo.

No usar float para resultados autoritativos.

---

# 14. SEGURIDAD OPERATIVA

No tocar bases comerciales reales.

No ejecutar migraciones sobre producción ni desarrollo compartido.

PostgreSQL de pruebas privado.

No desplegar sin autorización.

No modificar checkout histórico sucio.

No modificar AUX-2.

No modificar POS-3.

No crear clientes ficticios.

No permitir que una UI de cotización cree movimientos contables o de inventario.

No incorporar datos de productos inventados como precios reales.

---

# 15. REVIEW Y GIT

Seguir:

`/home/tuki/.codex/AGENTS.md`

Aplicar TDD RED → GREEN.

Delegación verificable cuando sea posible.

Native review independiente.

Revisión de Risk, Readability y Reliability.

No reutilizar reviews históricas de la rama derivada.

No realizar commits sin autorización explícita del usuario sobre el candidato exacto y después de cerrar el proceso de revisión correspondiente.

Sin push.
Sin PR.
Sin merge.
Sin deploy.

---

# 16. PRIMERA ENTREGA SOLICITADA

**Comenzar únicamente por P0-A.**

Antes de implementar, realizar un preflight e identificar contradicciones comerciales o incompatibilidades reales que impedirían una implementación segura.

Si aparece una decisión comercial indispensable no cubierta por este prompt, presentarla con ejemplos y detener solamente la parte afectada.

Para el resto, avanzar con el alcance autorizado de P0-A.

Entregar:

## P0-A Candidate

## Git Baseline

## Reused Derived Pricing Components

## Confirmed Business Rules

## Product Surcharge Configuration

## Combined Half-Dozen Surcharge

## Allocation and Optimization

## Exact Arithmetic and Rounding

## Raw/Cooked Compatibility

## POS-0 Compatibility

## Inventory/COGS Integrity

## Schema and Historical Snapshots

## Worked Pricing Examples

## Tests and Regression Evidence

## Review Status

## Worktree State

## P0-B Readiness

Detenerse antes del commit y antes de iniciar P0-B.

Finalizar:

`P0-A READY FOR REVIEW`

o

`P0-A BUSINESS DECISION REQUIRED`

o

`P0-A BLOCKED — motivo concreto`.

### L2 — Preflight

Exact requested base verified; historical branch still clean at 9e5eae0. New independent worktree created, no cherry-pick or dirty bytes copied. Only primary agent exists in this session. `mode: solo`, `delegation_unverifiable`: authoritative effective model/effort and native UUID are unavailable; the documented inspector cannot be addressed without fabricating an ID. User-owned degradation policy permits sequential inline work. Full P0-A forecast: approximately 1,500–2,000 authored lines plus this verbatim log; no commits authorized, no PR slicing initiated. No dependencies added; immutable installed dependencies reused locally. Engram mirror pending: core rejects writes because runtime session resolution is unavailable.

Read map of `half_dozen_price`: Products requests/DTO/create/update/model/admin and public resources/calculator/provider; Orders form options and pricing; Settings raw validation; Marketing featured view; product editor/card/types and order types; catalog seeder; AOB-6 economics consumes ProductPriceProvider. Existing H stays canonical, R is derived as `(2H-D)/2`; no data conversion. Current seed homogeneous surcharges are 1000, not the new-product reference 2000. Seed raw unit/half/dozen discounts are 200/1000/2000, so the same homogeneous surcharge remains algebraically equivalent. Raw Caprese mixed-six is invalid with R=1500; raw Humita equality is also invalid. No live catalog queried; no activation or silent repair.


### L3 — Foundation candidate and observed evidence

Implemented the inactive P0-A primitive only. The DP compares complete homogeneous/mixed costs; mixed identity prevents homogeneous sixes receiving the mixed tariff. An independent enumeration oracle covers 6/7/8/10/12/18/24/25/30-unit baskets, reverse-input stability and physical quantity conservation. Unit families are filtered out. Exact /12 products and /1200 percentage arithmetic use one final HALF-UP peso boundary. A rounding-induced loss of savings is an explicit error, never an alternative price.

Observed TDD RED: new foundation suite initially had 10 missing-class errors plus 2 expected-exception mismatches; GREEN followed implementation. Configuration/schema RED reproduced missing surcharge helper, route, enum and fields (42703 instead of the required consumed-history 55000). A separate Settings regression observed 302 instead of expected 422 for an unsafe changed surcharge, then GREEN after adding cooked sellable-product validation. The first hand-calculated eight-flavor-fixture expectation was corrected from 15167 to 15267 using the independently documented fractional sum; no production formula was changed to fit it. Additional regression assertions were added during refactoring; do not describe those as individually observed RED.

Final focused command: `vendor/bin/phpunit --do-not-cache-result tests/Unit/Orders/DerivedPricingFoundationTest.php tests/Feature/Products/DerivedPricingConfigurationTest.php tests/Feature/Orders/DerivedPricingSchemaTest.php tests/Feature/Inventory/OrderConsumptionConstraintTest.php`: **26 tests / 504 assertions passed**.

Full suite in disposable `sail-8.5/app` PHP 8.5.9, mounted only this worktree and sharing only private `tepinta-p0a-pg` network namespace, with DB_HOST=127.0.0.1, DB_PORT=5432, DB_DATABASE=testing: `vendor/bin/phpunit --do-not-cache-result`: **729 tests / 5848 assertions passed**. Host PHP 8.5.4 first run had 725 passes and 4 GD-extension errors; local image GD resolved the environment limitation without dependency/host installation. No shared database was touched. Exact base/historical dependency lockfiles are identical.

`npm run build`, `npm run types:check`, full `vendor/bin/phpstan analyse --no-progress`, scoped Pint and Prettier checks, scoped ESLint and `git diff --check` passed. Full ESLint retains **57 baseline errors**, all in files whose Git blob hashes exactly match d3c970c; zero errors remain in candidate JS/TS. No unrelated formatting was performed. Sail Make wrappers were not used because their default environment may target shared development; equivalent checks used private state.

Browser command: `LD_LIBRARY_PATH=/tmp/pos1-browser-libs/usr/lib/x86_64-linux-gnu PLAYWRIGHT_PORT=3189 PLAYWRIGHT_DB_DATABASE=testing_p0a_playwright npx playwright test tests/e2e/derived-pricing-configuration.spec.ts tests/e2e/products.spec.ts --workers=1`: **10 passed, 1 intentional desktop-only skip on mobile, 1 pre-existing failure**. New editor checks pass on both projects, including 320px bounds and stale HTTP response rejection. Host was initially missing libgbm; existing local test libraries resolved launch without downloading/installing software. The viewport failure (`products.spec.ts:116`, 1004px document versus 720px viewport) reproduced identically in a `git archive d3c970c` scratch tree under the home worktree parent with an independent private `testing_p0a_baseline_playwright` database. Do not fix AUX layout in this candidate. A 320px screenshot was inspected; the new surcharge control wraps and has explicit AUX text colors. Existing legacy price-field contrast is outside this change.

The additive migration down/up test preserves a populated v1 order and the entire fixture catalog byte-for-byte, reserves v2 shape, refuses rollback over v2 history, and rejects every newly introduced order/line field mutation after consumption with SQLSTATE 55000. Existing inventory consumption, costing, idempotency, order editing and payment regression suites pass as part of the full run. They prove no regression of the active v1 paths, NOT activation or preview/persist proof of new v2 pricing.

New product editing keeps existing H as canonical; the backend derives R exactly, including half-peso surcharges for odd D. New R input that cannot produce a whole-peso stored H is explicitly rejected rather than silently rounded; this is a current catalog representation boundary, not a replacement price formula. Existing historical/unchanged invalid configurations remain readable and metadata-editable; changed cooked tariffs and changed mixed surcharge validate savings. Raw activation remains deferred, including the owner Matambre homogeneous equality conflict and seed Caprese/Humita mixed conflicts documented in the pricing foundation guide.

Review mode observed ON from global preference, gentle-ai 4.1.1. Requested fresh lenses: Risk, Readability, Reliability. No historical approval reused. Initial assess reported high/unassessable solely because owned files were still untracked; the candidate will be explicitly fixed in this worktree's index before canonical preflight. No START or reviewer was invoked yet. Root structured self-review has no identified blocking P0-A defect; limitations are the inactive v2 reservation/activation boundary, no new basket endpoint or combined margins, raw activation decisions, and reproduced baseline lint/viewport issues. Native approval and commit authorization remain pending.

Engram reads resolved project `te-pinta-3.0`; no authoritative session ID is exposed. Save/review/summary writes fail with `Engram core lacks runtime session resolution`; the full task mirror remains pending, not claimed saved. No worker writes exist in this session. No source changes are authorized after candidate freeze without new checks/review binding. Stop before commit and P0-B.
