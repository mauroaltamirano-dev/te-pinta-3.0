import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function BrandingForm({ accentColor }: { accentColor: string }) {
    const form = useForm({ accent_color: accentColor });
    const previewColor = /^#[0-9a-f]{6}$/i.test(form.data.accent_color)
        ? form.data.accent_color
        : accentColor;
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/admin/settings/general/branding', { preserveScroll: true });
    };

    return (
        <form
            onSubmit={submit}
            aria-labelledby="branding-title"
            className="admin-ui grid gap-5 border-b border-admin-border p-5 sm:p-7"
        >
            <div>
                <h2 id="branding-title" className="text-lg font-semibold">
                    Identidad del restaurante
                </h2>
                <p
                    id="accent-help"
                    className="mt-1 text-sm text-admin-text-secondary"
                >
                    Elegí un tono oscuro para destacar acciones y enlaces. El
                    texto blanco sobre el color debe alcanzar un contraste
                    mínimo de 4.5:1.
                </p>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor="accent-color">Color de acento</Label>
                    <div className="flex items-center gap-3">
                        <input
                            type="color"
                            aria-label="Seleccionar color de acento"
                            value={previewColor}
                            onChange={(event) =>
                                form.setData('accent_color', event.target.value)
                            }
                            className="size-11 shrink-0 cursor-pointer rounded-md border border-admin-control-border bg-admin-surface p-1"
                        />
                        <Input
                            id="accent-color"
                            value={form.data.accent_color}
                            onChange={(event) =>
                                form.setData('accent_color', event.target.value)
                            }
                            autoComplete="off"
                            spellCheck={false}
                            maxLength={7}
                            aria-invalid={Boolean(form.errors.accent_color)}
                            aria-describedby={`accent-help${form.errors.accent_color ? ' accent-error' : ''}`}
                            className="font-mono"
                        />
                    </div>
                    {form.errors.accent_color && (
                        <p
                            id="accent-error"
                            role="alert"
                            className="text-sm text-danger"
                        >
                            {form.errors.accent_color}
                        </p>
                    )}
                </div>
                <div
                    className="rounded-lg border border-admin-border bg-admin-surface p-4"
                    aria-label="Vista previa del color"
                >
                    <p className="mb-3 text-sm text-admin-text-secondary">
                        Vista previa · todavía no guardada
                    </p>
                    <span
                        data-testid="branding-preview-action"
                        className="inline-flex min-h-11 items-center rounded-md px-4 py-2 text-sm font-semibold text-admin-on-accent"
                        style={{ backgroundColor: previewColor }}
                    >
                        Acción destacada
                    </span>
                </div>
            </div>
            <div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && (
                        <LoaderCircle
                            className="size-4 animate-spin"
                            aria-hidden="true"
                        />
                    )}
                    Guardar color de acento
                </Button>
            </div>
        </form>
    );
}
