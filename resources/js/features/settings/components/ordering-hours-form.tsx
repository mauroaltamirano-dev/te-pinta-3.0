import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export interface OrderingHour {
    /** ISO weekday, 1 = Monday. */
    weekday: number;
    /** HH:MM, null when the day is closed. */
    opens_at: string | null;
    closes_at: string | null;
}

const DAY_NAMES = [
    'Lunes',
    'Martes',
    'Miércoles',
    'Jueves',
    'Viernes',
    'Sábado',
    'Domingo',
];

export function OrderingHoursForm({ hours }: { hours: OrderingHour[] }) {
    const form = useForm({ hours });
    const errors = form.errors as Record<string, string | undefined>;
    const update = (index: number, changes: Partial<OrderingHour>) =>
        form.setData(
            'hours',
            form.data.hours.map((hour, current) =>
                current === index ? { ...hour, ...changes } : hour,
            ),
        );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/admin/settings/general/ordering-hours', {
            preserveScroll: true,
        });
    };

    return (
        <form
            onSubmit={submit}
            aria-labelledby="ordering-hours-title"
            className="admin-ui grid gap-5 border-b border-admin-border p-5 sm:p-7"
        >
            <div>
                <h2 id="ordering-hours-title" className="text-lg font-semibold">
                    Horario de pedidos web
                </h2>
                <p className="mt-1 text-sm text-admin-text-secondary">
                    La web acepta pedidos solo dentro de este horario. Si el
                    cierre es igual o anterior a la apertura, termina al día
                    siguiente (19:00 a 00:00 cierra a la medianoche).
                </p>
            </div>
            <div className="grid gap-3">
                {form.data.hours.map((hour, index) => {
                    const name = DAY_NAMES[hour.weekday - 1];
                    const open = hour.opens_at !== null;
                    const error =
                        errors[`hours.${index}.opens_at`] ??
                        errors[`hours.${index}.closes_at`] ??
                        errors[`hours.${index}.weekday`];

                    return (
                        <fieldset
                            key={hour.weekday}
                            className="grid grid-cols-[7rem_1fr] items-center gap-x-4 gap-y-2 sm:grid-cols-[7rem_8rem_1fr_1fr]"
                        >
                            <legend className="sr-only">{name}</legend>
                            <span className="font-medium">{name}</span>
                            <Label className="flex min-h-11 items-center gap-2 font-normal">
                                <Checkbox
                                    checked={open}
                                    onCheckedChange={(checked) =>
                                        update(
                                            index,
                                            checked === true
                                                ? {
                                                      opens_at: '19:00',
                                                      closes_at: '00:00',
                                                  }
                                                : {
                                                      opens_at: null,
                                                      closes_at: null,
                                                  },
                                        )
                                    }
                                />
                                Abierto
                            </Label>
                            {open ? (
                                <>
                                    <Input
                                        type="time"
                                        aria-label={`${name}: apertura`}
                                        value={hour.opens_at ?? ''}
                                        onChange={(event) =>
                                            update(index, {
                                                opens_at: event.target.value,
                                            })
                                        }
                                        required
                                        className="col-start-2 sm:col-start-auto"
                                    />
                                    <Input
                                        type="time"
                                        aria-label={`${name}: cierre`}
                                        value={hour.closes_at ?? ''}
                                        onChange={(event) =>
                                            update(index, {
                                                closes_at: event.target.value,
                                            })
                                        }
                                        required
                                        className="col-start-2 sm:col-start-auto"
                                    />
                                </>
                            ) : (
                                <span className="col-start-2 text-sm text-admin-text-secondary sm:col-span-2 sm:col-start-auto">
                                    Cerrado
                                </span>
                            )}
                            {error && (
                                <p
                                    role="alert"
                                    className="col-span-full text-sm text-danger"
                                >
                                    {error}
                                </p>
                            )}
                        </fieldset>
                    );
                })}
            </div>
            <div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && (
                        <LoaderCircle
                            className="size-4 animate-spin"
                            aria-hidden="true"
                        />
                    )}
                    Guardar horario
                </Button>
            </div>
        </form>
    );
}
