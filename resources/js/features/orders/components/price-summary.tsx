import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatOrderMoney, pricingModeLabels } from '../presentation';
import type { PriceQuote } from '../types';
export function PriceSummary({
    quote,
    loading = false,
    compact = false,
}: {
    quote: PriceQuote | null;
    loading?: boolean;
    compact?: boolean;
}) {
    if (loading) {
        return (
            <Card
                aria-busy="true"
                className={
                    compact ? 'border-0 bg-transparent shadow-none' : undefined
                }
            >
                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                    Calculando el precio…
                </CardContent>
            </Card>
        );
    }

    if (!quote) {
        return (
            <Card
                className={
                    compact ? 'border-0 bg-transparent shadow-none' : undefined
                }
            >
                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                    Agregá productos para ver el desglose del precio.
                </CardContent>
            </Card>
        );
    }

    return (
        <Card
            className={
                compact ? 'border-0 bg-transparent shadow-none' : undefined
            }
        >
            {!compact && (
                <CardHeader>
                    <CardTitle>Resumen del pedido</CardTitle>
                </CardHeader>
            )}
            <CardContent className={compact ? 'grid gap-4 p-0' : 'grid gap-4'}>
                <ul className="grid gap-3">
                    {(quote.lines ?? quote.items ?? []).map((line, index) => (
                        <li
                            key={`${line.format ?? line.pricingMode}-${index}`}
                            className="flex items-start justify-between gap-4 text-sm"
                        >
                            <div>
                                <p className="font-medium">
                                    {line.quantity} ×{' '}
                                    {pricingModeLabels[
                                        line.format ?? line.pricingMode ?? ''
                                    ] ??
                                        line.format ??
                                        line.pricingMode}
                                    {(line.name ?? line.productName)
                                        ? ` ${line.name ?? line.productName}`
                                        : ''}
                                </p>
                                {line.composition && (
                                    <ul className="ml-3 text-xs text-muted-foreground">
                                        {line.composition.map((part) => (
                                            <li
                                                key={`${part.name ?? part.product_name}-${part.quantity}`}
                                            >
                                                {part.name ?? part.product_name}{' '}
                                                ×{part.quantity}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            <span className="tabular-nums">
                                {formatOrderMoney.format(
                                    line.subtotal ?? line.lineTotal ?? 0,
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
                {(quote.addons ?? quote.toppings)?.map((addon) => (
                    <div
                        key={addon.name}
                        className="flex justify-between text-sm"
                    >
                        <span>
                            {addon.name} ×{addon.quantity}
                        </span>
                        <span>
                            {formatOrderMoney.format(
                                addon.subtotal ?? addon.lineTotal ?? 0,
                            )}
                        </span>
                    </div>
                ))}
                <dl className="grid gap-2 border-t pt-4 text-sm">
                    <div className="flex justify-between">
                        <dt>
                            {quote.products_cooked_subtotal !== undefined
                                ? 'Productos cocinados'
                                : 'Productos'}
                        </dt>
                        <dd>
                            {formatOrderMoney.format(
                                quote.products_cooked_subtotal ??
                                    quote.products_subtotal ??
                                    quote.empanadas_subtotal ??
                                    0,
                            )}
                        </dd>
                    </div>
                    {(quote.raw_discount ?? 0) > 0 && (
                        <div className="flex justify-between text-success">
                            <dt>Descuento por preparación en crudo</dt>
                            <dd>
                                −
                                {formatOrderMoney.format(
                                    quote.raw_discount ?? 0,
                                )}
                            </dd>
                        </div>
                    )}
                    <div className="flex justify-between">
                        <dt>Adicionales</dt>
                        <dd>
                            {formatOrderMoney.format(
                                quote.addons_subtotal ??
                                    quote.toppings_subtotal ??
                                    0,
                            )}
                        </dd>
                    </div>
                    {quote.discount_type !== null &&
                        quote.discount_type !== undefined &&
                        quote.discountable_subtotal !== undefined && (
                            <div className="flex justify-between border-t pt-2 font-medium">
                                <dt>Subtotal descontable</dt>
                                <dd>
                                    {formatOrderMoney.format(
                                        quote.discountable_subtotal,
                                    )}
                                </dd>
                            </div>
                        )}
                    {(quote.discount_amount ?? 0) > 0 && (
                        <div className="flex justify-between text-success">
                            <dt>Descuento</dt>
                            <dd>
                                −
                                {formatOrderMoney.format(
                                    quote.discount_amount ?? 0,
                                )}
                            </dd>
                        </div>
                    )}
                    <div className="flex justify-between">
                        <dt>Envío</dt>
                        <dd>
                            {formatOrderMoney.format(quote.shipping_fee ?? 0)}
                        </dd>
                    </div>
                    {(quote.savings ?? quote.combined_dozen_savings ?? 0) >
                        0 && (
                        <div className="flex justify-between text-success">
                            <dt>Ahorro por docena combinada</dt>
                            <dd>
                                {formatOrderMoney.format(
                                    quote.savings ??
                                        quote.combined_dozen_savings ??
                                        0,
                                )}
                            </dd>
                        </div>
                    )}
                    {(quote.rounding_adjustment ?? 0) !== 0 && (
                        <div className="flex justify-between">
                            <dt>Redondeo</dt>
                            <dd data-testid="order-rounding">
                                {(quote.rounding_adjustment ?? 0) > 0
                                    ? '+'
                                    : '−'}
                                {formatOrderMoney.format(
                                    Math.abs(quote.rounding_adjustment ?? 0),
                                )}
                            </dd>
                        </div>
                    )}
                    <div className="flex justify-between border-t pt-3 text-lg font-bold">
                        <dt>Total</dt>
                        <dd data-testid="order-total" aria-live="polite">
                            {formatOrderMoney.format(quote.total)}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}
