# Precios de pedidos

`app/Modules/Orders/Pricing/OrderPricingEngine.php` es el único calculador autoritativo de agrupación comercial y totales. El POS, el checkout público y las ediciones administrativas económicas lo invocan; React sólo representa previews del backend.

## Agrupación por familia de precio

Cada Product tiene una familia explícita: `empanada` o `unit`. La familia no se deriva de categoría, slug, nombre ni ID. Sólo `empanada` participa en unidad/media docena/docena/docena combinada y en descuentos por preparación cruda; su algoritmo existente y su redondeo se conservan. `unit` se cotiza como `quantity × unit_price` para cualquier condición, sin agrupación ni descuento crudo. Una cotización puede mezclar ambas familias internamente; no se implementa una superficie POS adicional por ello.

## Entradas y fuentes de precios

El motor recibe las cantidades finales por producto, una condición global del pedido (`cooked` o `raw`), cantidades de adicionales, tipo de entrega y el tipo/valor opcional de descuento administrativo. No recibe precios confiables ni un importe de descuento calculado por el cliente.

| Valor                                          | Fuente                                                   |
| ---------------------------------------------- | -------------------------------------------------------- |
| Precio por unidad/media docena/docena cocinada | `ProductPriceProvider` desde Products                    |
| Descuento para preparación cruda               | `raw_price_discounts` (`unit`, `half_dozen`, `dozen`)    |
| Docena combinada                               | Precios fijos cocinado/crudo de `order_settings`         |
| Adicional                                      | `order_addons.price` disponible                          |
| Envío                                          | `delivery_fee` para delivery; cero para pickup           |
| Descuento administrativo                       | Enum/valor del backend aplicado por `OrderPricingEngine` |

Cocinadas es la condición predeterminada y los precios almacenados en Products son los precios base/principales. Crudas es la alternativa de menor precio: `DatabaseProductPriceProvider` resta el descuento positivo persistido del tier correspondiente. No se modela como un recargo negativo.

La docena combinada mantiene precios independientes en `combined_dozen_cooked_price` y `combined_dozen_raw_price`; no se deriva uno del otro ni de los descuentos por preparación. Sus valores iniciales siguen siendo 17500 cocinada y 16000 cruda. En cambio, el ejemplo de Salteña es específico del producto: 18000 cocinada y 16000 cruda por docena.

Cada cotización nueva también incluye un desglose explícito de preparación. Por línea, el backend conserva `cooked_unit_price`, `cooked_line_total` y `raw_discount`, además de los valores aplicados. En el pedido conserva `products_cooked_subtotal` y `raw_discount`. Para un pedido cocinado, `raw_discount` es cero; para uno crudo, es la diferencia positiva restada al precio cocinado.

## Agrupación determinista

El algoritmo sigue reglas; no es un optimizador genérico del menor precio:

1. Ordena los productos solicitados por ID interno.
2. Consume cada docena completa del mismo producto a su precio de docena.
3. Mientras queden al menos 12 unidades de más de un producto, consume una docena combinada en orden de ID.
4. Para cada remanente, consume medias docenas del mismo producto.
5. Cotiza los remanentes finales como unidades.

El orden por ID del producto desempata composiciones mixtas grandes equivalentes. Hace repetibles los snapshots; no expresa una preferencia comercial.

## Ejemplos normativos

| Cantidades solicitadas | Líneas comerciales            |
| ---------------------- | ----------------------------- |
| 5 A                    | 5 unidades A                  |
| 6 A                    | 1 media docena A              |
| 7 A                    | 1 media docena A + 1 unidad A |
| 12 A                   | 1 docena A                    |
| 18 A                   | 1 docena A + 1 media docena A |
| 3 A + 3 B              | 3 unidades A + 3 unidades B   |
| 6 A + 3 B + 3 C        | 1 docena combinada            |
| 6 A + 6 B              | 1 docena combinada            |
| 24 variadas            | 2 docenas combinadas          |

No existe la media docena mixta. Primero se forman docenas del mismo producto y luego docenas mixtas antes de las medias docenas; por eso 6 A + 3 B + 3 C cierra una docena combinada en lugar de consumir A como media docena.

## Cocinadas y crudas

Los precios de Products son cocinados. Para cotizaciones crudas, `DatabaseProductPriceProvider` resta el `raw_price_discount` persistido correspondiente:

- precio unitario cocinado - descuento unitario crudo;
- precio de media docena cocinada - descuento crudo de media docena;
- precio de docena cocinada - descuento crudo de docena.

Los descuentos crudos son enteros positivos y no pueden superar el precio cocinado correspondiente. La condición pertenece al pedido completo; no se admiten combinaciones crudas/cocinadas por ítem.

## Ahorro de docena combinada

Para cada docena combinada, el motor cotiza la misma composición con la agrupación normal de productos y la compara con el precio fijo configurado para la condición:

```text
savings = max(0, normal composition price - combined price)
```

Sólo se muestra y persiste como ahorro un resultado positivo. Un precio combinado superior al cálculo normal nunca genera un “descuento” negativo engañoso; el precio cobrado sigue siendo el fijo configurado.

## Adicionales y entrega

Los adicionales no son Products. Cada addon disponible se cotiza como `quantity × current addon price`; la cantidad se limita a 1–100 y se rechazan filas duplicadas.

- `pickup`: el envío siempre es cero.
- `delivery`: usa el `delivery_fee` actual y requiere una dirección no vacía al crear.

