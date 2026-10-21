import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { BrandingForm } from '@/features/settings/components/branding-form';
import SettingsLayout from '@/layouts/settings-layout';

interface OrderingSettings {
    /** Empty while the operator edits, so the backend rejects it instead of saving 0%. */
    empanada_unit_surcharge_percent: number | '';
    /** Empty while the operator edits, so the backend rejects it instead of saving 0%. */
    combined_dozen_discount_percent: number | '';
    delivery_fee: number;
    whatsapp_order_phone: string;
    /** Null when the dozen discount row is missing; the backend requires it on save. */
    raw_price_discounts: { dozen: number | null };
    addons: Array<{
        id: number;
        name: string;
        price: number;
        is_available: boolean;
        sort_order: number;
    }>;
}
export default function GeneralSettings({
    orderingSettings,
    branding,
}: {
    orderingSettings: OrderingSettings;
    branding: { accent_color: string };
}) {
    const form = useForm(orderingSettings);
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/admin/settings/general', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Configuración general" />
            <SettingsLayout>
                <header className="border-b border-admin-border px-5 py-5 sm:px-7">
                    <p className="text-xs font-semibold tracking-[0.18em] text-admin-accent-text uppercase">
                        Configuración
                    </p>
                    <h1 className="mt-1 text-xl font-bold">
                        Configuración de pedidos
                    </h1>
                    <p className="mt-1 text-sm text-admin-text-secondary">
                        Precios globales y destino de WhatsApp usados por el
                        cálculo de precios y el proceso de compra.
                    </p>
                </header>
                <BrandingForm accentColor={branding.accent_color} />
                <form onSubmit={submit} className="grid gap-6 p-5 sm:p-7">
                    <fieldset className="grid gap-4 rounded-lg border border-admin-border bg-admin-surface p-4">
                        <legend className="px-1 font-semibold">
                            Precio por unidad de empanadas
                        </legend>
                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="empanada-unit-surcharge">
                                Recargo por unidad (%)
                            </Label>
                            <Input
                                id="empanada-unit-surcharge"
                                type="number"
                                min={0}
                                max={100}
                                step="1"
                                value={
                                    form.data.empanada_unit_surcharge_percent
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'empanada_unit_surcharge_percent',
                                        event.target.value === ''
                                            ? ''
                                            : Number(event.target.value),
                                    )
                                }
                                aria-invalid={Boolean(
                                    form.errors.empanada_unit_surcharge_percent,
                                )}
                                aria-describedby={
                                    form.errors.empanada_unit_surcharge_percent
                                        ? 'empanada-unit-surcharge-error'
                                        : 'empanada-unit-surcharge-help'
                                }
                                required
                            />
                            <p
                                id="empanada-unit-surcharge-help"
                                className="text-xs text-muted-foreground"
                            >
                                Unidad = docena ÷ 12 + este porcentaje,
                                redondeada a $100. Al guardar un porcentaje
                                nuevo se recalcula la unidad de todas las
                                empanadas; los pedidos ya hechos no cambian.
                            </p>
                            <ErrorText
                                id="empanada-unit-surcharge-error"
                                message={
                                    form.errors.empanada_unit_surcharge_percent
                                }
                            />
                        </div>
                    </fieldset>
                    <fieldset className="grid gap-4 rounded-lg border border-admin-border bg-admin-surface p-4">
                        <legend className="px-1 font-semibold">
                            Docena combinada
                        </legend>
                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="combined-dozen-discount">
                                Descuento de docena combinada (%)
                            </Label>
                            <Input
                                id="combined-dozen-discount"
                                type="number"
                                min={0}
                                max={50}
                                step="1"
                                value={
                                    form.data.combined_dozen_discount_percent
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'combined_dozen_discount_percent',
                                        event.target.value === ''
                                            ? ''
                                            : Number(event.target.value),
                                    )
                                }
                                aria-invalid={Boolean(
                                    form.errors.combined_dozen_discount_percent,
                                )}
                                aria-describedby={
                                    form.errors.combined_dozen_discount_percent
                                        ? 'combined-dozen-discount-error'
                                        : 'combined-dozen-discount-help'
                                }
                                required
                            />
                            <p
                                id="combined-dozen-discount-help"
                                className="text-xs text-muted-foreground"
                            >
                                Doce empanadas de gustos distintos cuestan la
                                suma de sus unidades menos este porcentaje (de 0
                                a 50; con 0 no se arma docena combinada). Los
                                pedidos ya hechos no cambian.
                            </p>
                            <ErrorText
                                id="combined-dozen-discount-error"
                                message={
                                    form.errors.combined_dozen_discount_percent
                                }
                            />
                        </div>
                    </fieldset>
                    <fieldset className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                        <legend className="px-1 font-semibold">Entrega</legend>
                        <MoneyField
                            id="delivery-fee"
                            label="Costo de envío"
                            value={form.data.delivery_fee}
                            error={form.errors.delivery_fee}
                            onChange={(value) =>
                                form.setData('delivery_fee', value)
                            }
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="whatsapp-phone">
                                Teléfono de pedidos por WhatsApp
                            </Label>
                            <Input
                                id="whatsapp-phone"
                                inputMode="tel"
                                value={form.data.whatsapp_order_phone}
                                onChange={(event) =>
                                    form.setData(
                                        'whatsapp_order_phone',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    form.errors.whatsapp_order_phone,
                                )}
                                aria-describedby={
                                    form.errors.whatsapp_order_phone
                                        ? 'whatsapp-phone-error'
                                        : 'whatsapp-phone-help'
                                }
                            />
                            <p
                                id="whatsapp-phone-help"
                                className="text-xs text-muted-foreground"
                            >
                                Usá el número internacional completo. El sistema
                                no agrega un número alternativo.
                            </p>
                            <ErrorText
                                id="whatsapp-phone-error"
                                message={form.errors.whatsapp_order_phone}
                            />
                        </div>
                    </fieldset>
                    <fieldset className="grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
                        <legend className="px-1 font-semibold">
                            Descuentos por preparación en crudo
                        </legend>
                        <p className="text-sm text-admin-text-secondary sm:col-span-3">
                            Se descuenta de la docena de cada gusto. La unidad
                            cruda se calcula desde esa docena con el mismo
                            recargo por unidad.
                        </p>
                        <MoneyField
                            id="raw-discount-dozen"
                            min={1}
                            label="Docena"
                            value={form.data.raw_price_discounts.dozen ?? ''}
                            error={form.errors['raw_price_discounts.dozen']}
                            onChange={(value) =>
                                form.setData('raw_price_discounts', {
                                    dozen: value,
                                })
                            }
                        />
                    </fieldset>
                    <fieldset className="grid gap-4 rounded-lg border p-4">
                        <legend className="px-1 font-semibold">
                            Adicionales
                        </legend>
                        {form.data.addons.map((addon, index) => (
                            <div
                                key={addon.id}
                                className="grid items-end gap-3 sm:grid-cols-[1fr_10rem_auto]"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor={`addon-name-${addon.id}`}>
                                        Nombre
                                    </Label>
                                    <Input
                                        id={`addon-name-${addon.id}`}
                                        value={addon.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'addons',
                                                form.data.addons.map(
                                                    (item, itemIndex) =>
                                                        itemIndex === index
                                                            ? {
                                                                  ...item,
                                                                  name: event
                                                                      .target
                                                                      .value,
                                                              }
                                                            : item,
                                                ),
                                            )
                                        }
                                    />
                                </div>
                                <MoneyField
                                    id={`addon-price-${addon.id}`}
                                    label="Precio"
                                    value={addon.price}
                                    onChange={(value) =>
                                        form.setData(
                                            'addons',
                                            form.data.addons.map(
                                                (item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              price: value,
                                                          }
                                                        : item,
                                            ),
                                        )
                                    }
                                />
                                <label className="flex h-9 items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={addon.is_available}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'addons',
                                                form.data.addons.map(
                                                    (item, itemIndex) =>
                                                        itemIndex === index
                                                            ? {
                                                                  ...item,
                                                                  is_available:
                                                                      checked ===
                                                                      true,
                                                              }
                                                            : item,
                                                ),
                                            )
                                        }
                                    />
                                    Disponible
                                </label>
                            </div>
                        ))}
                    </fieldset>
                    <div>
                        {Object.keys(form.errors).length > 0 && (
                            <p
                                role="alert"
                                className="mb-3 text-sm text-destructive"
                            >
                                Revisá los valores de configuración marcados.
                            </p>
                        )}
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Guardar configuración
                        </Button>
                    </div>
                </form>
            </SettingsLayout>
        </>
    );
}
function MoneyField({
    id,
    label,
    value,
    error,
    min = 0,
    onChange,
}: {
    id: string;
    label: string;
    value: number | '';
    error?: string;
    min?: number;
    onChange: (value: number) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                min={min}
                step="1"
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                required
            />
            <ErrorText id={`${id}-error`} message={error} />
        </div>
    );
}
function ErrorText({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} role="alert" className="text-xs text-destructive">
            {message}
        </p>
    ) : null;
}
