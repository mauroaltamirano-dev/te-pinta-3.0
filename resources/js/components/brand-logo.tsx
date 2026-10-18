import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type BrandLogoProps = Omit<ComponentProps<'span'>, 'children'> & {
    alt?: string;
};

export function BrandLogo({
    className,
    alt = 'Te Pinta',
    ...props
}: BrandLogoProps) {
    return (
        <span
            role={alt ? 'img' : undefined}
            aria-label={alt || undefined}
            aria-hidden={alt ? undefined : true}
            className={cn(
                'relative block overflow-hidden bg-admin-accent',
                className,
            )}
            {...props}
        >
            <span
                className="absolute inset-0 block bg-admin-on-accent"
                style={{
                    maskImage: 'url(/icons/te-pinta-mark.svg)',
                    maskSize: 'cover',
                    WebkitMaskImage: 'url(/icons/te-pinta-mark.svg)',
                    WebkitMaskSize: 'cover',
                }}
            />
        </span>
    );
}
