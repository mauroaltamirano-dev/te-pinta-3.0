import { router } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileImage,
    FileText,
    LoaderCircle,
    Paperclip,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import {
    deleteManagementAttachment,
    listManagementAttachments,
    uploadManagementAttachment,
} from '../attachment-api';
import { formatManagementDate } from '../presentation';
import type { ManagementAttachment } from '../types';

const MAX_FILE_SIZE = 10 * 1024 * 1024;
const ALLOWED_MIME_TYPES = new Set([
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
]);

export function AttachmentSection({
    transactionId,
    attachmentsCount,
}: {
    transactionId: number;
    attachmentsCount: number;
}) {
    const [attachments, setAttachments] = useState<
        ManagementAttachment[] | null
    >(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [deleting, setDeleting] = useState<ManagementAttachment | null>(null);
    const [loadVersion, setLoadVersion] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const controller = new AbortController();

        void listManagementAttachments(transactionId, controller.signal)
            .then(setAttachments)
            .catch((error: unknown) => {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                setLoadError(errorMessage(error));
            });

        return () => controller.abort();
    }, [transactionId, loadVersion]);

    const selectFile = async (file: File | undefined) => {
        if (!file) {
            return;
        }

        const validationError = validateFile(file);

        if (validationError) {
            setUploadError(validationError);

            if (inputRef.current) {
                inputRef.current.value = '';
            }

            return;
        }

        setUploading(true);
        setUploadError(null);

        try {
            const attachment = await uploadManagementAttachment(
                transactionId,
                file,
            );
            setAttachments((current) => [attachment, ...(current ?? [])]);
            setLoadError(null);
            router.reload({ only: ['transactions'] });
        } catch (error) {
            setUploadError(errorMessage(error));
        } finally {
            setUploading(false);

            if (inputRef.current) {
                inputRef.current.value = '';
            }
        }
    };

    const attachmentTotal = attachments?.length ?? attachmentsCount;

    return (
        <section
            aria-labelledby={`transaction-${transactionId}-attachments-title`}
            className="rounded-lg border border-admin-border bg-admin-surface-raised p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3
                        id={`transaction-${transactionId}-attachments-title`}
                        className="flex items-center gap-2 text-xs font-medium tracking-wide text-admin-text-tertiary uppercase"
                    >
                        <Paperclip aria-hidden="true" className="size-4" />
                        Comprobantes ({attachmentTotal})
                    </h3>
                    <p className="mt-1 text-xs text-admin-text-tertiary">
                        PDF, JPG, PNG o WEBP · máximo 10 MB
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    disabled={uploading}
                    onClick={() => inputRef.current?.click()}
                    className="h-11"
                >
                    {uploading ? (
                        <LoaderCircle
                            aria-hidden="true"
                            className="animate-spin"
                        />
                    ) : (
                        <Upload aria-hidden="true" />
                    )}
                    {uploading ? 'Adjuntando…' : 'Adjuntar'}
                </Button>
                <Input
                    ref={inputRef}
                    type="file"
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    className="sr-only"
                    aria-label="Seleccionar comprobante"
                    aria-describedby={
                        uploadError
                            ? `transaction-${transactionId}-attachment-error`
                            : undefined
                    }
                    onChange={(event) =>
                        void selectFile(event.target.files?.[0])
                    }
                />
            </div>

            {uploadError && (
                <p
                    id={`transaction-${transactionId}-attachment-error`}
                    role="alert"
                    className="mt-3 text-sm text-destructive"
                >
                    {uploadError}
                </p>
            )}

            {attachments === null && !loadError && (
                <p
                    role="status"
                    className="mt-4 text-sm text-admin-text-secondary"
                >
                    Cargando comprobantes…
                </p>
            )}

            {loadError && (
                <div className="mt-4 grid gap-3">
                    <p role="alert" className="text-sm text-destructive">
                        {loadError}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            setAttachments(null);
                            setLoadError(null);
                            setLoadVersion((version) => version + 1);
                        }}
                        className="h-11 w-full"
                    >
                        Reintentar
                    </Button>
                </div>
            )}

            {attachments?.length === 0 && (
                <p className="mt-4 text-sm text-admin-text-secondary">
                    Sin comprobantes adjuntos.
                </p>
            )}

            {attachments && attachments.length > 0 && (
                <ul className="mt-4 grid gap-3">
                    {attachments.map((attachment) => (
                        <AttachmentCard
                            key={attachment.id}
                            attachment={attachment}
                            onDelete={() => setDeleting(attachment)}
                        />
                    ))}
                </ul>
            )}

            <AttachmentDeleteDialog
                key={deleting?.id ?? 'closed'}
                transactionId={transactionId}
                attachment={deleting}
                onOpenChange={(open) => !open && setDeleting(null)}
                onDeleted={(attachmentId) => {
                    setAttachments(
                        (current) =>
                            current?.filter(
                                (attachment) => attachment.id !== attachmentId,
                            ) ?? [],
                    );
                    setDeleting(null);
                    router.reload({ only: ['transactions'] });
                }}
            />
        </section>
    );
}

