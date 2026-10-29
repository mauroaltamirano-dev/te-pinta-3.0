import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { RestaurantIdentity } from '@/hooks/use-restaurant-identity';

type ListField = 'highlights' | 'offerings' | 'dine_in_items';
type TextField = Exclude<keyof RestaurantIdentity, ListField>;

/** List fields in form order: fixed number of rows and item length limit (mirrors the backend). */
const lists: Array<{
    name: ListField;
    legend: string;
    itemLabel: string;
    help: string;
    slots: number;
    max: number;
}> = [
    {
        name: 'highlights',
        legend: 'Frases destacadas',
        itemLabel: 'Frase destacada',
        help: 'Hasta tres, debajo de la portada (por ejemplo: "Retiro o envío a domicilio").',
        slots: 3,
        max: 60,
    },
    {
        name: 'offerings',
        legend: 'Qué ofrecemos',
        itemLabel: 'Qué ofrecemos',
        help: 'Lista del pie de la web. Vacía, no se muestra.',
        slots: 6,
        max: 60,
    },
    {
        name: 'dine_in_items',
        legend: 'Etiquetas del local',
        itemLabel: 'Etiqueta del local',
        help: 'Se muestran en "Nuestro local" de la portada (por ejemplo: "Servicio a la mesa").',
        slots: 6,
        max: 40,
    },
];

/** Text fields in form order: label, help and length limit (mirrors the backend). */
const fields: Array<{
    name: TextField;
    label: string;
    help?: string;
    max: number;
    multiline?: boolean;
    type?: 'url';
}> = [
    { name: 'name', label: 'Nombre del restaurante', max: 60 },
    {
        name: 'eyebrow',
        label: 'Antetítulo de la portada',
        help: 'Texto corto en mayúsculas sobre el título.',
        max: 60,
    },
    { name: 'headline', label: 'Título de la portada', max: 80 },
    {
        name: 'headline_highlight',
        label: 'Parte destacada del título',
        help: 'Se pinta en otro color. Tiene que aparecer tal cual en el título.',
        max: 30,
    },
    {
        name: 'intro',
        label: 'Presentación',
        max: 200,
        multiline: true,
    },
    {
        name: 'home_title',
        label: 'Título de la pestaña del navegador',
        max: 60,
    },
    { name: 'footer_text', label: 'Texto del pie', max: 200, multiline: true },
    {
        name: 'location',
        label: 'Ubicación',
        help: 'Por ejemplo: Bell Ville, Córdoba.',
        max: 60,
    },
    {
        name: 'maps_url',
        label: 'Enlace de Google Maps',
        help: 'Botón "Cómo llegar" de la portada. Tiene que empezar con https://. Vacío, no se muestra.',
        max: 255,
        type: 'url',
    },
    {
        name: 'coming_soon_text',
        label: 'Texto de "Muy pronto"',
        help: 'Se muestra mientras la tienda online está cerrada.',
        max: 200,
        multiline: true,
    },
    {
        name: 'empty_cart_text',
        label: 'Texto del carrito vacío',
        max: 200,
        multiline: true,
    },
];

export function RestaurantIdentityForm({
    identity,
}: {
    identity: RestaurantIdentity;
}) {
    const form = useForm({
        ...identity,
        ...(Object.fromEntries(
            lists.map((list) => [
                list.name,
                Array.from(
                    { length: list.slots },
                    (_, index) => identity[list.name][index] ?? '',
                ),
            ]),
        ) as Record<ListField, string[]>),
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/admin/settings/general/identity', { preserveScroll: true });
    };
    const errors = form.errors as Partial<Record<string, string>>;

    return (
        <form
            onSubmit={submit}
            aria-labelledby="identity-title"
            className="admin-ui grid gap-5 border-b border-admin-border p-5 sm:p-7"
        >
            <div>
                <h2 id="identity-title" className="text-lg font-semibold">
                    Nombre y textos de la web
                </h2>
                <p className="mt-1 text-sm text-admin-text-secondary">
                    El nombre aparece en la web, el panel y los títulos. Los
                    textos vacíos no se muestran.
                </p>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                {fields.map((field) => {
                    const id = `identity-${field.name.replaceAll('_', '-')}`;
                    const error = errors[field.name];
                    const describedBy =
                        [field.help && `${id}-help`, error && `${id}-error`]
                            .filter(Boolean)
                            .join(' ') || undefined;
                    const Control = field.multiline ? Textarea : Input;

                    return (
                        <div
                            key={field.name}
                            className="grid content-start gap-2"
                        >
                            <Label htmlFor={id}>{field.label}</Label>
                            <Control
                                id={id}
                                value={form.data[field.name] ?? ''}
                                onChange={(event) =>
                                    form.setData(field.name, event.target.value)
                                }
                                maxLength={field.max}
                                type={field.type}
                                required={field.name === 'name'}
                                aria-invalid={Boolean(error)}
                                aria-describedby={describedBy}
                            />
                            {field.help && (
                                <p
                                    id={`${id}-help`}
                                    className="text-xs text-muted-foreground"
                                >
                                    {field.help}
                                </p>
                            )}
                            {error && (
                                <p
                                    id={`${id}-error`}
                                    role="alert"
                                    className="text-sm text-danger"
                                >
                                    {error}
                                </p>
                            )}
                        </div>
                    );
                })}
            </div>
            {lists.map((list) => {
                const helpId = `identity-${list.name.replaceAll('_', '-')}-help`;
                const listError =
                    errors[list.name] ??
                    Object.entries(errors).find(([key]) =>
                        key.startsWith(`${list.name}.`),
                    )?.[1];

                return (
                    <fieldset key={list.name} className="grid gap-3">
                        <legend className="mb-1 text-sm font-medium">
                            {list.legend}
                        </legend>
                        <p
                            id={helpId}
                            className="text-xs text-muted-foreground"
                        >
                            {list.help}
                        </p>
                        <div className="grid gap-3 sm:grid-cols-3">
                            {form.data[list.name].map((item, index) => (
                                <Input
                                    key={index}
                                    aria-label={`${list.itemLabel} ${index + 1}`}
                                    aria-describedby={helpId}
                                    value={item}
                                    maxLength={list.max}
                                    onChange={(event) =>
                                        form.setData(
                                            list.name,
                                            form.data[list.name].map(
                                                (current, itemIndex) =>
                                                    itemIndex === index
                                                        ? event.target.value
                                                        : current,
                                            ),
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        errors[`${list.name}.${index}`],
                                    )}
                                />
                            ))}
                        </div>
                        {listError && (
                            <p role="alert" className="text-sm text-danger">
                                {listError}
                            </p>
                        )}
                    </fieldset>
                );
            })}
            <div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && (
                        <LoaderCircle
                            className="size-4 animate-spin"
                            aria-hidden="true"
                        />
                    )}
                    Guardar nombre y textos
                </Button>
            </div>
        </form>
    );
}
