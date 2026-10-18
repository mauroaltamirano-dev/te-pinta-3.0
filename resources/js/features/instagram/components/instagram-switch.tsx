import { cn } from '@/lib/utils';

export function InstagramSwitch({
    checked,
    disabled = false,
    label,
    onCheckedChange,
}: {
    checked: boolean;
    disabled?: boolean;
    label: string;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={() => onCheckedChange(!checked)}
            className={cn(
                'relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition-[background-color,border-color,transform] focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:ring-offset-2 focus-visible:ring-offset-admin-surface focus-visible:outline-none active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50',
                checked
                    ? 'border-admin-accent bg-admin-accent'
                    : 'border-admin-border bg-admin-surface-raised',
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'size-5 rounded-full bg-admin-surface shadow-sm transition-transform',
                    checked ? 'translate-x-6' : 'translate-x-1',
                )}
            />
        </button>
    );
}
