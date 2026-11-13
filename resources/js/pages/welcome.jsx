import { Head, Link, usePage } from '@inertiajs/react';
import { FileSearch, Globe, KeyRound, Sparkles } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { challenges, login } from '@/routes';

const categories = [
    {
        title: 'Crypto',
        icon: KeyRound,
        text: 'Decode secret messages: Base64, Caesar shifts, XOR and more.',
    },
    {
        title: 'Web',
        icon: Globe,
        text: 'Inspect practice websites with your browser to find what they hide.',
    },
    {
        title: 'Forensics',
        icon: FileSearch,
        text: 'Dig through logs, archives and photos for the evidence.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const playHref = auth.user ? challenges() : login();

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <div className="h-1.5 bg-primary" aria-hidden="true" />

                <header className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-6 fill-current" />
                        </span>
                        <div className="min-w-0">
                            <p className="font-bold tracking-widest">PITON</p>
                            <p className="truncate text-xs text-muted-foreground">
                                Philippine Information Technology of the North
                            </p>
                        </div>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={playHref}>
                            {auth.user ? 'Challenges' : 'Log in'}
                        </Link>
                    </Button>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-12 px-4 py-10 sm:px-6">
                    <section className="max-w-2xl space-y-6">
                        <p className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium text-muted-foreground">
                            <Sparkles className="size-3.5" />
                            Capture the Flag · classroom edition
                        </p>
                        <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
                            PITON CTF
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            Crack codes, inspect websites and dig through files
                            to find hidden flags. Start with the three warm-ups,
                            then race your classmates up the scoreboard.
                        </p>
                        <div className="flex flex-wrap items-center gap-4">
                            <Button asChild size="lg">
                                <Link href={playHref}>
                                    {auth.user
                                        ? 'Go to challenges'
                                        : 'Log in to play'}
                                </Link>
                            </Button>
                            {!auth.user && (
                                <p className="text-sm text-muted-foreground">
                                    No account yet? Your instructor creates one
                                    for you.
                                </p>
                            )}
                        </div>
                    </section>

                    <section
                        aria-label="Challenge categories"
                        className="grid gap-4 sm:grid-cols-3"
                    >
                        {categories.map(({ title, icon: Icon, text }) => (
                            <div
                                key={title}
                                className="rounded-xl border bg-card p-5"
                            >
                                <Icon className="mb-3 size-6 text-primary" />
                                <h2 className="font-semibold">{title}</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {text}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="border-t px-4 py-5 text-center text-xs text-muted-foreground">
                    PITON · Philippine Information Technology of the North
                </footer>
            </div>
        </>
    );
}
