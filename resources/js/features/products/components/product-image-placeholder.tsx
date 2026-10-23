import { cn } from '@/lib/utils';

/** Neutral stand-in for a product without photo: the brand monogram, never a fake image. */
export function ProductImagePlaceholder({ className }: { className?: string }) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'grid size-full place-items-center bg-[radial-gradient(circle_at_center,#ffffff_0%,var(--color-public-earth)_70%)]',
                className,
            )}
        >
            <img
                src="/icons/te-pinta-logo.svg"
                alt=""
                width="96"
                height="96"
                loading="lazy"
                className="size-[28%] max-h-24 min-h-10 max-w-24 min-w-10 opacity-25 grayscale"
            />
        </div>
    );
}
