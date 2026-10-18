import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dayOptions } from '../presentation';
import { instagramRoutes } from '../routes';
import type { InstagramStoryAsset, InstagramStorySchedule } from '../types';
import { InstagramSwitch } from './instagram-switch';

interface ScheduleFormData {
    name: string;
    time: string;
    days_of_week: number[];
    asset_id: string;
    is_active: boolean;
    sort_order: number;
}

export function ScheduleDialog({
    open,
    schedule,
    assets,
    onOpenChange,
}: {
    open: boolean;
    schedule: InstagramStorySchedule | null;
    assets: InstagramStoryAsset[];
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<ScheduleFormData>({
        name: schedule?.name ?? '',
        time: schedule?.time ?? '11:00',
        days_of_week:
            schedule?.days_of_week ?? dayOptions.map((day) => day.value),
        asset_id: schedule?.asset_id?.toString() ?? '',
        is_active: schedule?.is_active ?? false,
        sort_order: schedule?.sort_order ?? 1,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (schedule) {
            form.patch(instagramRoutes.schedule(schedule.id), options);

            return;
        }

        form.post(instagramRoutes.schedules, options);
    };
    const toggleDay = (day: number, checked: boolean) =>
        form.setData(
            'days_of_week',
            checked
                ? [...form.data.days_of_week, day]
                : form.data.days_of_week.filter((value) => value !== day),
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell max-h-[calc(100vh-2rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {schedule
                            ? 'Editar programación'
                            : 'Nueva programación'}
                    </DialogTitle>
                    <DialogDescription>
                        Elegí cuándo se crea la publicación pendiente. Instagram
                        nunca se llama directamente desde esta pantalla.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Nombre"
                            htmlFor="schedule-name"
                            error={form.errors.name}
                        >
                            <Input
                                id="schedule-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                autoFocus
                                required
                                aria-invalid={Boolean(form.errors.name)}
                            />
                        </Field>
                        <Field
                            label="Horario"
                            htmlFor="schedule-time"
                            error={form.errors.time}
                        >
                            <Input
                                id="schedule-time"
                                type="time"
                                value={form.data.time}
                                onChange={(event) =>
                                    form.setData('time', event.target.value)
                                }
                                required
                                aria-invalid={Boolean(form.errors.time)}
                                className="[color-scheme:dark]"
                            />
                        </Field>
                    </div>

                    <Field
                        label="Historia"
                        htmlFor="schedule-asset"
                        error={form.errors.asset_id}
                    >
                        <Select
                            value={form.data.asset_id || 'none'}
                            onValueChange={(value) =>
                                form.setData(
                                    'asset_id',
                                    value === 'none' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger
                                id="schedule-asset"
                                className="w-full"
                            >
                                <SelectValue placeholder="Seleccionar una imagen" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sin imagen</SelectItem>
                                {assets.map((asset) => (
                                    <SelectItem
                                        key={asset.id}
                                        value={asset.id.toString()}
                                        disabled={!asset.is_active}
                                    >
                                        {asset.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field
                        label="Orden cuando comparte horario"
                        htmlFor="schedule-order"
                        error={form.errors.sort_order}
                    >
                        <Input
                            id="schedule-order"
                            type="number"
                            min={1}
                            max={999}
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData(
                                    'sort_order',
                                    Number(event.target.value),
                                )
                            }
                            aria-invalid={Boolean(form.errors.sort_order)}
                            className="max-w-32 tabular-nums"
                        />
                        <p className="text-xs text-admin-text-tertiary">
                            El número más bajo se agrega primero a la cola.
                        </p>
                    </Field>

                    <fieldset className="grid gap-3">
                        <legend className="text-sm font-medium">
                            Días activos
                        </legend>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {dayOptions.map((day) => {
                                const checked = form.data.days_of_week.includes(
                                    day.value,
                                );

                                return (
                                    <label
                                        key={day.value}
                                        className="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-admin-border bg-admin-surface-raised px-3 text-sm text-admin-text-secondary"
                                    >
                                        <Checkbox
                                            checked={checked}
                                            onCheckedChange={(value) =>
                                                toggleDay(
                                                    day.value,
                                                    value === true,
                                                )
                                            }
                                        />
                                        {day.label}
                                    </label>
                                );
                            })}
                        </div>
                        {form.errors.days_of_week && (
                            <p className="text-sm text-destructive">
                                {form.errors.days_of_week}
                            </p>
                        )}
                    </fieldset>

                    <div className="flex min-h-16 items-center justify-between gap-4 rounded-xl border border-admin-border bg-admin-surface-raised px-4">
                        <div>
                            <p className="text-sm font-semibold text-admin-text-primary">
                                Programación activa
                            </p>
                            <p className="mt-0.5 text-xs text-admin-text-tertiary">
                                Requiere una imagen activa para poder
                                publicarse.
                            </p>
                        </div>
                        <InstagramSwitch
                            checked={form.data.is_active}
                            disabled={!form.data.asset_id}
                            label="Activar programación"
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Guardar programación
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
