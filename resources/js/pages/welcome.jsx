import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import BrandBackdrop from '@/components/brand-backdrop';
import { Button } from '@/components/ui/button';
import { challenges, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <main className="relative isolate flex min-h-dvh items-center justify-center overflow-hidden bg-background px-4 py-16 text-foreground">
                <BrandBackdrop />

                <section className="flex flex-col items-center text-center">
                    <div className="relative mb-8">
                        <div
                            aria-hidden="true"
                            className="absolute inset-0 -z-10 scale-150 rounded-full bg-primary/25 blur-3xl"
                        />
                        <span className="flex size-24 items-center justify-center rounded-3xl bg-primary text-primary-foreground shadow-xl ring-4 ring-primary/20 sm:size-28">
                            <AppLogoIcon className="size-14 fill-current sm:size-16" />
                        </span>
                    </div>

                    <h1 className="text-5xl font-black tracking-tight sm:text-7xl">
                        PITON CTF
                    </h1>
                    <p className="mt-4 flex items-center gap-3 text-sm font-semibold tracking-[0.35em] text-primary uppercase sm:text-lg">
                        <span
                            className="h-px w-8 bg-primary/60 sm:w-12"
                            aria-hidden="true"
                        />
                        Cyber Defense Exercise
                        <span
                            className="h-px w-8 bg-primary/60 sm:w-12"
                            aria-hidden="true"
                        />
                    </p>

                    <Button
                        asChild
                        size="lg"
                        className="mt-10 h-12 px-10 text-base"
                    >
                        <Link href={auth.user ? challenges() : login()}>
                            {auth.user ? 'Go to challenges' : 'Log in to play'}
                        </Link>
                    </Button>
                </section>
            </main>
        </>
    );
}
