import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import ChallengeController from '@/actions/App/Http/Controllers/Admin/ChallengeController';
import GameController from '@/actions/App/Http/Controllers/Admin/GameController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

function Field({ id, label, error, children }) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

export default function ChallengeForm({ challenge }) {
    const isEditing = challenge !== null;
    const title = isEditing ? `Edit ${challenge.title}` : 'New challenge';

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin', href: GameController.show() },
            {
                title,
                href: isEditing
                    ? ChallengeController.edit(challenge.id)
                    : ChallengeController.create(),
            },
        ],
    });

    const action = isEditing
        ? ChallengeController.update.form(challenge.id)
        : ChallengeController.store.form();

    return (
        <>
            <Head title={`Admin · ${title}`} />
            <div className="flex max-w-2xl flex-1 flex-col p-4">
                <Heading title={title} />

                <Form {...action} className="grid gap-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6 sm:grid-cols-[1fr_8rem_6rem]">
                                <Field
                                    id="title"
                                    label="Title"
                                    error={errors.title}
                                >
                                    <Input
                                        id="title"
                                        name="title"
                                        defaultValue={challenge?.title}
                                        required
                                        maxLength={255}
                                    />
                                </Field>
                                <Field
                                    id="category"
                                    label="Category"
                                    error={errors.category}
                                >
                                    <Input
                                        id="category"
                                        name="category"
                                        defaultValue={challenge?.category}
                                        required
                                        maxLength={50}
                                        list="categories"
                                    />
                                    <datalist id="categories">
                                        <option value="Crypto" />
                                        <option value="Web" />
                                        <option value="Forensics" />
                                    </datalist>
                                </Field>
                                <Field
                                    id="points"
                                    label="Points"
                                    error={errors.points}
                                >
                                    <Input
                                        id="points"
                                        name="points"
                                        type="number"
                                        min={1}
                                        max={1000}
                                        defaultValue={challenge?.points ?? 100}
                                        required
                                    />
                                </Field>
                            </div>

                            <Field
                                id="description"
                                label="Description"
                                error={errors.description}
                            >
                                <Textarea
                                    id="description"
                                    name="description"
                                    defaultValue={challenge?.description}
                                    required
                                    maxLength={5000}
                                    rows={4}
                                />
                            </Field>

                            <Field
                                id="hint"
                                label="Hint (optional, costs half the points to open)"
                                error={errors.hint}
                            >
                                <Textarea
                                    id="hint"
                                    name="hint"
                                    defaultValue={challenge?.hint ?? ''}
                                    maxLength={2000}
                                    rows={2}
                                />
                            </Field>

                            <Field id="flag" label="Flag" error={errors.flag}>
                                <Input
                                    id="flag"
                                    name="flag"
                                    required={!isEditing}
                                    maxLength={200}
                                    autoComplete="off"
                                    spellCheck={false}
                                    placeholder={
                                        isEditing
                                            ? 'Leave blank to keep the current flag'
                                            : 'CTF{...}'
                                    }
                                    aria-describedby="flag-help"
                                />
                                <p
                                    id="flag-help"
                                    className="text-xs text-muted-foreground"
                                >
                                    Stored only as a SHA-256 hash. It cannot be
                                    shown again, so keep your own copy.
                                </p>
                            </Field>

                            {isEditing && challenge.links.length > 0 && (
                                <fieldset className="grid gap-2">
                                    <legend className="mb-2 text-sm font-medium">
                                        Current files (tick to remove)
                                    </legend>
                                    {challenge.links.map((link) => (
                                        <div
                                            key={link}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                id={`remove-${link}`}
                                                name="remove_files[]"
                                                value={link}
                                            />
                                            <Label
                                                htmlFor={`remove-${link}`}
                                                className="font-normal break-all"
                                            >
                                                {link}
                                            </Label>
                                        </div>
                                    ))}
                                </fieldset>
                            )}

                            <Field
                                id="files"
                                label={isEditing ? 'Add files' : 'Files'}
                                error={
                                    errors.files ??
                                    Object.entries(errors).find(([key]) =>
                                        key.startsWith('files.'),
                                    )?.[1]
                                }
                            >
                                <Input
                                    id="files"
                                    name="files[]"
                                    type="file"
                                    multiple
                                    aria-describedby="files-help"
                                />
                                <p
                                    id="files-help"
                                    className="text-xs text-muted-foreground"
                                >
                                    Up to 10 files, 10 MB each. Students open
                                    them from the challenge card. A file with
                                    the same name replaces the old one.
                                </p>
                            </Field>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {isEditing
                                        ? 'Save challenge'
                                        : 'Create challenge'}
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={GameController.show()}>
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