El costo de entrega inicial es el valor seguro `0`. Configurá el valor real en `/admin/settings/general` antes de tomar pedidos con envío.

## Descuentos administrativos

Los descuentos sólo están disponibles en los flujos administrativos autenticados de creación, edición y Quick Order. Los tipos admitidos son `percentage` (porcentaje entero de 0–100) y `fixed_amount` (pesos enteros). El tipo y el valor deben enviarse juntos.

```text
discountable subtotal = items subtotal + toppings subtotal
discount amount       = min(calculated amount, discountable subtotal)
total                 = discountable subtotal - discount amount + shipping
```

`items subtotal` ya contiene el precio aplicado para la condición global, por lo que el descuento crudo participa en el subtotal descontable. El envío nunca participa. Los porcentajes redondean half-up a pesos enteros; un descuento fijo no puede consumir el envío ni volver negativo el subtotal comercial. El preview y checkout públicos prohíben todos los campos de descuento administrativo.

## Historial persistido y compatibilidad

Crear un pedido persiste la cotización, no una referencia a un cálculo futuro:

- líneas normales: nombre vendido, modo, cantidad de bloques, unidades, precio aplicado y subtotal;
- líneas nuevas: precio/subtotal cocinado y descuento crudo junto a los valores aplicados;
- líneas combinadas: precio fijo aplicado, equivalente cocinado, descuento crudo, total normal de comparación, ahorro y composición de nombre/cantidad;
- adicionales: nombre vendido, cantidad, precio unitario aplicado y subtotal;
- pedido: snapshots del cliente/contacto/dirección, envío, subtotales, ahorro, descuento administrativo, pago y total.

Para pedidos nuevos, la invariante es `items_subtotal = products_cooked_subtotal - raw_discount`. Los campos históricos anteriores `items_base_subtotal`, `cooking_surcharge`, `base_unit_price`, `base_line_total` y `order_items.cooking_surcharge` se conservan para leer pedidos existentes, pero quedan null en pedidos nuevos. Del mismo modo, los campos nuevos quedan null en pedidos históricos: no se reconstruyen con precios o configuración actuales.

Abrir un pedido nunca vuelve a ejecutar pricing. Los cambios actuales de catálogo, clientes, adicionales o Settings no afectan snapshots existentes. Las superficies históricas presentan productos y unidades de forma genérica: no clasifican una línea persistida como empanada según el catálogo actual.

## Edición administrativa y nueva cotización

La página de edición parte de dos valores estructurados del backend:

- un draft reconstruido desde los registros persistidos de ítems, composiciones y adicionales como cantidades reales;
- `original_quote`, construido sólo desde los snapshots actuales del pedido para el resumen anterior.

El hook compartido del draft invoca `/admin/orders/price-preview` cuando cambian productos, cantidades, condición, adicionales, tipo de entrega o descuento. Compara esa cotización del backend con el total persistido; React no calcula un precio reemplazante. El método de pago no es económico y no invalida el preview.

`UpdateOrder` clasifica esos mismos campos como económicos. Si alguno cambió, procesa el pedido completo mediante `OrderPricingEngine` con la configuración y vendibilidad vigentes al confirmar. `PersistOrderQuote` reemplaza los snapshots de ítems, composiciones, adicionales, precios agregados, envío y ahorro en la misma transacción. Ningún otro pedido histórico se recalcula.

Las correcciones sólo de cliente, dirección, fecha/período/hora, notas, pago o estado operativo no reemplazan la cotización. Aun así pasan validación del backend y control de concurrencia optimista; el snapshot persistido permanece intacto.

### Selecciones históricas no disponibles

Un producto/addon no disponible, de categoría inactiva o eliminado lógicamente que ya exista en un pedido permanece visible con su cantidad histórica. Puede sobrevivir una corrección no económica sólo mientras toda la selección económica quede intacta. No puede incrementarse ni agregarse de nuevo. Antes de confirmar una edición económica se deben quitar o reemplazar las selecciones históricas no disponibles, porque las reglas actuales no pueden cotizarlas autoritativamente.

## Límites y rechazos

- Se requiere al menos un producto.
- Cada producto puede aparecer una vez con cantidad 1–120.
- Cada request contiene como máximo 50 productos.
- Cada adicional puede aparecer una vez con cantidad 1–100; máximo 20 filas.
- Las selecciones inexistentes, no disponibles o eliminadas lógicamente fallan al cotizar. La hidratación de edición puede mostrar una selección histórica intacta bajo la política explícita anterior.
- La selección pública usa slugs; la administrativa usa IDs internos validados.

## Checklist de cambios

1. Cambiá la agrupación sólo en `OrderPricingEngine`.
2. Mantené el acceso a precios de Products detrás de `ProductPriceProvider`.
3. Actualizá la matriz de `tests/Feature/Orders/OrderPricingEngineTest.php`.
4. Actualizá las pruebas de snapshots/restricciones si cambia la persistencia.
5. Actualizá este documento y `PROJECT_MAP.md` si se mueve una responsabilidad.

## Pending derived pricing foundation (P0-A)

The active rules above have not been replaced. See [DERIVED_PRICING_FOUNDATION.md](DERIVED_PRICING_FOUNDATION.md) for the prepared, inactive derived allocator, per-product homogeneous surcharge, configurable mixed-six surcharge, peso HALF-UP arithmetic, raw compatibility constraints and reserved exact schema. P0-B must complete and verify the shared-engine/persistence cutover before these rules can price an order.
