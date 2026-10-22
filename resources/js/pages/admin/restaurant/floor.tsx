import { Head, router, usePage } from '@inertiajs/react';
import {
    cloneElement,
    isValidElement,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';
import type { ReactElement, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { saveFloor } from '@/features/restaurant/floor-api';
import { FloorPlan } from '@/features/restaurant/floor-plan';
import { constrainGeometry } from '@/features/restaurant/geometry';
import { hydrateArea, tableLabel } from '@/features/restaurant/types';
import type {
    FloorArea,
    FloorSelection,
    FloorShape,
    GeometryPatch,
} from '@/features/restaurant/types';
import {
    notifySubmissionFailure,
    OFFLINE_SUBMISSION_MESSAGE,
    serverFailureMessage,
    UNEXPECTED_RESPONSE_MESSAGE,
} from '@/lib/submission-failure';

const selectClass =
    'min-h-11 w-full min-w-0 rounded-md border border-admin-border bg-admin-surface px-3 text-sm text-admin-text-primary focus-visible:outline-2 focus-visible:outline-admin-accent';
function Field({ label, children }: { label: string; children: ReactNode }) {
    const id = useId();

    return (
        <div className="grid min-w-0 gap-1 text-sm text-admin-text-secondary">
            <label htmlFor={id}>{label}</label>
            {isValidElement(children)
                ? cloneElement(children as ReactElement<{ id?: string }>, {
                      id,
                  })
                : children}
        </div>
    );
}

export default function FloorEditor({
    areas: initialAreas,
}: {
    areas: FloorArea[];
}) {
    const [areas, setAreas] = useState(() => initialAreas.map(hydrateArea));
    const [draft, setDraft] = useState<FloorArea | null>(() =>
        initialAreas[0] ? hydrateArea(initialAreas[0]) : null,
    );
    const [selection, setSelection] = useState<FloorSelection | null>(null);
    const [newName, setNewName] = useState('');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [conflict, setConflict] = useState(false);
    const [preview, setPreview] = useState(false);
    // Roles without floor.manage (e.g. waiters) only read the floor; the
    // backend rejects their saves anyway.
    const canManage = (usePage().props.auth?.permissions ?? []).includes(
        'floor.manage',
    );
    const readOnly = preview || !canManage;
    const intentionalReload = useRef(false);
    const original = areas.find((area) => area.id === draft?.id);
    const dirty = !!draft && JSON.stringify(draft) !== JSON.stringify(original);
    const table =
        selection?.kind === 'table'
            ? draft?.tables.find((item) => item.key === selection.key)
            : undefined;
    const obstacle =
        selection?.kind === 'obstacle'
            ? draft?.obstacles.find((item) => item.key === selection.key)
            : undefined;
    const selected = table || obstacle;

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const unload = (event: BeforeUnloadEvent) => {
            if (intentionalReload.current) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };
        const stop = router.on('before', (event) => {
            if (
                !window.confirm(
                    'Hay cambios sin guardar. ¿Querés salir y descartarlos?',
                )
            ) {
                event.preventDefault();
            }
        });
        window.addEventListener('beforeunload', unload);

        return () => {
            window.removeEventListener('beforeunload', unload);
            stop();
        };
    }, [dirty]);

    const accept = (area: FloorArea) => {
        const hydrated = hydrateArea(area);
        setAreas((current) =>
            current.some((item) => item.id === area.id)
                ? current.map((item) => (item.id === area.id ? hydrated : item))
                : [...current, hydrated],
        );
        setDraft(hydrated);
        setSelection(null);
        setConflict(false);
        setErrors({});
    };
    const submit = async (create = false) => {
        if (saving || (!create && !draft)) {
            return;
        }

        if (
            create &&
            dirty &&
            !window.confirm(
                'Hay cambios sin guardar. ¿Querés descartarlos y crear otra área?',
            )
        ) {
            return;
        }

        setSaving(true);
        setMessage('');
        setErrors({});

        try {
            const payload = create
                ? { name: newName, width: 1600, height: 1000, is_active: true }
                : {
                      name: draft!.name,
                      width: draft!.width,
                      height: draft!.height,
                      is_active: draft!.is_active,
                      expected_version: draft!.layout_version,
                      tables: draft!.tables.map((item) =>
                          Object.fromEntries(
                              Object.entries(item).filter(
                                  ([key]) => key !== 'key',
                              ),
                          ),
                      ),
                      obstacles: draft!.obstacles.map((item) =>
                          Object.fromEntries(
                              Object.entries(item).filter(
                                  ([key]) => key !== 'key',
                              ),
                          ),
                      ),
                  };
            const { response, data } = await saveFloor(
                create
                    ? '/admin/restaurant/areas'
                    : `/admin/restaurant/areas/${draft!.id}/layout`,
                create ? 'POST' : 'PUT',
                payload,
            );

            if (response.status === 409) {
                setConflict(true);
                setMessage(
                    'Otro administrador guardó este plano. Tu borrador se conserva: copiá tus ajustes antes de recargar.',
                );
                notifySubmissionFailure(
                    'Otro administrador guardó este plano antes.',
                );
            } else if (!response.ok) {
                const reason = serverFailureMessage(response.status, data);
                setErrors(data.errors || {});
                setMessage(`${reason} El borrador se conserva.`);
                notifySubmissionFailure(reason);
            } else if (!data.area) {
                setMessage(
                    `${UNEXPECTED_RESPONSE_MESSAGE} El borrador se conserva.`,
                );
                notifySubmissionFailure(UNEXPECTED_RESPONSE_MESSAGE);
            } else {
                accept(data.area);
                setNewName('');
                setMessage('Cambios guardados.');
            }
        } catch {
            setMessage(
                'No se pudo conectar. El borrador se conserva; volvé a intentar.',
            );
            notifySubmissionFailure(OFFLINE_SUBMISSION_MESSAGE);
        } finally {
            setSaving(false);
        }
    };
    const geometryChange = (target: FloorSelection, patch: GeometryPatch) => {
        setDraft((current) =>
            current
                ? target.kind === 'table'
                    ? {
                          ...current,
                          tables: current.tables.map((item) =>
                              item.key === target.key
                                  ? {
                                        ...item,
                                        position_x: patch.x,
                                        position_y: patch.y,
                                        width: patch.width,
                                        height: patch.height,
                                    }
                                  : item,
                          ),
                      }
                    : {
                          ...current,
                          obstacles: current.obstacles.map((item) =>
                              item.key === target.key
                                  ? { ...item, ...patch }
                                  : item,
                          ),
                      }
                : current,
        );
    };
    const property = (key: string, value: string | number | boolean | null) => {
        if (!selection) {
            return;
        }

        setDraft((current) =>
            current
                ? selection.kind === 'table'
                    ? {
                          ...current,
                          tables: current.tables.map((item) =>
                              item.key === selection.key
                                  ? { ...item, [key]: value }
                                  : item,
                          ),
                      }
                    : {
                          ...current,
                          obstacles: current.obstacles.map((item) =>
                              item.key === selection.key
                                  ? { ...item, [key]: value }
                                  : item,
                          ),
                      }
                : current,
        );
    };
    const changeShape = (shape: FloorShape) => {
        if (!draft || !selection || !selected) {
            return;
        }

        property(table ? 'shape' : 'type', shape);
        geometryChange(
            selection,
            constrainGeometry(
                {
                    x: table ? table.position_x : obstacle!.x,
                    y: table ? table.position_y : obstacle!.y,
                    width: selected.width,
                    height: selected.height,
                },
                draft,
                shape,
                table?.rotation || 0,
            ),
        );
    };
    const add = (kind: 'table' | 'obstacle') => {
        if (!draft) {
            return;
        }

        const key = crypto.randomUUID();
        const geometry = constrainGeometry(
            {
                x: '0.2',
                y: '0.2',
                width: kind === 'table' ? '0.15' : '0.06',
                height: '0.15',
            },
            draft,
            kind === 'table' ? 'rectangle' : 'circle',
            0,
        );

        if (kind === 'table') {
            let number = 1;

            while (draft.tables.some((item) => item.code === String(number))) {
                number++;
            }

            setDraft({
                ...draft,
                tables: [
                    ...draft.tables,
                    {
                        key,
                        code: String(number),
                        name: null,
                        shape: 'rectangle',
                        position_x: geometry.x,
                        position_y: geometry.y,
                        width: geometry.width,
                        height: geometry.height,
                        rotation: 0,
                        capacity: null,
                        is_active: true,
                    },
                ],
            });
        } else {
            setDraft({
                ...draft,
                obstacles: [
                    ...draft.obstacles,
                    { key, type: 'circle', label: null, ...geometry },
                ],
            });
        }

        setSelection({ kind, key });
        setPreview(false);
    };
    const pickArea = (id: number) => {
        if (
            dirty &&
            !window.confirm(
                'Hay cambios sin guardar. ¿Querés descartarlos y cambiar de área?',
            )
        ) {
            return;
        }

        setDraft(areas.find((area) => area.id === id) || null);
        setSelection(null);
        setErrors({});
        setMessage('');
        setConflict(false);
    };
    const reload = () => {
        if (
            dirty &&
            !window.confirm(
                'La recarga descartará tu borrador. ¿Querés continuar?',
            )
        ) {
            return;
        }

        intentionalReload.current = true;
        window.location.reload();
    };

    return (
        <>
            <Head title="Plano del salón" />
            <div className="mx-auto grid max-w-[1600px] min-w-0 gap-5 font-orders">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-balance text-admin-text-primary">
                            Plano del salón
                        </h1>
                        <p className="mt-1 text-sm text-pretty text-admin-text-secondary">
                            {canManage
                                ? 'Configuración física del salón. Seleccioná una mesa para editarla.'
                                : 'Distribución del salón en modo lectura.'}
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            className="min-h-11 bg-admin-accent text-admin-on-accent"
                            disabled={!dirty || saving || conflict}
                            onClick={() => void submit()}
                        >
                            {saving ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                    )}
                </header>
                {canManage && (
                    <div className="grid grid-cols-1 items-end gap-3 rounded-xl border border-admin-border bg-admin-surface p-4 sm:grid-cols-[1fr_1fr_auto]">
                        <div className="min-w-0 flex-1">
                            <Field label="Área">
                                <select
                                    className={selectClass}
                                    value={draft?.id || ''}
                                    disabled={saving}
                                    onChange={(event) =>
                                        pickArea(Number(event.target.value))
                                    }
                                >
                                    <option value="" disabled>
                                        Seleccionar área
                                    </option>
                                    {areas.map((area) => (
                                        <option key={area.id} value={area.id}>
                                            {area.name}
                                            {!area.is_active
                                                ? ' (inactiva)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                        <div className="min-w-0 flex-1">
                            <Field label="Nueva área">
                                <Input
                                    className="min-h-11"
                                    value={newName}
                                    maxLength={100}
                                    onChange={(event) =>
                                        setNewName(event.target.value)
                                    }
                                    placeholder="Nombre del área"
                                    disabled={saving}
                                />
                            </Field>
                        </div>
                        <Button
                            className="min-h-11"
                            variant="outline"
                            disabled={!newName.trim() || saving}
                            onClick={() => void submit(true)}
                        >
                            Crear área
                        </Button>
                    </div>
                )}
                {message && (
                    <div
                        role={
                            conflict || Object.keys(errors).length
                                ? 'alert'
                                : 'status'
                        }
                        className="rounded-lg border border-admin-border bg-admin-surface p-3 text-sm text-admin-text-primary"
                    >
                        {message}
                        {conflict && (
                            <Button
                                className="mt-2 min-h-11 w-full sm:w-auto"
                                variant="outline"
                                onClick={reload}
                            >
                                Recargar plano
                            </Button>
                        )}
                    </div>
                )}
                {Object.keys(errors).length > 0 && (
                    <ul
                        role="alert"
                        className="list-inside list-disc rounded-lg border border-destructive p-3 text-sm text-admin-text-primary"
                    >
                        {Object.entries(errors).map(([key, values]) => (
                            <li key={key}>
                                {key}: {values.join(' ')}
                            </li>
                        ))}
                    </ul>
                )}
                {!draft ? (
                    <p className="rounded-xl border border-admin-border bg-admin-surface p-6 text-admin-text-secondary">
                        Creá un área para empezar. No hay mesas preconfiguradas.
                    </p>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-admin-text-secondary">
                            <span className="mr-auto tabular-nums">
                                Versión {draft.layout_version} ·{' '}
                                {dirty ? 'Cambios sin guardar' : 'Guardado'}
                                {!draft.is_active ? ' · Área inactiva' : ''}
                            </span>
                            {canManage && (
                                <>
                                    <Button
                                        className="min-h-11"
                                        variant="outline"
                                        disabled={saving || preview}
                                        onClick={() => add('table')}
                                    >
                                        Agregar mesa
                                    </Button>
                                    <Button
                                        className="min-h-11"
                                        variant="outline"
                                        disabled={saving || preview}
                                        onClick={() => add('obstacle')}
                                    >
                                        Agregar obstáculo
                                    </Button>
                                    <Button
                                        className="min-h-11"
                                        variant="outline"
                                        onClick={() => setPreview(!preview)}
                                        aria-pressed={preview}
                                    >
                                        {preview
                                            ? 'Volver al editor'
                                            : 'Vista de lectura'}
                                    </Button>
                                </>
                            )}
                        </div>
                        <div className="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <section
                                className="grid min-w-0 gap-3 rounded-xl border border-admin-border bg-admin-surface p-3 sm:p-4"
                                aria-label="Editor visual"
                            >
                                <FloorPlan
                                    area={draft}
                                    editable={!readOnly && !saving}
                                    selected={selection}
                                    onSelect={setSelection}
                                    onGeometryChange={geometryChange}
                                />
                                {canManage && (
                                    <p className="text-xs text-pretty text-admin-text-secondary">
                                        Arrastrá en escritorio o seleccioná y
                                        ajustá los campos. Las superposiciones
                                        están permitidas; los límites del área
                                        se validan al guardar.
                                    </p>
                                )}
                                <Field label="Seleccionar elemento">
                                    <select
                                        className={selectClass}
                                        value={
                                            selection
                                                ? `${selection.kind}:${selection.key}`
                                                : ''
                                        }
                                        onChange={(event) => {
                                            const [kind, key] =
                                                event.target.value.split(':');
                                            setSelection(
                                                key
                                                    ? {
                                                          kind: kind as
                                                              | 'table'
                                                              | 'obstacle',
                                                          key,
                                                      }
                                                    : null,
                                            );
                                        }}
                                    >
                                        <option value="">Ninguno</option>
                                        {draft.tables.map((item) => (
                                            <option
                                                key={item.key}
                                                value={`table:${item.key}`}
                                            >
                                                {tableLabel(item)}
                                                {!item.is_active
                                                    ? ' (inactiva)'
                                                    : ''}
                                            </option>
                                        ))}
                                        {draft.obstacles.map((item, index) => (
                                            <option
                                                key={item.key}
                                                value={`obstacle:${item.key}`}
                                            >
                                                {item.label ||
                                                    `Obstáculo ${index + 1}`}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </section>
                            <fieldset
                                disabled={saving || readOnly}
                                className="grid min-w-0 gap-4 rounded-xl border border-admin-border bg-admin-surface p-4"
                            >
                                <legend className="sr-only">
                                    Propiedades del plano
                                </legend>
                                <h2 className="font-semibold text-admin-text-primary">
                                    Área
                                </h2>
                                <Field label="Nombre del área">
                                    <Input
                                        className="min-h-11"
                                        maxLength={100}
                                        value={draft.name}
                                        onChange={(event) =>
                                            setDraft({
                                                ...draft,
                                                name: event.target.value,
                                            })
                                        }
                                    />
                                </Field>
                                <div className="grid grid-cols-2 gap-2">
                                    <Field label="Ancho lógico">
                                        <Input
                                            className="min-h-11"
                                            type="number"
                                            min={100}
                                            max={10000}
                                            value={draft.width}
                                            onChange={(event) =>
                                                setDraft({
                                                    ...draft,
                                                    width: Number(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                    </Field>
                                    <Field label="Alto lógico">
                                        <Input
                                            className="min-h-11"
                                            type="number"
                                            min={100}
                                            max={10000}
                                            value={draft.height}
                                            onChange={(event) =>
                                                setDraft({
                                                    ...draft,
                                                    height: Number(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                    </Field>
                                </div>
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={draft.is_active}
                                        onChange={(event) =>
                                            setDraft({
                                                ...draft,
                                                is_active: event.target.checked,
                                            })
                                        }
                                    />
                                    Área activa
                                </label>
                                {selected && selection && (
                                    <>
                                        <h2 className="border-t border-admin-border pt-4 font-semibold">
                                            {table
                                                ? tableLabel(table)
                                                : 'Obstáculo'}
                                        </h2>
                                        {table ? (
                                            <>
                                                <Field label="Número / código">
                                                    <Input
                                                        className="min-h-11"
                                                        maxLength={40}
                                                        value={table.code}
                                                        onChange={(event) =>
                                                            property(
                                                                'code',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Nombre opcional">
                                                    <Input
                                                        className="min-h-11"
                                                        maxLength={100}
                                                        value={table.name || ''}
                                                        onChange={(event) =>
                                                            property(
                                                                'name',
                                                                event.target
                                                                    .value ||
                                                                    null,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Capacidad">
                                                    <Input
                                                        className="min-h-11"
                                                        type="number"
                                                        min={1}
                                                        max={100}
                                                        value={
                                                            table.capacity ?? ''
                                                        }
                                                        onChange={(event) =>
                                                            property(
                                                                'capacity',
                                                                event.target
                                                                    .value
                                                                    ? Number(
                                                                          event
                                                                              .target
                                                                              .value,
                                                                      )
                                                                    : null,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            table.is_active
                                                        }
                                                        onChange={(event) =>
                                                            property(
                                                                'is_active',
                                                                event.target
                                                                    .checked,
                                                            )
                                                        }
                                                    />
                                                    Mesa activa
                                                </label>
                                            </>
                                        ) : (
                                            <Field label="Etiqueta del obstáculo">
                                                <Input
                                                    className="min-h-11"
                                                    maxLength={100}
                                                    value={
                                                        obstacle!.label || ''
                                                    }
                                                    onChange={(event) =>
                                                        property(
                                                            'label',
                                                            event.target
                                                                .value || null,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        )}
                                        <Field label="Forma">
                                            <select
                                                className={selectClass}
                                                value={
                                                    table?.shape ||
                                                    obstacle?.type
                                                }
                                                onChange={(event) =>
                                                    changeShape(
                                                        event.target
                                                            .value as FloorShape,
                                                    )
                                                }
                                            >
                                                <option value="rectangle">
                                                    Rectángulo
                                                </option>
                                                <option value="circle">
                                                    Círculo
                                                </option>
                                            </select>
                                        </Field>
                                        <div className="grid grid-cols-2 gap-2">
                                            {(
                                                [
                                                    'x',
                                                    'y',
                                                    'width',
                                                    'height',
                                                ] as const
                                            ).map((key) => (
                                                <Field
                                                    key={key}
                                                    label={
                                                        {
                                                            x: 'Posición X',
                                                            y: 'Posición Y',
                                                            width: 'Ancho',
                                                            height: 'Alto',
                                                        }[key]
                                                    }
                                                >
                                                    <Input
                                                        className="min-h-11 tabular-nums"
                                                        type="number"
                                                        min={0}
                                                        max={1}
                                                        step="0.0001"
                                                        value={
                                                            table && key === 'x'
                                                                ? table.position_x
                                                                : table &&
                                                                    key === 'y'
                                                                  ? table.position_y
                                                                  : (selected[
                                                                        key as
                                                                            | 'width'
                                                                            | 'height'
                                                                    ] ??
                                                                    obstacle?.[
                                                                        key
                                                                    ])
                                                        }
                                                        onChange={(event) =>
                                                            property(
                                                                table &&
                                                                    key === 'x'
                                                                    ? 'position_x'
                                                                    : table &&
                                                                        key ===
                                                                            'y'
                                                                      ? 'position_y'
                                                                      : key,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            ))}
                                        </div>
                                        {table && (
                                            <Field label="Rotación">
                                                <select
                                                    className={selectClass}
                                                    value={table.rotation}
                                                    onChange={(event) => {
                                                        const rotation = Number(
                                                            event.target.value,
                                                        );
                                                        property(
                                                            'rotation',
                                                            rotation,
                                                        );
                                                        geometryChange(
                                                            selection,
                                                            constrainGeometry(
                                                                {
                                                                    x: table.position_x,
                                                                    y: table.position_y,
                                                                    width: table.width,
                                                                    height: table.height,
                                                                },
                                                                draft,
                                                                table.shape,
                                                                rotation,
                                                            ),
                                                        );
                                                    }}
                                                >
                                                    {[
                                                        0, 45, 90, 135, 180,
                                                        225, 270, 315,
                                                    ].map((angle) => (
                                                        <option
                                                            key={angle}
                                                            value={angle}
                                                        >
                                                            {angle}°
                                                        </option>
                                                    ))}
                                                </select>
                                            </Field>
                                        )}
                                        {obstacle && (
                                            <Button
                                                className="min-h-11"
                                                variant="outline"
                                                onClick={() => {
                                                    setDraft({
                                                        ...draft,
                                                        obstacles:
                                                            draft.obstacles.filter(
                                                                (item) =>
                                                                    item.key !==
                                                                    obstacle.key,
                                                            ),
                                                    });
                                                    setSelection(null);
                                                }}
                                            >
                                                Quitar obstáculo
                                            </Button>
                                        )}
                                    </>
                                )}
                            </fieldset>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
