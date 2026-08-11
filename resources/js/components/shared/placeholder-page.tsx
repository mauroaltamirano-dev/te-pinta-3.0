import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type PlaceholderPageProps = {
    title: string;
    description: string;
};

export function PlaceholderPage({ title, description }: PlaceholderPageProps) {
    return (
        <>
            <Head title={title} />
            <section className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-12 sm:px-6 sm:py-16">
                <div className="flex flex-col gap-2">
                    <p className="text-xs font-semibold tracking-[0.18em] text-primary uppercase">
                        Foundation stage
                    </p>
                    <h1 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
                        {title}
                    </h1>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>{title} module</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            The navigation boundary is ready. Business behavior
                            will be added only when its rules are specified.
                        </p>
                    </CardContent>
                </Card>
            </section>
        </>
    );
}
