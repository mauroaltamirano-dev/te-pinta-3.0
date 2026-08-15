import { Check, Clipboard } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function CopyOrderDetailButton({
    summary,
    className,
}: {
    summary: string;
    className?: string;
}) {
    const [copied, setCopied] = useState(false);
    const resetTimer = useRef<number | null>(null);

    useEffect(
        () => () => {
            if (resetTimer.current !== null) {
                window.clearTimeout(resetTimer.current);
            }
        },
        [],
    );

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(summary);
        } catch {
            const textarea = document.createElement('textarea');
            textarea.value = summary;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }

        setCopied(true);

        if (resetTimer.current !== null) {
            window.clearTimeout(resetTimer.current);
        }

        resetTimer.current = window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Button
            type="button"
            variant="outline"
            onClick={copy}
            className={cn('border-admin-accent/50', className)}
            aria-live="polite"
        >
            {copied ? <Check /> : <Clipboard />}
            {copied ? 'Detalle copiado' : 'Copiar detalle'}
        </Button>
    );
}
