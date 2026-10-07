# Gestión financiera

`Management` reúne la operación financiera administrativa de Te Pinta. La superficie vive en `/admin/management` y muestra **Gestión**. `Inventory` queda reservado para el futuro dominio de stock.

## Alcance actual

Gestión permite:

- administrar billeteras configurables mediante creación, cambio de nombre/orden, archivado y reactivación;
- registrar aperturas, ajustes, ingresos y egresos manuales;
- registrar compras, gastos operativos y retiros personales con categoría y contraparte opcional;
- transferir dinero entre dos billeteras activas sin alterar el dinero total del negocio;
- definir reglas porcentuales versionadas y aplicar distribuciones manuales sobre billeteras activas;
- registrar una vez el cobro de un Order y distribuirlo automáticamente cuando existe una regla activa;
- adjuntar y consultar comprobantes privados que respaldan una operación financiera sin modificar su impacto;
- consultar saldos derivados, métricas del período e historial financiero unificado;
- reconciliar el ledger con sus orígenes semánticos mediante findings derivados de solo lectura;
- revertir una transacción una sola vez mediante un movimiento compensatorio.

La integración con Orders registra el ingreso externo en una billetera predeterminada y, si existe una regla activa, aplica después una distribución interna conservativa. Una compra no incrementa stock.

## Dos responsabilidades separadas

La entidad administrativa explica **qué ocurrió**. El ledger registra **cómo impactó el dinero**.

```text
ManagementOutflow
  type: purchase
  category: raw_material
  description: 10 kg harina
  counterparty: Distribuidora Norte
  amount: 25000
        │
        └── FinancialTransaction (management_outflow)
              └── LedgerEntry -> Banco -> -25000
```

`management_outflows` conserva la semántica de compra, gasto o retiro. No se agregan proveedor, categoría ni campos específicos de egresos a `financial_transactions`. La relación estructurada es una clave foránea única desde el egreso hacia su transacción financiera; no depende de texto libre ni de metadata JSON.

Una transferencia conserva su propia semántica y usa dos impactos en una única cabecera:

```text
ManagementTransfer
  Banco -> Efectivo
  amount: 30000
        │
        └── FinancialTransaction (transfer)
              ├── LedgerEntry -> Banco    -> -30000
              └── LedgerEntry -> Efectivo -> +30000
```

`management_transfers` enlaza estructuralmente el origen, el destino y la cabecera mediante una clave foránea única. No representa la transferencia como un egreso más un ingreso ni agrega el tipo `transfer` a `management_outflows`.

Una distribución separa la regla vigente del hecho financiero aplicado:

```text
DistributionRule -> DistributionRuleVersion -> allocations en basis points
                                      │
                                      └── ManagementDistribution
                                            └── FinancialTransaction (distribution)
                                                  ├── LedgerEntry -> Caja      -> -10000
                                                  ├── LedgerEntry -> Operación -> +5000
                                                  ├── LedgerEntry -> Ganancia  -> +3000
                                                  └── LedgerEntry -> Reserva   -> +2000
```

La distribución identifica una billetera origen real y conserva una referencia a la versión junto con un snapshot relacional de cada destino, porcentaje e importe aplicado. No interpreta JSON ni reconstruye historia desde la regla actual. El débito del origen y los créditos de los destinos pertenecen a una única operación conservativa.

Un cobro de Order conserva su origen comercial sin acoplar Orders al ledger:

```text
Order payment_status -> paid
  └── OrderPaymentConfirmed
        └── ManagementOrderPayment
              └── FinancialTransaction (order_payment)
                    └── LedgerEntry -> billetera predeterminada -> +amount
              └── ManagementDistribution
                    └── FinancialTransaction (distribution)
                          ├── LedgerEntry -> billetera predeterminada -> -amount
                          └── LedgerEntry[] -> destinos de la versión -> +amount
```

`paid` es el estado operativo mutable del pedido. `ManagementOrderPayment` es el hecho financiero durable y único por Order. Orders emite un contrato de aplicación; Management decide la billetera, la regla y la distribución sin exponer sus internals al módulo Orders.

Los comprobantes forman una capa documental separada:

```text
FinancialTransaction
  ├── LedgerEntry[]
  └── ManagementAttachment[]
```

Cada `ManagementAttachment` pertenece directamente a una `FinancialTransaction`. Así, cualquier egreso, transferencia, distribución, cobro o reversión accede a sus comprobantes mediante la misma cabecera financiera, sin agregar relaciones polimórficas ni tablas duplicadas. El documento explica el hecho, pero nunca determina el tipo, importe, fecha, entries ni saldo. Las operaciones existentes pueden no tener comprobantes y esa ausencia es válida.

## Modelo persistente

