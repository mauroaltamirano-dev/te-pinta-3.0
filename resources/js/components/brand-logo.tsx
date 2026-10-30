import type { ComponentProps } from 'react';
import { useRestaurantIdentity } from '@/hooks/use-restaurant-identity';
import { cn } from '@/lib/utils';

type BrandLogoProps = Omit<ComponentProps<'span'>, 'children'> & {
    alt?: string;
};

export function BrandLogo({ className, alt, ...props }: BrandLogoProps) {
    const restaurant = useRestaurantIdentity();
    const label = alt ?? restaurant.name;

    return (
        <span
            role={label ? 'img' : undefined}
            aria-label={label || undefined}
            aria-hidden={label ? undefined : true}
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
