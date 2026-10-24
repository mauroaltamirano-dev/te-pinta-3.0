import { cn } from '@/lib/utils';
import { dozenSaving } from '../utils';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});

/** Dozen price with twelve units struck through and the saving, when there is one. */
export function DozenPrice({
    unitPrice,
    dozenPrice,
    className,
}: {
    unitPrice: number;
    dozenPrice: number;
    className?: string;
}) {
    const saving = dozenSaving(unitPrice, dozenPrice);

    return (
        <span
            data-testid="dozen-price"
            className={cn('flex flex-wrap items-baseline gap-x-2', className)}
        >
            <span
                data-testid="dozen-price-current"
                className="font-semibold tabular-nums"
            >
                {money.format(dozenPrice)}
            </span>
            {saving && (
                <>
                    <s
                        data-testid="dozen-price-regular"
                        className="text-[0.6em] font-normal text-muted-foreground tabular-nums"
                    >
                        <span className="sr-only">Antes </span>
                        {money.format(saving.regular)}
                    </s>
                    <span
                        data-testid="dozen-price-discount"
                        className="self-center rounded-full bg-public-canvas px-2 py-0.5 text-xs font-bold text-white tabular-nums"
                    >
                        -{saving.percent}%
                    </span>
                </>
            )}
        </span>
    );
}
