import { Form, Head, Link, usePoll } from '@inertiajs/react';
import {
    Download,
    Lock,
    Pencil,
    Play,
    Plus,
    RotateCcw,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import ChallengeController from '@/actions/App/Http/Controllers/Admin/ChallengeController';
import GameController from '@/actions/App/Http/Controllers/Admin/GameController';
import FilterSelect from '@/components/filter-select';
import GameStatusBadge from '@/components/game-status-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchInput from '@/components/search-input';
import TablePagination from '@/components/table-pagination';
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
import { Textarea } from '@/components/ui/textarea';
import { useTableControls } from '@/hooks/use-table-controls';
import { challenges as challengesRoute } from '@/routes';

function StatusButton({ status, children }) {
    return (
        <Form
            {...GameController.updateStatus.form()}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <>
                    <input type="hidden" name="status" value={status} />
                    <Button type="submit" disabled={processing}>
                        {children}
                    </Button>
                </>
            )}
        </Form>
    );
}

/** Wipes every team's progress after a confirmation, so the class can play again from zero. */
function ResetScores() {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <RotateCcw />
                    Reset scores
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Reset all scores?</DialogTitle>
                <DialogDescription>
                    Every team loses its points, solved challenges, used tries
                    and opened hints. Warm-ups lock their categories again, so
                    everyone starts over. Teams, students and challenges are
                    kept. This cannot be undone.
                </DialogDescription>
                <Form
                    {...GameController.resetScores.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setIsOpen(false)}
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
                                Reset scores
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminGame({ game, challenges }) {
    // Live solve and write-up counts, plus status changes made from another admin's tab.
    usePoll(5000, { only: ['game', 'challenges'] });
    // One filter per category the game has; "All" first, so it is the default.
    const categoryFilters = Object.fromEntries([
        ['all', { label: 'All categories', test: () => true }],
        ...[...new Set(challenges.map((challenge) => challenge.category))].map(
            (category) => [
                category,
                {
                    label: category,
                    test: (challenge) => challenge.category === category,
                },
            ],
        ),
    ]);
    const table = useTableControls(challenges, {
        searchFields: ['number', 'title', 'category'],
        filters: categoryFilters,
    });

    return (
        <>
            <Head title="Challenges" />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Heading title={game.name} />
                        <div className="mb-8">
                            <GameStatusBadge status={game.status} />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {game.status === 'running' ? (
                            <StatusButton status="locked">
                                <Lock />
                                Lock game
                            </StatusButton>
                        ) : (
                            <StatusButton status="running">
                                <Play />
                                {game.status === 'locked'
                                    ? 'Reopen game'
                                    : 'Start game'}
                            </StatusButton>
                        )}
                        <ResetScores />
                    </div>
                </div>

                <section aria-labelledby="challenges-heading">
                    <div className="mb-4 flex items-center justify-between gap-4">
                        <h2
                            id="challenges-heading"
                            className="text-lg font-semibold"
                        >
                            Challenges
                        </h2>
                        <Button size="sm" asChild>
                            <Link href={ChallengeController.create()}>
                                <Plus />
                                New challenge
                            </Link>
                        </Button>
                    </div>

                    <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                        <SearchInput
                            id="search-challenges"
                            label="Search by number, title or category"
                            value={table.query}
                            onChange={(event) =>
                                table.setQuery(event.target.value)
                            }
                            className="flex-1"
                        />
                        <FilterSelect
                            id="filter-challenges"
                            label="Category"
                            value={table.filter}
                            onChange={table.setFilter}
                            options={table.filterOptions}
                            className="sm:w-56"
                        />
                    </div>

                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                Challenges in {game.name}
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
                                        Title
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 font-medium"
                                    >
                                        Category
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right font-medium"
                                    >
                                        Points
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right font-medium"
                                    >
                                        Files
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right font-medium"
                                    >
                                        Solves
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right font-medium"
                                    >
                                        Write-ups
                                    </th>
                                    <th scope="col" className="px-3 py-2">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {table.pageItems.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            {challenges.length === 0
                                                ? 'No challenges yet.'
                                                : 'No challenges match.'}
                                        </td>
                                    </tr>
                                )}
                                {table.pageItems.map((challenge) => (
                                    <tr key={challenge.id} className="border-t">
                                        <td className="px-3 py-2">
                                            {challenge.number}
                                        </td>
                                        <td className="px-3 py-2 font-medium">
                                            {challenge.title}
                                            {challenge.unlocks_category && (
                                                <span className="block text-xs font-normal text-muted-foreground">
                                                    Warm-up: unlocks{' '}
                                                    {challenge.unlocks_category}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {challenge.category}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {challenge.points}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {challenge.files}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {challenge.solves}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            <Writeups challenge={challenge} />
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={ChallengeController.edit(
                                                            challenge.id,
                                                        )}
                                                    >
                                                        <Pencil />
                                                        <span className="sr-only">
                                                            Edit{' '}
                                                            {challenge.title}
                                                        </span>
                                                    </Link>
                                                </Button>
                                                <DeleteChallenge
                                                    challenge={challenge}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <TablePagination
                        page={table.page}
                        pageCount={table.pageCount}
                        perPage={table.perPage}
                        filteredCount={table.filteredCount}
                        onPageChange={table.setPage}
                    />
                </section>

                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Game details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...GameController.update.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                        >
                            {({ processing, errors, recentlySuccessful }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={game.name}
                                            required
                                            maxLength={100}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Description (optional)
                                        </Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            defaultValue={
                                                game.description ?? ''
                                            }
                                            maxLength={2000}
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>
                                        {recentlySuccessful && (
                                            <p className="text-sm text-muted-foreground">
                                                Saved.
                                            </p>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <WriteupTemplate game={game} />
            </div>
        </>
    );
}

/** One template every team can download from the write-up box of each challenge. */
function WriteupTemplate({ game }) {
    return (
        <Card className="max-w-xl">
            <CardHeader className="flex-row items-center justify-between gap-4">
                <CardTitle className="text-base">Write-up template</CardTitle>
                {game.writeup_template_url && (
                    <Form
                        {...GameController.removeWriteupTemplate.form()}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                disabled={processing}
                            >
                                <Trash2 />
                                Remove
                            </Button>
                        )}
                    </Form>
                )}
            </CardHeader>
            <CardContent>
                <Form
                    {...GameController.uploadWriteupTemplate.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {game.writeup_template_url ? (
                                    <>
                                        Teams can download{' '}
                                        <a
                                            href={game.writeup_template_url}
                                            className="font-medium text-foreground underline underline-offset-4"
                                            download
                                        >
                                            the current template
                                        </a>
                                        . A new upload replaces it.
                                    </>
                                ) : (
                                    'No template yet. Teams see a download link once you upload one.'
                                )}
                            </p>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Label
                                    htmlFor="writeup-template"
                                    className="sr-only"
                                >
                                    Template file
                                </Label>
                                <Input
                                    id="writeup-template"
                                    type="file"
                                    name="template"
                                    accept=".pdf,.docx"
                                    required
                                    aria-invalid={
                                        errors.template ? true : undefined
                                    }
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    <Upload />
                                    Upload
                                </Button>
                            </div>
                            <InputError message={errors.template} />
                            <p className="text-xs text-muted-foreground">
                                PDF or Word (.docx), up to 10 MB.
                            </p>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

/** The count of team write-ups; opens a list to download each one. */
function Writeups({ challenge }) {
    if (challenge.writeups.length === 0) {
        return 0;
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="link" size="sm" className="h-auto p-0">
                    {challenge.writeups.length}
                    <span className="sr-only">
                        write-ups for {challenge.title}
                    </span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Write-ups: {challenge.title}</DialogTitle>
                <DialogDescription>
                    One file per team, PDF or Word.
                </DialogDescription>
                <ul className="grid gap-2">
                    {challenge.writeups.map((writeup) => (
                        <li
                            key={writeup.url}
                            className="flex items-center justify-between gap-4 rounded-md border px-3 py-2"
                        >
                            <span className="font-medium">{writeup.team}</span>
                            <Button variant="outline" size="sm" asChild>
                                <a href={writeup.url}>
                                    <Download />
                                    Download
                                </a>
                            </Button>
                        </li>
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}

function DeleteChallenge({ challenge }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon">
                    <Trash2 />
                    <span className="sr-only">Delete {challenge.title}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{challenge.title}”?</DialogTitle>
                <DialogDescription>
                    This also deletes its {challenge.solves} solves and every
                    student's tries on it. Scores drop accordingly. This cannot
                    be undone.
                </DialogDescription>
                <Form
                    {...ChallengeController.destroy.form(challenge.id)}
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
                                Delete challenge
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

AdminGame.layout = {
    breadcrumbs: [
        {
            title: 'Challenges',
            href: challengesRoute(),
        },
    ],
};