| Tabla                                      | Responsabilidad                                                                                                   |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------- |
| `wallets`                                  | Billetera configurable, orden de presentación y `archived_at`; no contiene saldo.                                 |
| `financial_transactions`                   | Cabecera durable del hecho financiero, fecha efectiva, administrador y eventual `reversal_of_id`.                 |
| `ledger_entries`                           | Impacto firmado, en pesos enteros, sobre una billetera.                                                           |
| `management_outflow_categories`            | Catálogo persistido por tipo, con código estable, nombre, estado activo y orden.                                  |
| `management_outflows`                      | Origen semántico e inmutable de una compra, gasto o retiro, vinculado 1:1 con el ledger.                          |
| `management_transfers`                     | Transferencia interna e inmutable entre dos billeteras, vinculada 1:1 con una cabecera neutral.                   |
| `management_distribution_rules`            | Identidad lógica, nombre y estado activo globalmente exclusivo; no contiene porcentajes.                          |
| `management_distribution_rule_versions`    | Versión numerada e inmutable de una regla.                                                                        |
| `management_distribution_rule_allocations` | Destinos ordenados y porcentaje entero en basis points de cada versión.                                           |
| `management_distributions`                 | Aplicación inmutable desde una billetera origen de una versión; puede vincularse 0..1 con el pago que la originó. |
| `management_distribution_allocations`      | Snapshot relacional inmutable de billetera/nombre, orden, porcentaje e importe efectivamente aplicado.            |
| `management_order_payments`                | Cobro inmutable e idempotente de un Order, su destino, importe, fecha y vínculo 1:1 con la cabecera financiera.   |
| `management_attachments`                   | Metadata y tombstone de un archivo privado e inmutable vinculado a una cabecera financiera.                       |

Una transacción financiera admite varias entries. Los movimientos manuales y los egresos semánticos crean una; cada transferencia crea exactamente dos entries opuestas; cada distribución crea una entry negativa en el origen y una positiva por allocation aplicada.

## Comprobantes y evidencia documental

Los comprobantes son opcionales. El backend acepta inicialmente PDF, JPG/JPEG, PNG y WEBP de hasta `10 MB`, valida el MIME real y no confía en la extensión ni en el nombre informado por el navegador. EXE, JS, HTML, SVG, ZIP y cualquier formato fuera de la allowlist se rechazan.

El archivo se almacena en el disco privado `local` bajo una ruta generada como `management/attachments/{year}/{month}/{uuid}`. El nombre original se conserva sólo como metadata; nunca participa en el path. La fila conserva `disk`, `path`, `original_name`, `mime_type`, `size`, `checksum_sha256`, `uploaded_by` y timestamps. El SHA-256 permite comprobar integridad, pero este slice no deduplica archivos ni reemplaza contenido existente.

PostgreSQL y el storage no comparten una transacción ACID. El flujo escribe primero el archivo y luego crea la metadata; si la escritura de base falla, elimina exclusivamente el archivo recién creado. Si el storage falla, no crea ninguna fila. Ese cleanup corresponde sólo a cargas que nunca llegaron a confirmarse y no contradice la retención de evidencia válida.

Una carga confirmada es inmutable: no existe endpoint para reemplazar sus bytes ni editar su metadata autoritativa. La eliminación administrativa exige un motivo de 3 a 240 caracteres y crea una sola vez un tombstone con `deleted_at`, `deleted_by` y `deletion_reason`; deja de listar y servir el comprobante, pero conserva tanto la fila como el blob físico. PostgreSQL bloquea el borrado físico y la mutación de metadata. No existe purga, expiración, restauración ni lifecycle automático hasta que el negocio defina una política explícita de retención.

Revertir una transacción no elimina ni mueve sus comprobantes. El original mantiene su evidencia y la nueva cabecera `reversal` puede recibir comprobantes propios. Adjuntar o retirar de la vista un comprobante nunca crea una `FinancialTransaction`, una `LedgerEntry` ni un movimiento en el historial.

### Tipos financieros internos

| Tipo                  | Uso                                                                             |
| --------------------- | ------------------------------------------------------------------------------- |
| `opening`             | Movimiento inicial explícito y positivo.                                        |
| `adjustment_increase` | Corrección que agrega dinero.                                                   |
| `adjustment_decrease` | Corrección que quita dinero.                                                    |
| `manual_income`       | Ingreso manual.                                                                 |
| `manual_expense`      | Egreso manual excepcional.                                                      |
| `management_outflow`  | Impacto financiero de un `ManagementOutflow`.                                   |
| `transfer`            | Redistribución neutral entre dos billeteras.                                    |
| `distribution`        | Reasignación versionada, manual o derivada de un pago, hacia varias billeteras. |
| `order_payment`       | Ingreso confirmado originado por un Order.                                      |
| `reversal`            | Corrección compensatoria enlazada al hecho original.                            |

El usuario siempre ingresa importes positivos. El backend determina el signo: todo `management_outflow` crea exactamente una entry negativa, toda `transfer` crea la salida negativa de origen y la entrada positiva de destino, y toda `distribution` debita el importe completo del origen antes de acreditar el reparto.

### Tipos de egreso

| Tipo                  | Etiqueta        | Semántica                                                               |
| --------------------- | --------------- | ----------------------------------------------------------------------- |
| `purchase`            | Compra          | Adquisición de mercadería, materia prima o insumos.                     |
| `expense`             | Gasto           | Salida operativa por servicios u otros conceptos.                       |
| `personal_withdrawal` | Retiro personal | Salida no operativa que debe permanecer separada para métricas futuras. |

`manual_expense` continúa disponible como herramienta excepcional. El flujo normal orienta al administrador hacia Compra, Gasto o Retiro personal.

