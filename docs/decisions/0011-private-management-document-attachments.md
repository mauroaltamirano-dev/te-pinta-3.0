# 0011: Comprobantes documentales privados vinculados a la transacción financiera

Status: accepted
Date: 2026-08-29

## Contexto

Management necesita responder qué documento respalda una compra, gasto, transferencia, distribución, cobro o reversión. Esa evidencia puede incluir una factura, ticket, recibo o imagen, pero no representa dinero y no debe convertirse en una segunda fuente de importe, fecha, tipo o saldo.

Las operaciones financieras ya convergen en `FinancialTransaction`: las entidades semánticas explican qué ocurrió y `LedgerEntry` registra el impacto. Asociar archivos por separado a cada origen duplicaría tablas y endpoints; una referencia polimórfica general debilitaría la integridad estructural antes de existir otro dominio documental que la justifique.

Los comprobantes financieros contienen información administrativa y requieren privacidad, trazabilidad y retención conservadora. PostgreSQL y el filesystem no comparten una transacción ACID, por lo que la carga también necesita un límite explícito para no dejar metadata incompleta o archivos huérfanos.

## Decisión

Crear `management_attachments` como metadata documental vinculada mediante una FK restrictiva `financial_transaction_id` a una única `FinancialTransaction`. Egresos, transferencias, distribuciones, cobros y reversiones acceden a los comprobantes a través de su cabecera financiera. Una reversión es una cabecera nueva: no mueve ni elimina los archivos del original y puede tener documentación propia.

Los bytes viven en el disco privado `local`, fuera de `storage/public`, bajo `management/attachments/{year}/{month}/{uuid}` sin extensión. El path no incorpora nombre de archivo, cliente, proveedor ni otra PII. `original_name` se conserva sólo como metadata y se sanitiza al presentarlo o construir `Content-Disposition`.

El modelo conserva `disk`, `path`, `original_name`, `mime_type`, `size`, `checksum_sha256`, `uploaded_by` y timestamps. Se permiten inicialmente PDF, JPEG, PNG y WEBP de hasta `10 MB`; el backend inspecciona el MIME real y el tamaño y no confía únicamente en extensión o `Content-Type`. El SHA-256 verifica integridad, pero no activa deduplicación.

Una carga confirmada es inmutable. No existe reemplazo de bytes ni edición de metadata autoritativa. El flujo escribe primero el archivo y luego crea la fila; si la persistencia falla, elimina sólo el archivo recién escrito. Si el storage falla, no crea metadata. Los uploads fallidos no son evidencia confirmada y quedan fuera de la política de retención.

La eliminación administrativa no borra físicamente. Exige un motivo de 3 a 240 caracteres y completa una sola vez `deleted_at`, `deleted_by` y `deletion_reason`; deja de listar y servir el comprobante y conserva fila y blob. PostgreSQL bloquea el borrado físico y la mutación posterior de la metadata protegida. No se implementan restauración, purga, expiración ni lifecycle automático hasta que exista una política de retención aprobada.

Las operaciones de carga, lectura, preview, descarga y tombstone usan el límite administrativo existente: `auth`, `verified`, `admin`, CSRF, `admin.request.log` y policies de Management. Las respuestas nunca exponen `disk` ni `path`; los bytes se sirven por un endpoint autorizado que vuelve a comprobar el registro, el tombstone, la existencia y el prefijo permitido.

Adjuntar o crear un tombstone no crea `FinancialTransaction` ni `LedgerEntry`, no cambia balances o fechas y no aparece como movimiento financiero. El overview puede obtener `attachments_count` mediante una consulta agregada, pero sólo el detalle carga metadata y ninguna lectura de Management carga bytes anticipadamente. La ausencia de comprobante es válida y no genera finding de reconciliación.

Los eventos `[Management] attachment.created` y `[Management] attachment.deleted` contienen sólo IDs de attachment, cabecera financiera y administrador. Nombre, MIME, tamaño, checksum, disk, path, motivo y contenido permanecen fuera de los logs.

## Consecuencias

- Todas las operaciones financieras comparten una relación documental con integridad referencial y sin tablas idénticas.
- El ledger continúa siendo la única fuente de saldo y los orígenes semánticos continúan siendo la autoridad del hecho administrativo.
- Los documentos permanecen privados, inmutables y rastreables aunque la operación se revierta.
- La eliminación visible preserva evidencia y responsabilidad administrativa, a costa de mantener storage ocupado hasta definir retención.
- La aplicación debe coordinar cleanup ante fallas de carga porque la base de datos no puede confirmar o revertir bytes del filesystem.
- El límite inicial evita OCR, extracción fiscal, deduplicación, antivirus integrado, clasificación automática y cualquier interpretación contable del contenido.
- Si aparece multi-tenancy u otro dominio documental, la autorización y el modelo común deberán reevaluarse antes de generalizar la tabla.

## Alternativas consideradas

### Asociar el archivo a cada entidad semántica

Rechazado porque duplicaría relaciones y endpoints para egresos, transferencias, distribuciones y cobros. También obligaría a definir una regla diferente para reversiones, que ya poseen su propia cabecera financiera.

### Usar `attachable_type` y `attachable_id`

Rechazado por ahora porque una relación polimórfica no ofrece una FK completa y amplía el sistema documental sin otro consumidor demostrado. `FinancialTransaction` ya es el límite común estructural.

### Guardar archivos en PostgreSQL

Rechazado porque el proyecto ya usa Laravel Storage y la base debe conservar metadata consultable, no bytes grandes que aumenten backups y costo de lectura del modelo financiero.

### Publicar archivos bajo `/storage`

Rechazado porque una URL predecible evitaría la autorización administrativa y expondría evidencia financiera fuera de su contexto.

### Eliminar físicamente al solicitar borrado

Rechazado porque destruiría evidencia histórica sin una política de retención. El tombstone auditado permite corregir la vista administrativa sin una pérdida silenciosa e irreversible.

### Exigir comprobante o marcar su ausencia como finding

Rechazado porque el negocio no definió una obligación documental universal. La reconciliación financiera verifica relaciones e importes conocidos; no debe inventar una regla contable.
