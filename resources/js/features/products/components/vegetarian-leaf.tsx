import { Leaf } from 'lucide-react';
import { cn } from '@/lib/utils';

export function VegetarianLeaf({ className }: { className?: string }) {
    return (
        <span
            role="img"
            aria-label="Vegetariana"
            title="Vegetariana"
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700',
                className,
            )}
        >
            <Leaf className="size-[60%]" aria-hidden="true" />
        </span>
    );
}
