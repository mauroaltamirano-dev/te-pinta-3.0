# 0008: Distribuciones de Gestión versionadas e inmutables

Status: accepted
Date: 2026-08-27
Amended: 2026-08-28

## Contexto

Gestión necesita aplicar una regla porcentual a varias billeteras sin conectar todavía los pagos de Orders. Las reglas cambian con el tiempo, pero una aplicación histórica debe conservar qué versión, destinos, porcentajes e importes produjo. Recalcular desde la configuración vigente o guardar sólo un JSON impediría verificar relaciones e invariantes con PostgreSQL.

El dinero se expresa en pesos enteros. La división porcentual puede dejar un remainder y `ledger_entries` prohíbe importes cero. La primera implementación creó sólo entries positivas porque todavía no existía integración con Orders; eso incrementaba el total del ledger y confundía una reasignación interna con creación de dinero. La conservación de valor no puede depender de una integración futura: toda distribución necesita identificar desde qué billetera sale el dinero existente.

## Decisión

Separar una `DistributionRule` lógica de sus `DistributionRuleVersion` inmutables. Cada versión contiene allocations relacionales ordenadas hacia billeteras, expresadas en basis points enteros. Una versión es válida sólo si cada allocation es positiva, no repite billetera y la suma es exactamente `10.000` bps.

Aplicar una versión crea un `ManagementDistribution` inmutable vinculado 1:1 con una `FinancialTransaction` de tipo `distribution`. La distribución requiere una `source_wallet_id` real, activa al aplicar y distinta de todas las billeteras destino. Su ledger contiene exactamente una entry `-amount` en el origen y una entry positiva por destino; la suma completa debe ser cero.

Cada destino se conserva además como snapshot relacional en `management_distribution_allocations`. El snapshot guarda billetera, nombre al aplicar, orden, `percentage_bps` e importe aplicado; complementa la referencia estructurada a la versión y no usa JSON como fuente del historial.

El reparto calcula primero `floor(amount * percentage_bps / 10.000)` para cada allocation en orden canónico `(sort_order, wallet_id)`. El remainder completo se suma a la última allocation de ese orden. La distribución se rechaza si cualquier resultado es cero. El backend recalcula siempre; la preview sólo informa.

Una regla lógica puede activarse o desactivarse y recibir versiones nuevas. Las versiones, sus allocations, las distribuciones y sus snapshots no se actualizan ni eliminan. Corregir una aplicación requiere una única reversión por entries exactamente opuestas y una distribución nueva. La reversión acredita el origen y debita los destinos por los importes originales, conserva suma cero y sigue permitida si cualquiera de esas billeteras fue archivada después.

La creación admite únicamente origen y destinos activos, pero los históricos permanecen visibles y reversibles sin reactivar billeteras. La migración de esta enmienda se aborta si encuentra distribuciones anteriores sin un origen verificable: no inventa una billetera ni reescribe hechos financieros ambiguos.

Slice 5 conectará un pago de Order con esta operación de forma idempotente. Esa integración aportará el disparador y la trazabilidad del cobro; no relajará la conservación de valor ya exigida por el ledger.

## Consecuencias

- Las versiones anteriores y sus aplicaciones permanecen auditables cuando cambia la regla.
- PostgreSQL puede proteger porcentajes, unicidad, correspondencia con el ledger e inmutabilidad sin interpretar documentos JSON.
- El redondeo es exacto y determinista: nunca se pierden ni crean pesos dentro del importe distribuido.
- Los importes demasiado pequeños para producir una entry positiva por destino fallan completos; no se silencian allocations ni se crean entries cero.
- El historial presenta una distribución como un único hecho semántico y reserva el detalle para el origen y sus N destinos.
- Cada distribución y su reversión conservan valor: `SUM(ledger_entries) = 0`.
- El historial puede explicar `origen -> destinos` sin degradar la distribución a una transferencia manual.
- Integrar Orders no requerirá corregir saldos creados artificialmente; deberá resolver idempotencia, eventos de pago y trazabilidad.

## Alternativas consideradas

### Editar porcentajes sobre una única regla

Rechazado porque reescribiría el significado histórico de aplicaciones anteriores.

### Guardar el snapshot sólo como JSON

Rechazado porque debilitaría claves foráneas, restricciones e inspección SQL. Un JSON complementario podría agregarse si aparece una necesidad de presentación, pero nunca reemplazará el snapshot relacional.

### Distribuir el remainder por mayor porcentaje o al primer destino

Rechazado porque no aporta una ventaja frente a usar la última allocation canónica y agrega otra regla de prioridad. El orden explícito más `wallet_id` ofrece un desempate estable.

### Crear una billetera origen ficticia

Rechazado porque inventaría un hecho financiero. La distribución debe debitar una billetera real elegida por el administrador; Orders aportará luego el disparador y la trazabilidad, no un origen contable ficticio.

### Forzar neutralidad con una entry negativa sin origen

Rechazado porque produciría contabilidad aparente sin una entidad responsable ni una relación verificable con el cobro real.

### Mantener sólo entries positivas hasta integrar Orders

Rechazado porque una operación interna no puede crear dinero temporalmente. Postergar la conservación contaminaría saldos e históricos y obligaría a reconciliarlos después.

### Modelar la distribución como transferencias independientes

Rechazado porque perdería la regla y versión aplicadas, fragmentaría un único hecho semántico y dificultaría garantizar que el reparto completo sea atómico.
