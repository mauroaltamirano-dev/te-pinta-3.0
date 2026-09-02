import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type BrandLogoProps = Omit<ComponentProps<'img'>, 'src' | 'width' | 'height'>;

export function BrandLogo({
    className,
    alt = 'Te Pinta',
    ...props
}: BrandLogoProps) {
    return (
        <img
            src="/icons/te-pinta-logo.svg"
            width={512}
            height={512}
            alt={alt}
            className={cn('block object-cover', className)}
            {...props}
        />
    );
}
