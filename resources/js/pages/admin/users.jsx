import { Form, Head, usePage } from '@inertiajs/react';
import { Check, Copy, KeyRound, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import FilterSelect from '@/components/filter-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchInput from '@/components/search-input';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTableControls } from '@/hooks/use-table-controls';

// The first filter is the default.
const userFilters = {
    all: { label: 'Everyone', test: () => true },
    students: { label: 'Students', test: (user) => !user.is_admin },
    admins: { label: 'Admins', test: (user) => user.is_admin },
    noTeam: {
        label: 'Students without a team',
        test: (user) => !user.is_admin && !user.team,
    },
};

export default function AdminUsers({ users }) {
    const page = usePage();
    const { auth } = page.props;
    const credentials = page.flash?.credentials;
    const table = useTableControls(users, {
        searchFields: ['name', 'email', 'team'],
        filters: userFilters,
    });

    return (
        <>
            <Head title="Admin · Students" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Students"
                        description="Registration is closed, so every account is added here."
                    />
                    <AddStudentsDialog />
                </div>

                {credentials?.length > 0 && (
                    <NewCredentials credentials={credentials} />
                )}

                <div className="flex flex-col gap-2 sm:flex-row">
                    <SearchInput
                        id="search-users"
                        label="Search by name, email or team"
                        value={table.query}
                        onChange={(event) => table.setQuery(event.target.value)}
                        className="flex-1"
                    />
                    <FilterSelect
                        id="filter-users"
                        label="Show"
                        value={table.filter}
                        onChange={table.setFilter}
                        options={table.filterOptions}
                        className="sm:w-56"
                    />
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Accounts</caption>
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-3 py-2 font-medium"
                                >
                                    Name
                                </th>
                                <th
                                    scope="col"
                                    className="px-3 py-2 font-medium"
                                >
                                    Email
                                </th>
                                <th
                                    scope="col"
                                    className="px-3 py-2 font-medium"
                                >
                                    Team
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
                                        colSpan={4}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No accounts match.
                                    </td>
                                </tr>
                            )}
                            {table.pageItems.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="px-3 py-2 font-medium break-words">
                                        {user.name}{' '}
                                        {user.is_admin && (
                                            <Badge variant="secondary">
                                                Admin
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 break-all">
                                        {user.email}
                                    </td>
                                    <td className="px-3 py-2">
                                        {user.team ?? (
                                            <span className="text-muted-foreground">
                                                {user.is_admin
                                                    ? '-'
                                                    : 'No team'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex justify-end gap-1">
                                            <ResetPassword user={user} />
                                            {user.id !== auth.user.id && (
                                                <DeleteUser user={user} />
                                            )}
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
            </div>
        </>
    );
}

/** One button; the dialog switches between adding one student and pasting a class list. */
function AddStudentsDialog() {
    const [isOpen, setIsOpen] = useState(false);
    const [mode, setMode] = useState('one');
    const close = () => setIsOpen(false);

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button className="self-start">
                    <UserPlus />
                    Add students
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add students</DialogTitle>
                    <DialogDescription>
                        {mode === 'one'
                            ? 'You choose the password and give it to the student.'
                            : 'One student per line as "Name, email". Passwords are generated and shown once.'}
                    </DialogDescription>
                </DialogHeader>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={mode}
                    // Clicking the active option would clear the choice; keep one selected.
                    onValueChange={(value) => value && setMode(value)}
                    className="self-start"
                    aria-label="How to add"
                >
                    <ToggleGroupItem value="one" className="px-4">
                        One student
                    </ToggleGroupItem>
                    <ToggleGroupItem value="many" className="px-4">
                        Class list
                    </ToggleGroupItem>
                </ToggleGroup>
                {mode === 'one' ? (
                    <AddOneStudent onAdded={close} />
                ) : (
                    <AddManyStudents onAdded={close} />
                )}
            </DialogContent>
        </Dialog>
    );
}

function AddOneStudent({ onAdded }) {
    return (
        <Form
            {...UserController.store.form()}
            options={{ preserveScroll: true }}
            onSuccess={onAdded}
            className="grid gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            maxLength={255}
                            autoComplete="off"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            required
                            maxLength={255}
                            autoComplete="off"
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            name="password"
                            type="text"
                            required
                            autoComplete="new-password"
                            spellCheck={false}
                        />
                        <InputError message={errors.password} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Add student
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

function AddManyStudents({ onAdded }) {
    return (
        <Form
            {...UserController.storeMany.form()}
            options={{ preserveScroll: true }}
            onSuccess={onAdded}
            className="grid gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="students">Students</Label>
                        <Textarea
                            id="students"
                            name="students"
                            required
                            rows={8}
                            spellCheck={false}
                            placeholder={
                                'Joe Christian, joechristian@school.example'
                            }
                            aria-invalid={errors.students ? true : undefined}
                        />
                        <InputError message={errors.students} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Add students
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

function NewCredentials({ credentials }) {
    const [copiedText, copy] = useClipboard();
    const asText = credentials
        .map(({ name, email, password }) => `${name}\t${email}\t${password}`)
        .join('\n');

    return (
        <section
            aria-labelledby="credentials-heading"
            role="status"
            className="rounded-xl border border-amber-500/50 bg-amber-50 p-4 dark:bg-amber-500/10"
        >
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 id="credentials-heading" className="font-semibold">
                    New passwords: copy them now, they are not shown again
                </h2>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => copy(asText)}
                >
                    {copiedText === asText ? <Check /> : <Copy />}
                    {copiedText === asText ? 'Copied' : 'Copy all'}
                </Button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <caption className="sr-only">New login details</caption>
                    <thead className="text-left text-muted-foreground">
                        <tr>
                            <th scope="col" className="py-1 pr-4 font-medium">
                                Name
                            </th>
                            <th scope="col" className="py-1 pr-4 font-medium">
                                Email
                            </th>
                            <th scope="col" className="py-1 font-medium">
                                Password
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {credentials.map((credential) => (
                            <tr key={credential.email}>
                                <td className="py-1 pr-4">{credential.name}</td>
                                <td className="py-1 pr-4 break-all">
                                    {credential.email}
                                </td>
                                <td className="py-1 font-mono">
                                    {credential.password}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function ResetPassword({ user }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon">
                    <KeyRound />
                    <span className="sr-only">
                        Reset password for {user.name}
                    </span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Reset {user.name}'s password?</DialogTitle>
                <DialogDescription>
                    Their current password stops working. A new one is generated
                    and shown to you once.
                </DialogDescription>
                <Form
                    {...UserController.resetPassword.form(user.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setIsOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button type="submit" disabled={processing}>
                                Reset password
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteUser({ user }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon">
                    <Trash2 />
                    <span className="sr-only">Delete {user.name}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {user.name}?</DialogTitle>
                <DialogDescription>
                    This deletes their account. Their team keeps everything it
                    solved and can keep playing. It cannot be undone.
                </DialogDescription>
                <Form
                    {...UserController.destroy.form(user.id)}
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
                                Delete
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

AdminUsers.layout = {
    breadcrumbs: [
        {
            title: 'Students',
            href: UserController.index(),
        },
    ],
};