## Categorías

Las categorías se persisten en `management_outflow_categories`; no son un enum de aplicación. Cada una pertenece a un tipo de egreso y usa `(type, code)` como identidad estable. El slice inicial carga:

- compras: Productos / mercadería, Materia prima, Materiales / insumos y Otros;
- gastos: Materiales / insumos, Servicios y Otros;
- retiros personales: Personal.

El tipo y el código de una categoría son identidad inmutable; su nombre, estado y orden pueden evolucionar. Una categoría puede desactivarse para impedir selecciones nuevas sin romper historia. Las categorías usadas no se eliminan por cascada. Este slice no incorpora una UI de administración del catálogo; su CRUD queda diferido.

## Creación atómica

`CreateManagementOutflow` recibe `ManagementOutflowData` construido desde un Form Request autorizado y validado. Dentro de una única transacción PostgreSQL:

1. bloquea la billetera y rechaza su uso si está archivada;
2. bloquea la categoría, exige que esté activa y corresponda al tipo solicitado;
3. crea la `FinancialTransaction` con tipo `management_outflow`;
4. crea una `LedgerEntry` por el negativo del importe positivo;
5. crea el `ManagementOutflow` con su relación estructurada.

Una falla en cualquier paso revierte el egreso administrativo, la cabecera y la entry. No se bloquea para sumar saldos: la agregación de entries inmutables conserva su validez bajo writers concurrentes.

## Transferencias entre billeteras

`CreateManagementTransfer` recibe `ManagementTransferData` construido desde un Form Request autorizado y validado. Dentro de una única transacción PostgreSQL:

1. rechaza origen y destino iguales;
2. resuelve y bloquea ambas billeteras por `id` ascendente, independientemente del sentido solicitado;
3. exige que ambas existan y estén activas;
4. crea una `FinancialTransaction` de tipo `transfer`;
5. crea exactamente dos entries: `-amount` en origen y `+amount` en destino;
6. crea el `ManagementTransfer` con su vínculo 1:1.

El orden determinista de locks evita que transferencias opuestas `A -> B` y `B -> A` adquieran las filas en orden inverso. No se comprueba saldo suficiente: una billetera puede quedar negativa. Cualquier falla revierte entidad semántica, cabecera y ambas entries.

La escritura se expone como `POST /admin/management/transfers` (`admin.management.transfers.store`) dentro del grupo administrativo existente. No hay endpoints de actualización ni eliminación para transferencias confirmadas.

La transferencia es neutral por construcción:

```text
SUM(entries de la transferencia) = -amount + amount = 0
```

Por eso no es un ingreso, un egreso operativo ni un retiro personal. Sólo redistribuye los saldos individuales. Al crearla, ambas billeteras están activas y el total operativo tampoco cambia; si una se archiva después, su exclusión del total de billeteras activas responde al archivado, no a la transferencia.

## Reglas y distribuciones versionadas

`CreateDistributionRule` crea la identidad lógica y su primera versión; `CreateDistributionRuleVersion` agrega una versión sin modificar las anteriores; `SetDistributionRuleActive` controla si la regla puede elegirse para una aplicación nueva. Sólo una regla puede estar activa globalmente: activar otra reemplaza la selección anterior dentro de la misma operación. Las versiones y sus allocations no se editan ni eliminan.

Los porcentajes se almacenan como basis points enteros: `100 % = 10.000 bps`. Cada allocation debe ser mayor que cero, cada billetera aparece una sola vez y la suma de una versión debe ser exactamente `10.000`. Una versión nueva admite únicamente billeteras activas; desactivar una regla o archivar después una billetera no rompe la lectura ni la reversión de aplicaciones históricas.

`CreateManagementDistribution` recibe una billetera origen, una versión, un importe positivo en pesos enteros, fecha efectiva y descripción opcional. Dentro de una única transacción PostgreSQL:

1. bloquea la regla, su versión, la billetera origen y las billeteras destino en orden determinista;
2. exige que la regla esté activa, que la versión pertenezca a ella y que origen y destinos continúen activos;
3. rechaza que la billetera origen sea también uno de los destinos;
4. calcula cada parte con división entera usando `floor(amount * percentage_bps / 10.000)`;
5. asigna el remainder íntegro a la última allocation del orden canónico `(sort_order, wallet_id)`;
6. rechaza toda la operación si cualquier importe calculado es cero;
7. crea una cabecera `distribution`, una entry `-amount` en el origen, una entry positiva por destino, el `ManagementDistribution` y sus snapshots relacionales.

La preview de React usa la misma regla de redondeo para informar, pero no es autoritativa: el backend recalcula. La conservación exacta queda protegida:

```text
SUM(snapshot amounts) = distribution amount
SUM(destination entries) = distribution amount
SUM(all distribution entries) = -amount + amount = 0
```

La distribución no exige saldo suficiente: reasigna el estado registrado y puede dejar negativo el origen, del mismo modo que los demás movimientos registran la realidad financiera sin ocultarla. No usa una billetera ficticia ni crea dinero. Un cobro registra primero dinero externo en su billetera receptora; la distribución automática posterior usa esa misma billetera como origen y conserva valor.

