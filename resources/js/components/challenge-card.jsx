import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowRight,
    Check,
    CircleCheck,
    Crosshair,
    Download,
    ExternalLink,
    Eye,
    Lightbulb,
    Lock,
    Upload,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { hint, submit, writeup } from '@/routes/challenges';

// Four tiles per row on wide screens; a short last row stays centered (the parent is a centered flex-wrap).
const tileWidth =
    'w-full sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.667rem)] xl:w-[calc(25%-0.75rem)]';

/** A clickable tile; the challenge itself opens in a dialog. */
export default function ChallengeCard({ challenge, isPlayable }) {
    if (challenge.locked_by) {
        return <LockedTile challenge={challenge} />;
    }

    const isWarmUp = Boolean(challenge.unlocks_category);

    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        tileWidth,
                        'group relative flex flex-col items-center gap-3 rounded-xl border p-5 text-center shadow-sm transition',
                        'hover:-translate-y-0.5 hover:shadow-lg focus-visible:ring-[3px] focus-visible:ring-ring/60 focus-visible:outline-none',
                        challenge.solved
                            ? 'border-green-500/60 bg-gradient-to-b from-green-700 to-green-900 text-white'
                            : 'border-primary/40 bg-card hover:border-primary',
                    )}
                >
                    {challenge.solved && (
                        <span className="absolute top-3 right-3 flex size-6 items-center justify-center rounded-full bg-green-400 text-green-950">
                            <Check className="size-4" strokeWidth={3} />
                            <span className="sr-only">Solved</span>
                        </span>
                    )}
                    <Crosshair
                        className={cn(
                            'size-8',
                            challenge.solved
                                ? 'text-green-300'
                                : 'text-primary',
                        )}
                        aria-hidden="true"
                    />
                    <span className="flex min-h-10 items-center text-base leading-tight font-semibold">
                        {challenge.title}
                    </span>
                    <span
                        className={cn(
                            'h-px w-3/4',
                            challenge.solved ? 'bg-white/25' : 'bg-border',
                        )}
                        aria-hidden="true"
                    />
                    <span
                        className={cn(
                            'font-mono text-lg font-semibold tabular-nums',
                            challenge.solved
                                ? 'text-green-200'
                                : 'text-primary',
                        )}
                    >
                        {isWarmUp ? 'Warm-up' : challenge.points}
                        {!isWarmUp && <span className="sr-only"> points</span>}
                    </span>
                </button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{challenge.title}</DialogTitle>
                    <DialogDescription>
                        {challenge.category} ·{' '}
                        {isWarmUp ? 'Warm-up' : `${challenge.points} pts`}
                    </DialogDescription>
                </DialogHeader>
                <ChallengeDetails
                    challenge={challenge}
                    isPlayable={isPlayable}
                    isWarmUp={isWarmUp}
                />
            </DialogContent>
        </Dialog>
    );
}

