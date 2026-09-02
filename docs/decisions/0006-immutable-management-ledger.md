# 0006: Ledger inmutable para Gestión

Estado: aceptado
Fecha: 2026-08-26

## Contexto

Gestión necesita representar dinero disponible en varias billeteras y permitir correcciones auditables. Un campo de saldo editable perdería el origen de cada cambio, admitiría divergencias bajo concurrencia y dificultaría integrar más adelante compras, transferencias y ventas distribuidas.

No se necesita contabilidad tradicional de partida doble: el dominio actual registra impactos operativos por billetera y una futura transacción puede afectar varias billeteras.

## Decisión

`Management` persiste una cabecera `financial_transactions` y una o más filas inmutables `ledger_entries` con importes firmados en pesos enteros. `wallets` no almacena saldo; cada saldo se deriva con `SUM(ledger_entries.amount)`.

Toda corrección agrega otro hecho. Una reversión crea una transacción nueva con entries opuestas, enlaza una única vez con `reversal_of_id` y conserva intacto el original. Las operaciones manuales ordinarias rechazan billeteras archivadas, pero una reversión puede compensar una billetera archivada para que el ciclo de vida del recurso no impida reparar el historial.

PostgreSQL rechaza importes cero o fraccionarios, tipos y formas de reversión inválidos, transacciones sin entries y mutaciones destructivas de cabeceras o entries. Laravel conserva la autorización, determina el signo, agrupa las escrituras en transacciones y bloquea únicamente las filas cuyo estado previo decide la operación.

## Consecuencias

- Los saldos permanecen explicables y correctos bajo writers concurrentes sin un contador mutable.
- Se permiten saldos negativos para registrar la realidad financiera.
- Una transacción puede tener múltiples entries; `ManagementTransfer` usa dos impactos opuestos y la misma estructura admite distribuciones futuras.
- Corregir un error agrega historial; no lo oculta.
- Las consultas de saldo agregan entries y podrán requerir una proyección verificable si el volumen futuro lo demuestra.
- La integración con Orders deberá ser idempotente y completa, pero queda fuera de este slice.

## Alternativas consideradas

- **Guardar `wallet.balance`:** rechazado porque crea una segunda fuente de verdad mutable.
- **Editar o borrar movimientos:** rechazado porque destruye evidencia financiera.
- **Implementar event sourcing o CQRS genérico:** rechazado por complejidad no demostrada; el ledger relacional explícito cubre las invariantes actuales.
- **Exigir partida doble contable:** pospuesto porque los impactos operativos por billetera no requieren todavía cuentas contables balanceadas.