## Cobros confirmados de Orders

La integración adopta una entidad intermedia —Opción B— porque `payment_status = paid` puede cambiar y no constituye por sí solo evidencia financiera inmutable. `ManagementOrderPayment` vincula estructuralmente un `Order` con una única `FinancialTransaction`; no usa descripción libre ni un origen polimórfico general.

Existen tres caminos administrativos hacia `paid`:

1. crear un Order ya pagado;
2. editar un Order y transicionar de no pagado a pagado;
3. usar el cambio rápido de `UpdateOrderPaymentStatus`.

Los tres emiten síncronamente `OrderPaymentConfirmed(orderId, amount, confirmedOn, adminId)` dentro de la misma transacción que confirma el estado operativo. `RecordConfirmedOrderPayment` delega en `CreateManagementOrderPayment`, que:

1. resuelve la única billetera activa marcada `is_order_payment_default`;
2. crea una cabecera `order_payment` con la fecha comercial de confirmación;
3. crea exactamente una entry positiva por el total final persistido del pedido;
4. crea el `ManagementOrderPayment` con referencias a Order, billetera y cabecera;
5. intenta aplicar la versión más reciente de la única regla activa mediante `ApplyDistributionToOrderPayment`.

La fecha efectiva es el día calendario del negocio en que se confirma `paid`; no es `created_at`, `delivery_date` ni la fecha original del pedido. Si falta una billetera predeterminada activa, toda la transición falla y no queda el Order pagado sin su ingreso. La configuración admite una sola billetera predeterminada; archivarla requiere elegir antes otro destino.

La aplicación automática reutiliza `CreateManagementDistribution`: recibe la billetera, importe y fecha efectiva del pago, fija la versión concreta seleccionada y persiste el mismo reparto, redondeo, snapshot y ledger conservativo que el flujo manual. `management_distributions.management_order_payment_id` es anulable para no afectar distribuciones manuales y único para garantizar que cada pago origine cero o una distribución. El bloqueo del pago y esa unicidad protegen también reintentos y ejecuciones concurrentes.

La ausencia de una regla activa no invalida el ingreso externo. El pago queda visible como **Pendiente de distribución** y puede reintentarse desde Gestión cuando exista una regla; no se inventan porcentajes ni se elige una regla arbitraria. En cambio, una falla técnica después de seleccionar una regla se propaga: como el listener es síncrono, la confirmación de `paid`, el ingreso y la distribución revierten juntos y no dejan ledger parcial.

La unicidad de `management_order_payments.order_id` protege la idempotencia del ingreso incluso ante una entrega repetida del evento o escrituras concurrentes. Repetir el mismo hecho devuelve el registro existente; la relación única del reparto tampoco crea una segunda distribución. Los Orders que ya estaban pagados antes de estas migraciones no se completan retrospectivamente: sin una confirmación histórica verificable, un backfill inventaría fecha, responsable, destino o regla. La migración de Slice 5.1 agrega la referencia anulable sin asociar ni distribuir datos legacy.

Volver un Order a no pagado o cancelarlo no elimina ni revierte automáticamente el cobro. Cuando existe una distribución asociada no revertida, la reversión del pago se rechaza: el administrador debe revertir primero la distribución y después el pago. Ambas correcciones reutilizan `ReverseFinancialTransaction`, conservan los originales y evitan un estado donde se retire el ingreso mientras el reparto continúa aplicado.

La reversión no habilita recrear otro `ManagementOrderPayment` ni otra distribución para el mismo Order. Si una edición económica posterior cambia el total del pedido, el hecho financiero original conserva su importe y requiere revisión manual. Un modelo futuro de pagos separados deberá representar un repago real; no se resuelve eliminando el origen ni liberando sus restricciones únicas.

## Saldo y métricas

El saldo de una billetera es `SUM(ledger_entries.amount)`. No existe `wallet.balance` ni una acción para editarlo. Se permite saldo negativo para registrar la realidad financiera aunque la billetera no tenga fondos suficientes según el ledger.

El saldo total incluye únicamente billeteras activas. Las billeteras archivadas conservan su saldo histórico, pero se excluyen del total operativo.

Las métricas del período provienen del backend y distinguen:

- ingresos: manuales y cobros confirmados de Orders;
- egresos operativos: compras y gastos;
- retiros personales.

Un retiro personal no se presenta como gasto operativo. Estas cifras no representan ventas, cobros de Orders, ganancia, margen ni rentabilidad.

Las transferencias y sus reversiones quedan fuera de ingresos, egresos operativos y retiros personales. La suma global del ledger no cambia; únicamente se compensan los saldos de las billeteras involucradas.

Las distribuciones y sus reversiones también quedan fuera de ingresos, egresos operativos, retiros personales y transferencias. Se informan como **Distribuciones internas**. Su suma de entries es cero, por lo que sólo cambia la composición entre billeteras y el saldo global permanece intacto. Un `order_payment` sí aumenta ingresos y el saldo global porque registra dinero externo ya cobrado; no altera egresos, retiros, transferencias ni distribuciones.

## Historial

