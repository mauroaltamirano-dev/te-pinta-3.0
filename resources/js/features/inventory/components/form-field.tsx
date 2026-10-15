import type { PropsWithChildren } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface FormFieldProps extends PropsWithChildren {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    className?: string;
}

export function FormField({
    id,
    label,
    error,
    hint,
    className,
    children,
}: FormFieldProps) {
    return (
        <div className={cn('grid content-start gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && !error && (
                <p
                    id={`${id}-hint`}
                    className="text-xs text-admin-text-tertiary"
                >
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={`${id}-error`}
                    className="text-xs text-destructive"
                    role="alert"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
