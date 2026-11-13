import { Head, usePage, usePoll } from '@inertiajs/react';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';
import { scoreboard as scoreboardRoute } from '@/routes';

export default function Scoreboard({ game, scoreboard }) {
    const { auth } = usePage().props;

    // Only the scoreboard prop is reloaded, so the rest of the page stays put.
    usePoll(15000, { only: ['scoreboard'] });

    return (
        <>
            <Head title={`${game.name} scoreboard`} />
            <div className="flex flex-1 flex-col p-4">
                <Heading
                    title={`${game.name} scoreboard`}
                    description="Updates every 15 seconds. Ties go to whoever reached the score first."
                />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            Ranking of all teams
                        </caption>
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    className="w-12 px-3 py-2 font-medium"
                                >
                                    #
                                </th>
                                <th
                                    scope="col"
                                    className="px-3 py-2 font-medium"
                                >
                                    Team
                                </th>
                                <th
                                    scope="col"
                                    className="px-3 py-2 text-right font-medium"
                                >
                                    Solved
                                </th>
                                <th
                                    scope="col"
                                    className="px-3 py-2 text-right font-medium"
                                >
                                    Points
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {scoreboard.map((player, index) => {
                                const isMe = player.id === auth.user.team_id;

                                return (
                                    <tr
                                        key={player.id}
                                        aria-current={isMe ? 'true' : undefined}
                                        className={cn(
                                            'border-t',
                                            isMe &&
                                                'bg-primary/10 font-semibold',
                                        )}
                                    >
                                        <td className="px-3 py-2">
                                            {index + 1}
                                        </td>
                                        <td className="px-3 py-2 break-words">
                                            {player.name}
                                            {isMe && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    (your team)
                                                </span>
                                            )}
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                {player.members.join(', ')}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {player.solved}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {player.points}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
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