`BuildManagementOverview` compone billeteras, saldos, categorías, reglas/versiones seleccionables, métricas e historial paginado. El historial financiero continúa unificado: cuando una transacción proviene de `ManagementOutflow`, el resource agrega su tipo, categoría, concepto, contraparte e importe para mostrar Compra, Gasto o Retiro personal en lugar de una entry genérica. Cuando proviene de `ManagementTransfer`, presenta un solo hecho `Origen -> Destino`; su detalle expone los dos impactos sin duplicar la fila principal. Cuando proviene de `ManagementDistribution`, muestra un único hecho `Origen -> destinos` con nombre y número de versión de la regla; el detalle presenta primero el débito del origen y luego los créditos preservados. Cuando proviene de `ManagementOrderPayment`, presenta **Venta / Pago de pedido**, el identificador público, cliente, monto, billetera destino y fecha efectiva; no degrada el origen de negocio a una transacción genérica.

El pago y la distribución aparecen como dos hechos separados porque uno incorpora dinero externo y el otro sólo lo reasigna. Sus detalles enlazan la relación estructural en ambos sentidos: el pago muestra Pendiente, Distribuido o Distribución revertida, y la distribución automática conserva el Order y el pago que la originaron. Las relaciones se cargan anticipadamente para evitar N+1 al mostrar Order, cliente, versión y reparto.

Los filtros admiten período, billetera, tipo financiero o semántico y categoría. Filtrar una billetera incluye cualquier transferencia o distribución donde participe como origen o destino porque la consulta busca su impacto en `ledger_entries`. Las relaciones se cargan anticipadamente para mantener acotada la cantidad de queries. React presenta el read model; no recalcula saldos, signos ni métricas autoritativas.

## Reconciliación y auditoría financiera

`GET /admin/management/audit` (`admin.management.audit.index`) responde una pregunta acotada: si el ledger y las entidades semánticas cuentan la misma historia. Es una proyección administrativa de **solo lectura**; detecta y explica situaciones, pero nunca actualiza el ledger, una cabecera financiera ni su origen semántico.

Los findings se derivan al consultar. No existe una tabla `management_audit_findings`, un estado duplicado ni un workflow de resolución persistido. Cuando los datos dejan de cumplir la condición, el finding desaparece en la consulta siguiente. Incorporar reconocimiento, resolución o notas administrativas requerirá demostrar primero que ese historial aporta valor y modelarlo en una entidad específica, no convertir cada diferencia calculada en una fila mutable.

La presencia o ausencia de comprobantes no participa en la reconciliación de Slice 6. Un documento no demuestra por sí mismo que el ledger sea correcto y el negocio no definió una obligación documental universal; por eso una operación sin attachments no genera `info`, `warning` ni `critical`. Si más adelante se necesita una métrica administrativa de cobertura documental, deberá permanecer separada de los findings financieros.

### Catálogo y severidades

| Finding                              | Severidad  | Significado                                                                                            |
| ------------------------------------ | ---------- | ------------------------------------------------------------------------------------------------------ |
| `pending_distribution`               | `info`     | El cobro es íntegro, no fue revertido y todavía no posee una distribución; es válido y reintentable.   |
| `reversed_payment`                   | `warning`  | El ingreso de un Order fue compensado y permanece visible como historia.                               |
| `reversed_distribution`              | `warning`  | La distribución fue compensada; el original y su reparto permanecen intactos.                          |
| `missing_semantic_source`            | `critical` | Una cabecera de un tipo que exige origen semántico no posee la relación correspondiente.               |
| `amount_mismatch`                    | `critical` | El importe semántico, la cabecera, las entries o los snapshots no coinciden según la operación.        |
| `wallet_mismatch`                    | `critical` | Las billeteras esperadas por el origen semántico no coinciden con los impactos del ledger.             |
| `date_mismatch`                      | `critical` | La fecha efectiva del origen semántico no coincide con su cabecera o con una relación vinculada.       |
| `ledger_structure_mismatch`          | `critical` | La cantidad, signo o conservación de las entries no cumple la estructura del tipo financiero conocido. |
| `missing_active_distribution_rule`   | `warning`  | No existe una regla activa para distribuir cobros nuevos o pendientes.                                 |
| `multiple_active_distribution_rules` | `critical` | Existe más de una regla activa pese a la unicidad esperada.                                            |
| `missing_default_income_wallet`      | `warning`  | No existe una billetera predeterminada para registrar cobros nuevos.                                   |
| `multiple_default_income_wallets`    | `critical` | Existe más de una billetera predeterminada pese a la unicidad esperada.                                |
| `archived_default_income_wallet`     | `critical` | La billetera predeterminada está archivada y no puede recibir operaciones nuevas.                      |

`info` señala una condición válida que puede requerir trabajo operativo; `warning` conserva visibilidad sobre una compensación o una configuración ausente; `critical` indica que relaciones persistidas contradicen una invariante estructural conocida. La pantalla no eleva tipos desconocidos a críticos: se omiten hasta definir una regla verificable.

Los findings de configuración usan el tipo filtrable `configuration`. Los casos de multiplicidad y wallet predeterminada archivada son defensivos: PostgreSQL los impide normalmente, pero la auditoría conserva una explicación crítica si la integridad fue deshabilitada o los datos provinieron de una importación. Una billetera archivada que sólo aparece en historia no produce ningún finding.

