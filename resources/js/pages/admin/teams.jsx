import { Form, Head } from '@inertiajs/react';
import { Trash2, UserMinus, UserPlus } from 'lucide-react';
import { useState } from 'react';
import TeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchInput from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function AdminTeams({ teams, unassigned, teamSize }) {
    return (
        <>
            <Head title="Admin · Teams" />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <Heading
                    title="Teams"
                    description={`Teams of up to ${teamSize} students play the game together. A team can play as soon as it has a member.`}
                />

                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle className="text-base">New team</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...TeamController.store.form()}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="flex flex-col gap-2 sm:flex-row sm:items-start"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <Label
                                            htmlFor="team-name"
                                            className="sr-only"
                                        >
                                            Team name
                                        </Label>
                                        <Input
                                            id="team-name"
                                            name="name"
                                            required
                                            maxLength={100}
                                            placeholder="Team name, e.g. Byte Busters"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Create team
                                    </Button>
                                </>
                            )}
                        </Form>
                        <p className="mt-3 text-xs text-muted-foreground">
                            {unassigned.length} student
                            {unassigned.length === 1 ? '' : 's'} without a team.
                        </p>
                    </CardContent>
                </Card>

                {teams.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No teams yet. Create the first one above.
                    </p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {teams.map((team) => (
                            <TeamCard
                                key={team.id}
                                team={team}
                                unassigned={unassigned}
                                teamSize={teamSize}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function TeamCard({ team, unassigned, teamSize }) {
    const isFull = team.members.length >= teamSize;

    return (
        <Card className="gap-4">
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <CardTitle className="text-base break-words">
                        {team.name}
                    </CardTitle>
                    <Badge variant={isFull ? 'default' : 'secondary'}>
                        {team.members.length}/{teamSize}
                        {isFull ? ' · full' : ''}
                    </Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                    {team.points} points
                </p>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {team.members.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No members yet.
                    </p>
                ) : (
                    <ul className="grid gap-1">
                        {team.members.map((member) => (
                            <li
                                key={member.id}
                                className="flex items-center justify-between gap-2 text-sm"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">
                                        {member.name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {member.email}
                                    </span>
                                </span>
                                <Form
                                    {...TeamController.removeMember.form({
                                        team: team.id,
                                        user: member.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="icon"
                                            disabled={processing}
                                        >
                                            <UserMinus />
                                            <span className="sr-only">
                                                Remove {member.name} from{' '}
                                                {team.name}
                                            </span>
                                        </Button>
                                    )}
                                </Form>
                            </li>
                        ))}
                    </ul>
                )}

                <Codename team={team} />

                {!isFull && <AddMember team={team} unassigned={unassigned} />}

                <DeleteTeam team={team} />
            </CardContent>
        </Card>
    );
}

/** The name students see on the scoreboard instead of the real team name. */
function Codename({ team }) {
    const inputId = `codename-${team.id}`;

    return (
        <Form
            {...TeamController.updateCodename.form(team.id)}
            options={{ preserveScroll: true }}
            className="grid gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <Label htmlFor={inputId}>Scoreboard codename</Label>
                    <div className="flex gap-2">
                        <Input
                            id={inputId}
                            name="codename"
                            defaultValue={team.codename ?? ''}
                            maxLength={100}
                            placeholder="e.g. Red Falcon"
                            aria-invalid={errors.codename ? true : undefined}
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            Save
                        </Button>
                    </div>
                    <InputError message={errors.codename} />
                    {!team.codename && (
                        <p className="text-xs text-muted-foreground">
                            No codename: students see the real team name on the
                            scoreboard.
                        </p>
                    )}
                </>
            )}
        </Form>
    );
}

function AddMember({ team, unassigned }) {
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();
    const matches = unassigned.filter(
        (student) =>
            student.name.toLowerCase().includes(needle) ||
            student.email.toLowerCase().includes(needle),
    );
    const searchId = `search-member-${team.id}`;
    const listId = `add-member-${team.id}`;

    if (unassigned.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Every student already has a team.
            </p>
        );
    }

    return (
        <Form
            {...TeamController.addMember.form(team.id)}
            options={{ preserveScroll: true }}
            onSuccess={() => setQuery('')}
            className="grid gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <p className="text-sm font-medium">Add a member</p>
                    <SearchInput
                        id={searchId}
                        label={`Search students to add to ${team.name}`}
                        placeholder="Search by name or email"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        aria-controls={listId}
                    />
                    {matches.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No student matches “{query}”.
                        </p>
                    ) : (
                        <>
                            <label htmlFor={listId} className="sr-only">
                                Student to add to {team.name}
                            </label>
                            {/* A native list box: reliable inside cards, searchable, and keyboard friendly. */}
                            <select
                                id={listId}
                                name="user_id"
                                required
                                size={Math.min(5, Math.max(2, matches.length))}
                                className="w-full rounded-md border border-input bg-transparent p-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 [&>option]:rounded [&>option]:px-2 [&>option]:py-1.5 [&>option:checked]:bg-primary [&>option:checked]:text-primary-foreground"
                            >
                                {matches.map((student) => (
                                    <option key={student.id} value={student.id}>
                                        {student.name} · {student.email}
                                    </option>
                                ))}
                            </select>
                        </>
                    )}
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={processing || matches.length === 0}
                        className="justify-self-start"
                    >
                        <UserPlus />
                        Add to team
                    </Button>
                    <InputError message={errors.user_id} />
                </>
            )}
        </Form>
    );
}

function DeleteTeam({ team }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-2 self-start text-destructive"
                >
                    <Trash2 />
                    Delete team
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{team.name}”?</DialogTitle>
                <DialogDescription>
                    Its {team.points} points, solves and tries are deleted. The
                    members stay as students without a team. This cannot be
                    undone.
                </DialogDescription>
                <Form
                    {...TeamController.destroy.form(team.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Delete team
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

AdminTeams.layout = {
    breadcrumbs: [
        {
            title: 'Teams',
            href: TeamController.index(),
        },
    ],
};
