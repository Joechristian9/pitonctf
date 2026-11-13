import { Head, usePoll } from '@inertiajs/react';
import { Lock, Sparkles, Swords } from 'lucide-react';
import ChallengeCard from '@/components/challenge-card';
import GameStatusBadge from '@/components/game-status-badge';
import Heading from '@/components/heading';
import { challenges as challengesRoute } from '@/routes';

const statusMessages = {
    locked: 'The game is locked. You can look around, but flags can no longer be submitted.',
    draft: "The game hasn't started yet. The challenges appear here once your instructor starts it.",
};

export default function Challenges({ game, team, categories }) {
    // Live: a teammate's solve, an unlocked category or the game starting or locking shows up within 5 s.
    usePoll(5000, { only: ['game', 'team', 'categories'] });
    const isPlayable = game.status === 'running';
    const hasChallenges = Object.keys(categories).length > 0;

    return (
        <>
            <Head title={game.name} />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <Heading
                    title={game.name}
                    description={
                        game.description ??
                        'Find the hidden flag in each challenge and submit it below.'
                    }
                />

                <TeamBanner team={team} />

                {!isPlayable && (
                    <p className="flex items-center gap-2 rounded-xl border p-4 text-sm">
                        <GameStatusBadge status={game.status} />
                        {game.status === 'locked'
                            ? statusMessages.locked
                            : statusMessages.draft}
                    </p>
                )}

                {!hasChallenges && game.status !== 'draft' && (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        This game has no challenges yet.
                    </p>
                )}

                {Object.entries(categories).map(([category, items]) => (
                    <section
                        key={category}
                        aria-labelledby={`category-${category}`}
                    >
                        <CategoryHeading category={category} items={items} />
                        <div className="flex flex-wrap justify-center gap-4">
                            {items.map((challenge) => (
                                <ChallengeCard
                                    key={challenge.id}
                                    challenge={challenge}
                                    isPlayable={isPlayable}
                                />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

function TeamBanner({ team }) {
    if (!team) {
        return (
            <p className="rounded-xl border border-dashed p-4 text-sm">
                You are not in a team yet. Ask your instructor to add you to
                one.
            </p>
        );
    }

    return (
        <p className="flex items-center justify-center gap-3 rounded-xl border p-5 text-xl font-bold tracking-tight">
            <Swords className="size-6 text-primary" />
            {team.name}
        </p>
    );
}

function CategoryHeading({ category, items }) {
    const isWarmUp = items.some((challenge) => challenge.unlocks_category);
    const lockedBy = items.find((challenge) => challenge.locked_by)?.locked_by;

    return (
        <div className="mb-5 rounded-xl border border-primary/40 bg-card px-6 py-5 text-center shadow-sm">
            <p className="flex items-center justify-center gap-3 text-xs font-semibold tracking-[0.3em] text-primary uppercase">
                <span className="h-px w-10 bg-primary/50" aria-hidden="true" />
                {isWarmUp ? 'Start here' : 'Category'}
                <span className="h-px w-10 bg-primary/50" aria-hidden="true" />
            </p>
            <h2
                id={`category-${category}`}
                className="mt-2 flex items-center justify-center gap-2 text-2xl font-bold tracking-tight sm:text-3xl"
            >
                {isWarmUp && <Sparkles className="size-6 text-primary" />}
                {lockedBy && <Lock className="size-5 text-muted-foreground" />}
                {isWarmUp ? 'Warm-ups' : category}
            </h2>
            {isWarmUp && (
                <p className="mt-1 text-sm text-muted-foreground">
                    No points here. Each one you solve unlocks a category of
                    real challenges.
                </p>
            )}
            {lockedBy && (
                <p className="mt-1 text-sm text-muted-foreground">
                    Locked until your team solves the “{lockedBy}” warm-up.
                </p>
            )}
        </div>
    );
}

Challenges.layout = {
    breadcrumbs: [
        {
            title: 'Challenges',
            href: challengesRoute(),
        },
    ],
};