### Reglas de reconciliación

| Operación            | Estructura esperada                                                                                               |
| -------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `order_payment`      | Un `ManagementOrderPayment`, una entry positiva por el importe, misma billetera y misma fecha efectiva.           |
| `management_outflow` | Un `ManagementOutflow` y exactamente una entry negativa por el importe en su billetera.                           |
| `transfer`           | Un `ManagementTransfer`, exactamente una salida y una entrada entre billeteras distintas, con suma cero.          |
| `distribution`       | Un `ManagementDistribution`, un débito de origen y N créditos coincidentes con snapshots, con suma completa cero. |
| `reversal`           | Las mismas billeteras e importes del original con signos exactamente opuestos; el original permanece intacto.     |

Para un pago distribuido también deben coincidir pago y distribución en importe, fecha efectiva y billetera receptora/origen. Sus administradores pueden diferir: `ManagementDistribution.created_by` identifica a quien aplicó o reintentó el reparto, no necesariamente a quien confirmó el pago.

El saldo auditado de cada billetera se obtiene exclusivamente con `SUM(ledger_entries.amount)` y se presenta como `wallet_id`, nombre, saldo derivado, cantidad de transacciones, última fecha efectiva y estado. El estado toma la mayor severidad `warning` o `critical` dentro del período auditado y considera tanto la wallet semántica como todas las wallets impactadas por el ledger; los filtros visuales de tipo, severidad y estado no pueden ocultar esa señal. Un `info` como `pending_distribution` no la degrada. **OK** significa que no existe un warning o critical para esa billetera dentro del período auditado, no que toda la base esté conciliada ni que exista una certificación contable.

El resumen se calcula para el período y la wallet seleccionados antes de aplicar los filtros visuales de tipo, severidad o estado. `pending`, `warnings` y `critical` cuentan los findings de ese universo; `ok` cuenta operaciones auditadas sin findings dentro del mismo universo. Filtrar la lista no puede convertir una operación excluida en OK ni ocultar una alerta del resumen.

El total global del ledger no necesita ser cero: `order_payment` e ingresos reales lo incrementan, mientras que egresos reales lo reducen. La conservación en cero corresponde sólo a operaciones internas como transferencias y distribuciones.

### Estados válidos e historia

- un pago sin distribución es **Pendiente de distribución**, no una inconsistencia crítica;
- una operación y su reversión permanecen visibles y explicables;
- una billetera archivada puede aparecer en pagos, egresos, transferencias, distribuciones y reversiones históricos sin producir un finding;
- una versión histórica no se invalida porque una billetera destino se archive después;
- los Orders que ya estaban pagados antes de `ManagementOrderPayment` no se infieren, no se backfillean y no generan findings críticos;
- una edición económica posterior puede hacer que el total actual del Order difiera de su cobro original; la auditoría lo expone para revisión, pero no reescribe ninguno de los hechos.

### Filtros y costo de consulta

La UI ofrece hoy, últimos 7 días, últimos 30 días o un rango acotado y envía `from`/`to`. También admite `wallet_id`, `severity`, `status` y `type`.

`type` busca sobre dos dimensiones con el mismo control: acepta un tipo de operación —`order_payment`, `management_outflow`, `transfer`, `distribution`, `reversal` o `configuration`— o cualquiera de los códigos de finding del catálogo anterior. Por ejemplo, `distribution` reúne los findings cuyo origen es una distribución, mientras que `amount_mismatch` reúne esa inconsistencia a través de todos los tipos de operación. `status` agrupa `ok`, `pending`, `reversed` y `requires_review` sin persistir estado adicional.

El período usa `financial_transactions.effective_date`; los filtros de billetera se resuelven por participación en `ledger_entries` o por la relación semántica correspondiente. Resumen, findings paginados y estado de billeteras conservan el mismo alcance filtrado para que una selección no produzca un OK aparente sobre datos que la propia consulta excluyó.

El backend usa joins, `EXISTS`, agregaciones agrupadas y eager loading; no consulta una entidad semántica por cada transacción. Los balances se agregan por billetera y los findings se paginan para que el costo permanezca acotado con miles de movimientos. Los índices existentes sobre tipo/fecha de cabecera, billetera/transacción de entries y claves foráneas únicas cubren el acceso actual; no se agregan índices redundantes sin evidencia de una consulta real.

La única acción ofrecida por un finding es navegar al detalle responsable. `pending_distribution` puede llevar al reintento seguro ya existente. Un finding crítico sólo ofrece inspección: nunca **Corregir automáticamente**.

## Inmutabilidad y reversiones

Los egresos, las transferencias y las distribuciones confirmados no exponen edición ni eliminación. Corregir importe, billetera, tipo, categoría, fecha o concepto requiere una reversión y una nueva operación correcta:

```text
egreso original
+ reversión compensatoria
+ nuevo egreso correcto, si corresponde
```

