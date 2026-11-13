import { Head, usePage, usePoll } from '@inertiajs/react';
import { ChartLine, Trophy } from 'lucide-react';
import ScoreProgressionChart from '@/components/score-progression-chart';
import { cn } from '@/lib/utils';
import { scoreboard as scoreboardRoute } from '@/routes';

export default function Scoreboard({ scoreboard, progression }) {
    const { auth } = usePage().props;

    // Only the live data is reloaded, so the rest of the page stays put.
    usePoll(15000, { only: ['scoreboard', 'progression'] });

    return (
        <>
            <Head title="Scoreboard" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <header className="text-center">
                    <h1 className="flex items-center justify-center gap-4 text-3xl font-black tracking-tight uppercase sm:text-5xl">
                        <span
                            className="hidden h-px w-16 bg-primary/60 sm:block"
                            aria-hidden="true"
                        />
                        Scoreboard
                        <span
                            className="hidden h-px w-16 bg-primary/60 sm:block"
                            aria-hidden="true"
                        />
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Updates every 15 seconds. Ties go to whoever reached the
                        score first.
                    </p>
                </header>

                <Panel icon={ChartLine} title="Score progression">
                    <ScoreProgressionChart progression={progression} />
                </Panel>

                <Panel icon={Trophy} title="Leaderboard">
                    {scoreboard.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No teams yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    Ranking of all teams
                                </caption>
                                <thead className="text-left text-muted-foreground">
                                    <tr className="border-b">
                                        <th
                                            scope="col"
                                            className="w-20 px-3 py-3 font-semibold"
                                        >
                                            Place
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-3 font-semibold"
                                        >
                                            Team
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-3 text-right font-semibold"
                                        >
                                            Score
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {scoreboard.map((team, index) => {
                                        const isMine =
                                            team.id === auth.user.team_id;

                                        return (
                                            <tr
                                                key={team.id}
                                                aria-current={
                                                    isMine ? 'true' : undefined
                                                }
                                                className={cn(
                                                    'border-b transition-colors last:border-0 hover:bg-muted/40',
                                                    isMine && 'bg-primary/10',
                                                )}
                                            >
                                                <td className="px-3 py-3">
                                                    <span
                                                        className={cn(
                                                            'flex size-9 items-center justify-center rounded-md border-2 font-mono font-bold tabular-nums',
                                                            index < 3
                                                                ? 'border-primary text-primary'
                                                                : 'border-border',
                                                        )}
                                                    >
                                                        {index + 1}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <span className="font-semibold break-words">
                                                        {team.name}
                                                    </span>
                                                    {isMine && (
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            (your team)
                                                        </span>
                                                    )}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {team.members.join(
                                                            ', ',
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono text-base font-bold tabular-nums">
                                                    {team.points}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </div>
        </>
    );
}

function Panel({ icon: Icon, title, children }) {
    return (
        <section
            aria-label={title}
            className="rounded-xl border border-primary/40 bg-card p-4 shadow-sm sm:p-6"
        >
            <h2 className="mb-4 flex items-center gap-2 text-sm font-bold tracking-[0.2em] text-primary uppercase">
                <Icon className="size-4" aria-hidden="true" />
                {title}
            </h2>
            {children}
        </section>
    );
}

Scoreboard.layout = {
    breadcrumbs: [
        {
            title: 'Scoreboard',
            href: scoreboardRoute(),
        },
    ],
};
