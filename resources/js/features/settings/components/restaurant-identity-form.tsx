import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { RestaurantIdentity } from '@/hooks/use-restaurant-identity';

type TextField = Exclude<keyof RestaurantIdentity, 'highlights'>;

const HIGHLIGHT_SLOTS = 3;

/** Text fields in form order: label, help and length limit (mirrors the backend). */
const fields: Array<{
    name: TextField;
    label: string;
    help?: string;
    max: number;
    multiline?: boolean;
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
        highlights: Array.from(
            { length: HIGHLIGHT_SLOTS },
            (_, index) => identity.highlights[index] ?? '',
        ),
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/admin/settings/general/identity', { preserveScroll: true });
    };
    const errors = form.errors as Partial<Record<string, string>>;
    const highlightsError =
        errors.highlights ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('highlights.'),
        )?.[1];

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
            <fieldset className="grid gap-3">
                <legend className="mb-1 text-sm font-medium">
                    Frases destacadas
                </legend>
                <p
                    id="identity-highlights-help"
                    className="text-xs text-muted-foreground"
                >
                    Hasta tres, debajo de la portada y en el pie (por ejemplo:
                    &quot;Retiro o envío a domicilio&quot;).
                </p>
                <div className="grid gap-3 sm:grid-cols-3">
                    {form.data.highlights.map((highlight, index) => (
                        <Input
                            key={index}
                            aria-label={`Frase destacada ${index + 1}`}
                            aria-describedby="identity-highlights-help"
                            value={highlight}
                            maxLength={60}
                            onChange={(event) =>
                                form.setData(
                                    'highlights',
                                    form.data.highlights.map(
                                        (item, itemIndex) =>
                                            itemIndex === index
                                                ? event.target.value
                                                : item,
                                    ),
                                )
                            }
                            aria-invalid={Boolean(
                                errors[`highlights.${index}`],
                            )}
                        />
                    ))}
                </div>
                {highlightsError && (
                    <p role="alert" className="text-sm text-danger">
                        {highlightsError}
                    </p>
                )}
            </fieldset>
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