function AttachmentCard({
    attachment,
    onDelete,
}: {
    attachment: ManagementAttachment;
    onDelete: () => void;
}) {
    const visual = attachment.mime_type.startsWith('image/');
    const FileIcon = visual ? FileImage : FileText;

    return (
        <li className="grid gap-3 rounded-lg border border-admin-border bg-admin-surface p-3">
            <div className="flex min-w-0 items-start gap-3">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-admin-action text-admin-accent">
                    <FileIcon aria-hidden="true" className="size-5" />
                </span>
                <div className="min-w-0">
                    <p className="text-sm font-medium break-words text-white">
                        {attachment.name}
                    </p>
                    <p className="mt-1 text-xs text-admin-text-tertiary">
                        {mimeLabel(attachment.mime_type)} ·{' '}
                        {formatFileSize(attachment.size)} ·{' '}
                        {formatManagementDate(attachment.created_at)}
                    </p>
                    <p className="mt-1 text-xs text-admin-text-tertiary">
                        Adjuntado por {attachment.uploaded_by.name}
                    </p>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_2.75rem]">
                {attachment.preview_url && (
                    <Button asChild variant="outline" className="h-11">
                        <a
                            href={attachment.preview_url}
                            target="_blank"
                            rel="noreferrer"
                            aria-label={`Ver ${attachment.name}`}
                        >
                            <Eye aria-hidden="true" /> Ver
                        </a>
                    </Button>
                )}
                <Button
                    asChild
                    variant="outline"
                    className={`h-11 ${attachment.preview_url ? '' : 'col-span-2 sm:col-span-1'}`}
                >
                    <a
                        href={attachment.download_url}
                        aria-label={`Descargar ${attachment.name}`}
                    >
                        <Download aria-hidden="true" /> Descargar
                    </a>
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onDelete}
                    aria-label={`Eliminar ${attachment.name}`}
                    className="col-span-2 size-11 justify-self-end text-destructive sm:col-span-1"
                >
                    <Trash2 aria-hidden="true" />
                </Button>
            </div>
        </li>
    );
}

function AttachmentDeleteDialog({
    transactionId,
    attachment,
    onOpenChange,
    onDeleted,
}: {
    transactionId: number;
    attachment: ManagementAttachment | null;
    onOpenChange: (open: boolean) => void;
    onDeleted: (attachmentId: number) => void;
}) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    if (!attachment) {
        return null;
    }

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        setError(null);

        try {
            await deleteManagementAttachment(
                transactionId,
                attachment.id,
                reason,
            );
            onDeleted(attachment.id);
        } catch (caught) {
            setError(errorMessage(caught));
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark border-admin-border bg-admin-surface sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Eliminar comprobante</DialogTitle>
                    <DialogDescription>
                        Esta acción queda auditada. Indicá por qué retirás{' '}
                        <strong className="font-medium text-white">
                            {attachment.name}
                        </strong>
                        .
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`attachment-${attachment.id}-reason`}>
                            Motivo
                        </Label>
                        <Textarea
                            id={`attachment-${attachment.id}-reason`}
                            value={reason}
                            minLength={3}
                            maxLength={240}
                            required
                            autoFocus
                            onChange={(event) => setReason(event.target.value)}
                            aria-invalid={Boolean(error)}
                            aria-describedby={
                                error
                                    ? `attachment-${attachment.id}-delete-error`
                                    : undefined
                            }
                            className="min-h-24 border-admin-border bg-admin-canvas"
                        />
                        {error && (
                            <p
                                id={`attachment-${attachment.id}-delete-error`}
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="h-11"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing || reason.trim().length < 3}
                            className="h-11"
                        >
                            {processing && (
                                <LoaderCircle
                                    aria-hidden="true"
                                    className="animate-spin"
                                />
                            )}
                            Eliminar comprobante
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function validateFile(file: File) {
    if (!ALLOWED_MIME_TYPES.has(file.type)) {
        return 'El archivo debe ser PDF, JPG, PNG o WEBP.';
    }

    if (file.size > MAX_FILE_SIZE) {
        return 'El archivo no puede superar los 10 MB.';
    }

    return null;
}

function formatFileSize(bytes: number) {
    if (bytes < 1024 * 1024) {
        return `${new Intl.NumberFormat('es-AR', { maximumFractionDigits: 1 }).format(bytes / 1024)} KB`;
    }

    return `${new Intl.NumberFormat('es-AR', { maximumFractionDigits: 1 }).format(bytes / (1024 * 1024))} MB`;
}

function mimeLabel(mimeType: string) {
    return (
        {
            'application/pdf': 'PDF',
            'image/jpeg': 'JPG',
            'image/png': 'PNG',
            'image/webp': 'WEBP',
        }[mimeType] ?? 'Archivo'
    );
}

function errorMessage(error: unknown) {
    return error instanceof Error
        ? error.message
        : 'No se pudo completar la operación.';
}