La reversión reutiliza `ReverseFinancialTransaction`: bloquea la cabecera original, comprueba que no sea una reversión y que todavía no tenga otra, y crea una nueva transacción con entries opuestas. El origen semántico original permanece intacto y su estado revertido se deriva de la relación financiera. En una transferencia, la reversión intercambia los signos de sus dos entries y continúa siendo neutral. En una distribución, acredita el origen y debita cada destino por su impacto original. En un cobro sin reparto activo, crea una entry negativa exacta en la billetera original; si su distribución sigue aplicada, primero exige revertirla. No modifica `payment_status` ni habilita un segundo cobro.

Una reversión puede compensar una billetera archivada después del egreso, la transferencia o la distribución. Impedirlo eliminaría la vía segura de corregir historia. No se permite revertir una reversión ni revertir dos veces el mismo origen.

## Integridad en PostgreSQL

Las migraciones de Management protegen:

- tipos financieros y tipos de egreso cerrados a los casos implementados;
- importe administrativo positivo, distinto de cero y sin fracción;
- entries distintas de cero y expresadas en pesos enteros;
- claves foráneas restrictivas hacia categoría, billetera, transacción y administrador;
- unicidad 1:1 de `management_outflows.financial_transaction_id`;
- unicidad 1:1 de `management_transfers.financial_transaction_id` y origen distinto de destino;
- correspondencia de tipo/categoría, administrador y fecha entre el egreso y su cabecera;
- exactamente una entry negativa cuya billetera e importe coinciden con el egreso;
- existencia de un origen semántico para toda cabecera `management_outflow`;
- correspondencia de administrador y fecha entre transferencia y cabecera `transfer`;
- exactamente dos entries, `-amount` en origen y `+amount` en destino, cuya suma es cero;
- existencia de un origen semántico para toda cabecera `transfer`;
- versiones de regla numeradas de forma única, allocations positivas y ordenadas, billeteras no repetidas y suma exacta de `10.000` bps;
- inmutabilidad de versiones y allocations, y una única regla lógica activa globalmente;
- relación 1:1 entre `management_distributions` y cabeceras `distribution`, con billetera origen obligatoria, administrador, fecha e importe coincidentes;
- referencia anulable y única desde una distribución automática a `management_order_payments`, con origen, importe y fecha coincidentes con el pago; el administrador corresponde a quien aplicó el reparto;
- origen distinto de todos los destinos, exactamente una entry negativa `-amount` en el origen y una entry positiva por allocation;
- snapshots relacionales de distribución que coinciden exactamente con la versión usada, sus entries positivas y el importe total, con suma completa de entries igual a cero;
- existencia de un origen semántico para toda cabecera `distribution`;
- a lo sumo una billetera activa marcada `is_order_payment_default` y prohibición de archivar la marcada;
- relación única `management_order_payments.order_id` y vínculo 1:1 mediante `financial_transaction_id`;
- coincidencia de administrador, fecha e importe entre `ManagementOrderPayment`, su cabecera `order_payment` y una única entry positiva en la billetera destino;
- existencia de un origen semántico para toda cabecera `order_payment`;
- prohibición de revertir un `order_payment` mientras su distribución asociada continúe aplicada;
- al menos una entry para cada cabecera financiera;
- una sola reversión por transacción original;
- rechazo de `UPDATE` y `DELETE` sobre cabeceras, entries, egresos, transferencias, versiones, distribuciones y snapshots confirmados.

Las validaciones de aplicación aportan autorización y errores comprensibles. Los constraints y constraint triggers diferibles protegen también escrituras directas o secuencias incompletas al confirmar la transacción. La migración que agrega el origen se aborta si detecta distribuciones legacy sin una billetera origen verificable: no inventa ni deduce una para hacer pasar datos ambiguos.

## Seguridad y observabilidad

Todas las rutas usan `auth`, `verified`, `admin`, CSRF y `admin.request.log`. Los Form Requests autorizan mediante policies y validan tipos, categoría, reglas/versiones, billeteras, porcentajes, orden, importe, fecha y longitudes. El backend vuelve a validar el estado activo dentro de la operación atómica.

`GET|POST /admin/management/transactions/{financialTransaction}/attachments` lista o carga comprobantes. `GET|DELETE /admin/management/transactions/{financialTransaction}/attachments/{managementAttachment}` sirve bytes o crea el tombstone; el `GET` acepta únicamente `?disposition=inline|attachment`. Todas resuelven y comprueban el vínculo entre cabecera y attachment, autorizan mediante las policies de Management y nunca exponen `disk` ni `path`. No existe una URL directa bajo `/storage`. Un archivo ausente o fuera del prefijo permitido responde sin revelar rutas internas. La respuesta usa el MIME preservado, un nombre de descarga seguro y encabezados que impiden interpretar contenido activo fuera de la allowlist.

Los eventos estables son:

- `[Management] wallet.created|updated|archived|restored`;
- `[Management] transaction.created|reversed`;
- `[Management] outflow.created|reversed`;
- `[Management] transfer.created|reversed`;
- `[Management] distribution.rule_created`;
- `[Management] distribution.rule_version_created|rule_status_changed`;
- `[Management] distribution.created|reversed`.
- `[Management] order_payment.created`.
- `[Management] order_payment.reversed`.
- `[Management] order_payment.distribution_created|distribution_skipped|distribution_failed`.
- `[Management] attachment.created|deleted`.
- `[Management] attachment.file_missing|file_corrupt`.

