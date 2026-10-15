import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPesos } from '../utils';

export function HomogeneousSurchargePreview({
    dozen,
    value,
    onChange,
    error,
}: {
    dozen: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const requestId = useRef(0);
    const [result, setResult] = useState<{
        key: string;
        half?: number;
        error?: string;
    } | null>(null);
    const key = `${dozen}:${value}`;
    useEffect(() => {
        const id = ++requestId.current;
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const token = document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content;
                const xsrf = document.cookie
                    .split('; ')
                    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                    ?.slice('XSRF-TOKEN='.length);
                const response = await fetch(
                    '/admin/settings/products/half-dozen-preview',
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                            ...(xsrf
                                ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) }
                                : {}),
                        },
                        body: JSON.stringify({
                            dozen_price: dozen,
                            homogeneous_half_dozen_surcharge: value,
                        }),
                    },
                );
                const data = await response.json();

                if (id !== requestId.current || controller.signal.aborted) {
                    return;
                }

                setResult(
                    response.ok
                        ? { key, half: data.half_dozen_price }
                        : {
                              key,
                              error:
                                  data.errors
                                      ?.homogeneous_half_dozen_surcharge?.[0] ??
                                  'Revisa la docena y el recargo.',
                          },
                );
            } catch {
                if (id === requestId.current && !controller.signal.aborted) {
                    setResult({
                        key,
                        error: 'No se pudo consultar el precio. Vuelve a intentar.',
                    });
                }
            }
        }, 200);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [dozen, value, key]);
    const current = result?.key === key ? result : null;

    return (
        <div className="grid min-w-0 gap-2 sm:col-span-2">
            <Label
                htmlFor="homogeneous-surcharge"
                className="text-admin-text-primary"
            >
                Recargo de media homogénea
            </Label>
            <Input
                id="homogeneous-surcharge"
                className="min-w-0 text-admin-text-primary"
                inputMode="decimal"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-describedby="homogeneous-price-result"
                aria-invalid={Boolean(error || current?.error)}
            />
            <p
                id="homogeneous-price-result"
                role="status"
                className="text-sm text-admin-text-secondary"
            >
                {error ??
                    current?.error ??
                    (current?.half !== undefined
                        ? `Media homogénea resultante: ${formatPesos(current.half)}`
                        : 'Consultando precio de media homogénea…')}
            </p>
            <p className="text-xs text-admin-text-secondary">
                El backend calcula docena / 2 + recargo. Guardar modifica
                explícitamente el precio de media docena.
            </p>
        </div>
    );
}
