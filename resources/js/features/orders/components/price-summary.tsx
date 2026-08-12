import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PriceQuote } from '../types';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});
const formatLabels: Record<string, string> = {
    unit: 'Unit',
    half_dozen: 'Half dozen',
    dozen: 'Dozen',
    combined_dozen: 'Combined dozen',
};
export function PriceSummary({
    quote,
    loading = false,
}: {
    quote: PriceQuote | null;
    loading?: boolean;
}) {
    if (loading) {
        return (
            <Card aria-busy="true">
                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                    Calculating backend price…
                </CardContent>
            </Card>
        );
    }

    if (!quote) {
        return (
            <Card>
                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                    Add products to see the backend price breakdown.
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Price summary</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <ul className="grid gap-3">
                    {(quote.lines ?? quote.items ?? []).map((line, index) => (
                        <li
                            key={`${line.format ?? line.pricingMode}-${index}`}
                            className="flex items-start justify-between gap-4 text-sm"
                        >
                            <div>
                                <p className="font-medium">
                                    {line.quantity} ×{' '}
                                    {formatLabels[
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
                                {money.format(
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
                            {money.format(
                                addon.subtotal ?? addon.lineTotal ?? 0,
                            )}
                        </span>
                    </div>
                ))}
                <dl className="grid gap-2 border-t pt-4 text-sm">
                    <div className="flex justify-between">
                        <dt>Empanadas</dt>
                        <dd>
                            {money.format(
                                quote.products_subtotal ??
                                    quote.empanadas_subtotal ??
                                    0,
                            )}
                        </dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Toppings</dt>
                        <dd>
                            {money.format(
                                quote.addons_subtotal ??
                                    quote.toppings_subtotal ??
                                    0,
                            )}
                        </dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Shipping</dt>
                        <dd>{money.format(quote.shipping_fee ?? 0)}</dd>
                    </div>
                    {(quote.savings ?? quote.combined_dozen_savings ?? 0) >
                        0 && (
                        <div className="flex justify-between text-emerald-600">
                            <dt>Combined-dozen savings</dt>
                            <dd>
                                {money.format(
                                    quote.savings ??
                                        quote.combined_dozen_savings ??
                                        0,
                                )}
                            </dd>
                        </div>
                    )}
                    <div className="flex justify-between border-t pt-3 text-lg font-bold">
                        <dt>Total</dt>
                        <dd>{money.format(quote.total)}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}
