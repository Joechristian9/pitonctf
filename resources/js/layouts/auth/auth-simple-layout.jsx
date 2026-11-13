import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import BrandBackdrop from '@/components/brand-backdrop';
import { home } from '@/routes';

/** Shared by every auth page (log in, password reset, two-factor, ...): the PITON brand above a card. */
export default function AuthSimpleLayout({ children, title, description }) {
    return (
        <div className="relative isolate flex min-h-dvh items-center justify-center overflow-hidden bg-background px-4 py-12">
            <BrandBackdrop />

            <div className="w-full max-w-sm">
                <Link
                    href={home()}
                    className="mx-auto mb-8 flex w-fit flex-col items-center gap-3 rounded-xl p-1 focus-visible:ring-[3px] focus-visible:ring-ring/60 focus-visible:outline-none"
                >
                    <span className="flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg ring-4 ring-primary/20">
                        <AppLogoIcon className="size-8 fill-current" />
                    </span>
                    <span className="text-center">
                        <span className="block text-xl font-black tracking-tight">
                            PITON CTF
                        </span>
                        <span className="block text-xs font-semibold tracking-[0.3em] text-primary uppercase">
                            Cyber Defense Exercise
                        </span>
                    </span>
                </Link>

                <main className="rounded-2xl border bg-card/90 p-6 shadow-xl backdrop-blur-sm sm:p-8">
                    <div className="mb-6 space-y-1 text-center">
                        <h1 className="text-xl font-semibold">{title}</h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </main>
            </div>
        </div>
    );
}