Los eventos incluyen identificadores internos, administrador, tipo, categoría, regla o versión cuando corresponde. Para comprobantes incluyen únicamente attachment, cabecera financiera y administrador. Omiten importe, descripción, contraparte, nombre original, MIME, tamaño, checksum, path, disk, motivo de eliminación, contenido, porcentajes completos, snapshots, payloads y modelos serializados. El ledger, los orígenes semánticos y la metadata documental persistida son la evidencia durable; los logs sólo ofrecen correlación operativa.

## Experiencia administrativa

Gestión mantiene una sola superficie responsive. Las acciones principales son Registrar compra, Registrar gasto, Retiro personal, Transferir, Crear regla y Distribuir dinero; el movimiento manual queda como acción secundaria. La gestión de billeteras permite marcar un único destino predeterminado para cobros de Orders. Crear regla solicita nombre y destinos ordenados con billetera y porcentaje; una nueva versión reutiliza la identidad lógica sin editar las anteriores. Activar una regla reemplaza la activa anterior.

El formulario de distribución manual solicita billetera origen, regla/versión vigente, importe positivo, fecha efectiva y descripción opcional. Su preview muestra el origen con `-amount` y cada destino con su porcentaje e importe positivo, incluido el remainder determinista de la última allocation. Los selectores nuevos sólo ofrecen reglas activas y billeteras activas, y el formulario impide elegir como origen una billetera incluida en los destinos. El historial conserva el origen, el nombre y los destinos aplicados aunque las billeteras se archiven o cambie la configuración.

El detalle de un pago muestra su estado de distribución, la regla y versión preservadas y un enlace al reparto. Si falta una regla, presenta **Pendiente de distribución** y permite reintentar desde el mismo detalle después de configurar Management. El detalle de la distribución enlaza de regreso al pago y al Order. La reversión del pago explica el orden obligatorio cuando el reparto continúa aplicado.

Cada detalle financiero incorpora una sección **Comprobantes** que carga metadata sólo al abrir la operación. Presenta nombre original, formato, tamaño y fecha, permite adjuntar varios archivos y ofrece preview para PDF e imágenes o descarga autorizada. En mobile cada archivo se recompone como card sin overflow horizontal y las acciones conservan objetivos táctiles mínimos de 44 px. El historial puede mostrar `attachments_count`, pero nunca carga bytes ni todas las filas documentales para construir el overview.

Auditoría reutiliza los mismos tokens y composición administrativa. En escritorio presenta resumen, findings, saldos por billetera y movimientos que requieren atención; en mobile recompone el contenido como cards sin tablas anchas ni overflow horizontal. El resumen separa OK, pendientes, warnings y críticos, y cada control interactivo mantiene un objetivo mínimo de 44 px. Esta sección no reemplaza ni anticipa un Dashboard financiero completo.

En escritorio, el historial usa una tabla compacta y permite abrir el detalle. En mobile se recompone como cards/filas sin tabla horizontal. Los formularios y detalles usan Dialog/Sheet según los patrones existentes, tokens `admin-*` y objetivos táctiles mínimos de 44 px.

## Verificación

```bash
# Backend focalizado
make artisan ARGS="test tests/Feature/Management"

# E2E responsive focalizado
make npm ARGS="run test:e2e -- tests/e2e/management.spec.ts"
```

Los escenarios mutables crean identidades con namespace de proyecto, worker y reintento, y luego localizan exactamente esos registros. Las transferencias usan dos billeteras propias por escenario y comprueban saldos individuales, neutralidad, historial semántico y reversión. Las distribuciones manuales crean una billetera origen con saldo inicial y tres destinos, prueban reparto, conservación y reversión. Los pagos crean su billetera predeterminada, una regla y un Order propios; comprueban ingreso único, reparto automático, saldo global, versión preservada, estado pendiente sin regla y orden de reversión. Desktop y mobile comparten la base de Playwright y no deben colisionar ni depender de una primera fila global.

## Fuera de alcance

- CRUD administrativo de categorías;
- proveedores como dominio;
- backfill de Orders pagados antes de Slice 5;
- reversión automática por cancelar o volver a no pagado;
- recreación o corrección estructurada del cobro después de una edición económica;
- Dashboard financiero avanzado, ganancia, rentabilidad o Analytics;
- integración con Inventory o cualquier modificación automática de stock.

La separación entre el origen semántico y el ledger se registra en [ADR 0007](decisions/0007-semantic-management-outflows.md). El versionado y conservación de distribuciones se registra en [ADR 0008](decisions/0008-versioned-immutable-management-distributions.md). El límite idempotente y su distribución automática se registra en [ADR 0009](decisions/0009-idempotent-order-payments-in-management.md). La evidencia documental privada y su retención conservadora se registran en [ADR 0011](decisions/0011-private-management-document-attachments.md).

## Rich Purchase integration

The new Purchase aggregate is documented in [PURCHASES.md](PURCHASES.md). Its ledger impact and attachments use Management; physical receipts and signed cost compensation use Inventory. Existing simple purchases remain historical Management outflows. Existing operating-outflow totals and the Purchase filter include rich and legacy facts with signed reversals; expanded overview metrics remain scoped to AOB-7.
