import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

// Ten distinct line colors, readable on both the light and dark card.
const lineColors = [
    '#f4b400',
    '#ef4444',
    '#22c55e',
    '#3b82f6',
    '#a855f7',
    '#06b6d4',
    '#f97316',
    '#ec4899',
    '#84cc16',
    '#6366f1',
];

const formatTime = (ms) =>
    new Date(ms).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

/** Step lines of each top team's score over time. progression: [{ id, name, steps: [{ t, points }] }]. */
export default function ScoreProgressionChart({ progression }) {
    if (progression.length === 0) {
        return (
            <p className="py-12 text-center text-sm text-muted-foreground">
                No points scored yet. The chart starts with the first solve.
            </p>
        );
    }

    const leader = progression[0];

    return (
        <figure>
            <figcaption className="mb-2 text-center text-sm font-semibold text-muted-foreground">
                Top {progression.length} teams
            </figcaption>
            {/* The leaderboard below is the accessible version of this chart. */}
            <p className="sr-only">
                Score over time for the top {progression.length} teams.{' '}
                {leader.name} leads with{' '}
                {leader.steps[leader.steps.length - 1].points} points.
            </p>
            <div className="h-80 w-full" aria-hidden="true">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        margin={{ top: 8, right: 16, bottom: 0, left: 0 }}
                    >
                        <CartesianGrid
                            stroke="var(--border)"
                            vertical={false}
                        />
                        <XAxis
                            dataKey="t"
                            type="number"
                            scale="time"
                            domain={['dataMin', 'dataMax']}
                            tickFormatter={formatTime}
                            stroke="var(--muted-foreground)"
                            fontSize={12}
                            allowDuplicatedCategory={false}
                        />
                        <YAxis
                            stroke="var(--muted-foreground)"
                            fontSize={12}
                            width={48}
                            allowDecimals={false}
                        />
                        <Tooltip
                            labelFormatter={formatTime}
                            contentStyle={{
                                background: 'var(--popover)',
                                border: '1px solid var(--border)',
                                borderRadius: 8,
                                color: 'var(--popover-foreground)',
                            }}
                        />
                        <Legend wrapperStyle={{ fontSize: 12 }} />
                        {progression.map((team, index) => (
                            <Line
                                key={index}
                                data={team.steps}
                                dataKey="points"
                                name={team.name}
                                type="stepAfter"
                                stroke={lineColors[index % lineColors.length]}
                                strokeWidth={2}
                                dot={{ r: 2 }}
                                activeDot={{ r: 4 }}
                                isAnimationActive={false}
                            />
                        ))}
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </figure>
    );
}