function ChallengeDetails({ challenge, isPlayable, isWarmUp }) {
    const { flash } = usePage();
    const [result, setResult] = useState(null);

    // Every tile gets the same flash, so only keep the one for this challenge. It is kept in
    // state because the 5-second live refresh brings an empty flash that would wipe it.
    const flashResult =
        flash?.flagResult?.challenge_id === challenge.id
            ? flash.flagResult
            : null;
    if (flashResult !== null && flashResult !== result) {
        setResult(flashResult);
    }
    const inputId = `flag-${challenge.id}`;

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm whitespace-pre-line">
                {challenge.description}
            </p>

            {challenge.links.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {challenge.links.map((link) => {
                        // Practice web pages are meant to be browsed; every other file is downloaded and opened by the student.
                        const isPage = /\.html?$/i.test(link);

                        return (
                            <Button
                                key={link}
                                variant="outline"
                                size="sm"
                                asChild
                            >
                                <a
                                    href={link}
                                    {...(isPage
                                        ? {
                                              target: '_blank',
                                              rel: 'noopener noreferrer',
                                          }
                                        : { download: true })}
                                >
                                    {isPage ? <ExternalLink /> : <Download />}
                                    {link.split('/').pop()}
                                    <span className="sr-only">
                                        {isPage
                                            ? '(opens in a new tab)'
                                            : '(downloads)'}
                                    </span>
                                </a>
                            </Button>
                        );
                    })}
                </div>
            )}

            {challenge.hint &&
            challenge.hint_cost === 0 &&
            !challenge.solved ? (
                <FreeHint hint={challenge.hint} />
            ) : challenge.hint ? (
                <p className="rounded-md bg-muted p-3 text-sm">
                    <span className="font-medium">Hint: </span>
                    {challenge.hint}
                </p>
            ) : (
                challenge.has_hint &&
                isPlayable && <HintDialog challenge={challenge} />
            )}

            <div>
                {challenge.solved ? (
                    <p className="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                        <CircleCheck className="size-4" />
                        {isWarmUp
                            ? `Solved · ${challenge.unlocks_category} unlocked`
                            : `Solved by ${challenge.solved_by ?? 'your team'} · +${challenge.earned} pts`}
                    </p>
                ) : !isPlayable || challenge.tries_left === 0 ? (
                    <p className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Lock className="size-4" />
                        {isPlayable
                            ? 'Locked: no tries left'
                            : 'Not open for submissions'}
                    </p>
                ) : (
                    <Form
                        {...submit.form(challenge.id)}
                        options={{ preserveScroll: true }}
                        // A wrong flag still returns OK with a message, so this clears the input for the next try.
                        resetOnSuccess
                        disableWhileProcessing
                        className="grid gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Label htmlFor={inputId}>Flag</Label>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Input
                                        id={inputId}
                                        name="flag"
                                        placeholder="CTF{...}"
                                        autoComplete="off"
                                        spellCheck={false}
                                        required
                                        aria-invalid={
                                            errors.flag ? true : undefined
                                        }
                                        aria-describedby={`${inputId}-result`}
                                    />
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Submit
                                    </Button>
                                </div>
                                <InputError message={errors.flag} />
                                <p className="text-xs text-muted-foreground">
                                    {isWarmUp
                                        ? 'No try limit on warm-ups.'
                                        : `${challenge.tries_left} ${challenge.tries_left === 1 ? 'try' : 'tries'} left`}
                                </p>
                            </>
                        )}
                    </Form>
                )}

                {/* Always rendered so screen readers announce the message when it changes. */}
                <p
                    id={`${inputId}-result`}
                    aria-live="polite"
                    className={cn(
                        'mt-2 text-sm',
                        result?.ok
                            ? 'text-green-700 dark:text-green-400'
                            : 'text-red-600 dark:text-red-400',
                    )}
                >
                    {result?.message}
                </p>
                {challenge.guesses?.length > 0 && (
                    <Popover>
                        <PopoverTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="mt-1 -ml-2"
                            >
                                <Eye />
                                {`Tried answers (${challenge.guesses.length})`}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent align="start" className="w-80 p-3">
                            <p className="mb-2 text-sm font-medium">
                                Your team already tried
                            </p>
                            <ul className="grid max-h-60 gap-1 overflow-y-auto text-sm">
                                {challenge.guesses.map((guess, index) => (
                                    <li
                                        key={index}
                                        className="flex flex-wrap items-center gap-2"
                                    >
                                        <code className="rounded bg-muted px-2 py-0.5 font-mono break-all">
                                            {guess.flag}
                                        </code>
                                        {guess.by && (
                                            <span className="text-xs text-muted-foreground">
                                                by {guess.by}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </PopoverContent>
                    </Popover>
                )}
            </div>

            <WriteupUpload challenge={challenge} />
        </div>
    );
}

/** The team's write-up for this challenge: one PDF or Word file, replaced by each new upload. */
function WriteupUpload({ challenge }) {
    const { team, game } = usePage().props;
    const inputId = `writeup-${challenge.id}`;
    // The name we ask for, e.g. "Bravo-Sticky_Note", so the instructor can tell files apart.
    const suggestedName = `${team?.name ?? 'team_name'}-${challenge.title}`
        .trim()
        .replace(/\s+/g, '_');

    return (
        <Form
            {...writeup.form(challenge.id)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            disableWhileProcessing
            className="grid gap-2 border-t pt-4"
        >
            {({ processing, errors }) => (
                <>
                    <Label htmlFor={inputId}>Write-up</Label>
                    {game?.writeup_template_url && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="self-start"
                            asChild
                        >
                            <a href={game.writeup_template_url} download>
                                <Download />
                                Download the template
                            </a>
                        </Button>
                    )}
                    {challenge.writeup && (
                        <p className="text-sm text-muted-foreground">
                            Your team uploaded{' '}
                            <a
                                href={challenge.writeup.url}
                                className="font-medium break-all text-foreground underline underline-offset-4"
                            >
                                {challenge.writeup.name}
                            </a>
                            . A new upload replaces it.
                        </p>
                    )}
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            id={inputId}
                            type="file"
                            name="writeup"
                            accept=".pdf,.docx"
                            required
                            aria-invalid={errors.writeup ? true : undefined}
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            {processing ? <Spinner /> : <Upload />}
                            Upload
                        </Button>
                    </div>
                    <InputError message={errors.writeup} />
                    <p className="text-xs text-muted-foreground">
                        Name the file{' '}
                        <code className="rounded bg-muted px-1 py-0.5 font-mono break-all text-foreground">
                            {suggestedName}.pdf
                        </code>{' '}
                        (team name, a dash, then the challenge title). PDF or
                        Word (.docx), up to 10 MB. Only your team and your
                        instructor can open it.
                    </p>
                </>
            )}
        </Form>
    );
}

function HintDialog({ challenge }) {
    const pointsWithHint = challenge.points - challenge.hint_cost;

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="-ml-2 self-start">
                    <Lightbulb />
                    Show hint (−{challenge.hint_cost} pts)
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="mb-2 flex size-10 items-center justify-center rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400">
                        <Lightbulb className="size-5" />
                    </div>
                    <DialogTitle>
                        Open the hint for “{challenge.title}”?
                    </DialogTitle>
                    <DialogDescription>
                        A hint costs half the challenge's points. Once opened,
                        it stays open for your whole team and the cost cannot be
                        undone.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-center justify-center gap-4 rounded-lg border bg-muted/40 p-4">
                    <div className="text-center">
                        <p className="text-xs text-muted-foreground">
                            Without hint
                        </p>
                        <p className="text-2xl font-semibold tabular-nums">
                            {challenge.points}
                        </p>
                    </div>
                    <ArrowRight
                        className="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div className="text-center">
                        <p className="text-xs text-muted-foreground">
                            With hint
                        </p>
                        <p className="text-2xl font-semibold text-amber-600 tabular-nums dark:text-amber-400">
                            {pointsWithHint}
                        </p>
                    </div>
                    <span className="sr-only">
                        Solving it after opening the hint earns {pointsWithHint}{' '}
                        instead of {challenge.points} points.
                    </span>
                </div>

                <Form
                    {...hint.form(challenge.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Keep trying</Button>
                            </DialogClose>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Open hint (−{challenge.hint_cost} pts)
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Not clickable until the team solves the warm-up for this category. */
function LockedTile({ challenge }) {
    return (
        <div
            className={cn(
                tileWidth,
                'relative flex cursor-not-allowed flex-col items-center gap-3 rounded-xl border border-dashed bg-muted/40 p-5 text-center text-muted-foreground',
            )}
            title={`Solve the “${challenge.locked_by}” warm-up to unlock.`}
        >
            <Lock
                className="absolute top-3 right-3 size-4"
                aria-hidden="true"
            />
            <Crosshair className="size-8 opacity-60" aria-hidden="true" />
            <span className="flex min-h-10 items-center text-base leading-tight font-semibold">
                {challenge.title}
            </span>
            <span className="h-px w-3/4 bg-border" aria-hidden="true" />
            <span className="font-mono text-lg font-semibold tabular-nums">
                {challenge.points}
                <span className="sr-only">
                    {' '}
                    points, locked until the “{challenge.locked_by}” warm-up is
                    solved
                </span>
            </span>
        </div>
    );
}

function FreeHint({ hint }) {
    const [isOpen, setIsOpen] = useState(false);

    return isOpen ? (
        <p className="rounded-md bg-muted p-3 text-sm">
            <span className="font-medium">Hint: </span>
            {hint}
        </p>
    ) : (
        <Button
            variant="ghost"
            size="sm"
            className="-ml-2 self-start"
            onClick={() => setIsOpen(true)}
        >
            <Lightbulb />
            Show hint (free)
        </Button>
    );
}
