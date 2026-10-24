import { Head } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { BrandBadge } from '@/components/brand-badge';
import { useRestaurantBranding } from '@/hooks/use-restaurant-branding';

export default function ComingSoon({
    whatsappUrl,
}: {
    whatsappUrl: string | null;
}) {
    useRestaurantBranding();

    return (
        <div className="public-theme relative isolate flex min-h-svh flex-col overflow-hidden bg-public-ink font-sans text-white">
            <Head title="Muy pronto" />
            <img
                src="/images/brand/bg-empanada.png"
                alt=""
                aria-hidden="true"
                className="pointer-events-none absolute -right-24 -bottom-24 -z-10 size-96 max-w-none object-contain opacity-20 select-none sm:size-[32rem] lg:right-[4%] lg:bottom-[-6rem]"
            />
            <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center px-4 py-16 text-center sm:px-8">
                <BrandBadge
                    title="Te Pinta"
                    className="size-32 text-white sm:size-40"
                />
                <p className="mt-10 inline-flex items-center gap-3 text-xs font-bold tracking-[0.2em] text-white/80 uppercase">
                    <span
                        className="h-px w-8 bg-public-canvas"
                        aria-hidden="true"
                    />
                    Web en construcción
                    <span
                        className="h-px w-8 bg-public-canvas"
                        aria-hidden="true"
                    />
                </p>
                <h1 className="mt-5 font-serif text-[clamp(2.6rem,7vw,4.75rem)] leading-[0.98] font-semibold tracking-[-0.03em] text-balance">
                    Estamos preparando algo rico
                    <span className="text-public-canvas">.</span>
                </h1>
                <p className="mt-6 max-w-lg text-base leading-7 text-white/85 sm:text-lg">
                    Muy pronto vas a poder pedir tus empanadas desde acá. Y se
                    vienen novedades.
                </p>
                <p className="mt-3 text-sm text-white/70">
                    Mientras tanto, escribinos para hacer tu pedido.
                </p>
                {whatsappUrl && (
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-10 inline-flex min-h-13 w-full items-center justify-center gap-2 rounded-full bg-public-canvas px-7 text-base font-semibold text-white shadow-lg shadow-black/25 transition-colors hover:bg-public-earth-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-auto"
                    >
                        <MessageCircle className="size-5" aria-hidden="true" />
                        Escribinos por WhatsApp
                    </a>
                )}
            </main>
            <footer className="px-4 py-6 text-center text-xs text-white/55">
                © {new Date().getFullYear()} Te Pinta · Bell Ville, Córdoba
            </footer>
        </div>
    );
}
