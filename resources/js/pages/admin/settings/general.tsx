import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SettingsLayout from '@/layouts/settings-layout';

interface OrderingSettings {
    combined_dozen_raw_price: number;
    combined_dozen_cooked_price: number;
    delivery_fee: number;
    whatsapp_order_phone: string;
    cooking_surcharges: { unit: number; half_dozen: number; dozen: number };
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
}: {
    orderingSettings: OrderingSettings;
}) {
    const form = useForm(orderingSettings);
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/admin/settings/general', { preserveScroll: true });
    };

    return (
        <>
            <Head title="General configuration" />
            <SettingsLayout>
                <header className="border-b border-admin-border px-5 py-5 sm:px-7">
                    <p className="text-xs font-semibold tracking-[0.18em] text-admin-accent uppercase">
                        Settings
                    </p>
                    <h1 className="mt-1 text-xl font-bold">
                        Ordering configuration
                    </h1>
                    <p className="mt-1 text-sm text-admin-text-secondary">
                        Global prices and the WhatsApp destination used by
                        backend pricing and checkout.
                    </p>
                </header>
                <form onSubmit={submit} className="grid gap-6 p-5 sm:p-7">
                    <fieldset className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                        <legend className="px-1 font-semibold">
                            Combined dozen and delivery
                        </legend>
                        <MoneyField
                            id="combined-raw"
                            label="Raw combined dozen"
                            value={form.data.combined_dozen_raw_price}
                            error={form.errors.combined_dozen_raw_price}
                            onChange={(value) =>
                                form.setData('combined_dozen_raw_price', value)
                            }
                        />
                        <MoneyField
                            id="combined-cooked"
                            label="Cooked combined dozen"
                            value={form.data.combined_dozen_cooked_price}
                            error={form.errors.combined_dozen_cooked_price}
                            onChange={(value) =>
                                form.setData(
                                    'combined_dozen_cooked_price',
                                    value,
                                )
                            }
                        />
                        <MoneyField
                            id="delivery-fee"
                            label="Delivery fee"
                            value={form.data.delivery_fee}
                            error={form.errors.delivery_fee}
                            onChange={(value) =>
                                form.setData('delivery_fee', value)
                            }
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="whatsapp-phone">
                                WhatsApp order phone
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
                                Use the international number without a hardcoded
                                frontend fallback.
                            </p>
                            <ErrorText
                                id="whatsapp-phone-error"
                                message={form.errors.whatsapp_order_phone}
                            />
                        </div>
                    </fieldset>
                    <fieldset className="grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
                        <legend className="px-1 font-semibold">
                            Cooking surcharges
                        </legend>
                        {(['unit', 'half_dozen', 'dozen'] as const).map(
                            (tier) => (
                                <MoneyField
                                    key={tier}
                                    id={`surcharge-${tier}`}
                                    label={
                                        tier === 'unit'
                                            ? 'Unit'
                                            : tier === 'half_dozen'
                                              ? 'Half dozen'
                                              : 'Dozen'
                                    }
                                    value={form.data.cooking_surcharges[tier]}
                                    error={
                                        form.errors[
                                            `cooking_surcharges.${tier}`
                                        ]
                                    }
                                    onChange={(value) =>
                                        form.setData('cooking_surcharges', {
                                            ...form.data.cooking_surcharges,
                                            [tier]: value,
                                        })
                                    }
                                />
                            ),
                        )}
                    </fieldset>
                    <fieldset className="grid gap-4 rounded-lg border p-4">
                        <legend className="px-1 font-semibold">Toppings</legend>
                        {form.data.addons.map((addon, index) => (
                            <div
                                key={addon.id}
                                className="grid items-end gap-3 sm:grid-cols-[1fr_10rem_auto]"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor={`addon-name-${addon.id}`}>
                                        Name
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
                                    label="Price"
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
                                    Available
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
                                Review the highlighted configuration values.
                            </p>
                        )}
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Save configuration
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
    onChange,
}: {
    id: string;
    label: string;
    value: number;
    error?: string;
    onChange: (value: number) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                min="0"
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
