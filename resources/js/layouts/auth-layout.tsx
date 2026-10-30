import { useRestaurantBranding } from '@/hooks/use-restaurant-branding';
import { useRestaurantIdentity } from '@/hooks/use-restaurant-identity';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    /** A function receives the restaurant name, for titles that mention it. */
    title?: string | ((restaurantName: string) => string);
    description?: string;
    children: React.ReactNode;
}) {
    useRestaurantBranding();
    const restaurant = useRestaurantIdentity();

    return (
        <AuthLayoutTemplate
            title={typeof title === 'function' ? title(restaurant.name) : title}
            description={description}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
