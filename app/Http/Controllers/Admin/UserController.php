<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const MAX_BULK_LINES = 200;

    public function index(): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::query()
                ->with(['roles:id,name', 'team:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'team_id'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                    'team' => $user->team?->name,
                ]),
        ]);
    }

    /**
     * Add one student with a password the admin chose.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->createStudent(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$user->name}."]);

        return back();
    }

    /**
     * Add a class list, one "Name, email" per line. Passwords are generated and shown to the admin once.
     */
    public function storeMany(Request $request): RedirectResponse
    {
        $request->validate(['students' => ['required', 'string', 'max:50000']]);

        // trim() also drops the "\r" of Windows line endings.
        $lines = $request->string('students')->explode("\n")
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values();

        if ($lines->count() > self::MAX_BULK_LINES) {
            throw ValidationException::withMessages(['students' => 'Add at most '.self::MAX_BULK_LINES.' students at a time.']);
        }

        $rows = $lines->map(function (string $line): array {
            // Split on the last comma or tab, so a name like "Christian, Joe" still works.
            $separator = max(strrpos($line, ',') ?: -1, strrpos($line, "\t") ?: -1);

            return $separator < 0
                ? ['name' => $line, 'email' => '']
                : ['name' => trim(substr($line, 0, $separator)), 'email' => trim(substr($line, $separator + 1))];
        })->all();

        $validator = Validator::make($rows, [
            '*.name' => ['required', 'string', 'max:255'],
            '*.email' => ['required', 'string', 'email', 'max:255', 'distinct:ignore_case', Rule::unique(User::class, 'email')],
        ], attributes: ['*.name' => 'name', '*.email' => 'email']);

        if ($validator->fails()) {
            // Report problems by line number, which is what the admin can find in the pasted list.
            $messages = collect($validator->errors()->messages())
                ->map(fn (array $errors, string $key): string => 'Line '.((int) Str::before($key, '.') + 1).': '.$errors[0])
                ->values()
                ->all();

            throw ValidationException::withMessages(['students' => $messages]);
        }

        $credentials = DB::transaction(fn (): array => array_map(function (array $row): array {
            $password = Str::password(12, symbols: false);
            $user = $this->createStudent($row['name'], $row['email'], $password);

            return ['name' => $user->name, 'email' => $user->email, 'password' => $password];
        }, $rows));

        Inertia::flash('credentials', $credentials);

        return back();
    }

    /**
     * Give the user a new random password, shown to the admin once.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $password = Str::password(12, symbols: false);
        $user->update(['password' => $password]);

        Inertia::flash('credentials', [['name' => $user->name, 'email' => $user->email, 'password' => $password]]);

        return back();
    }

    /**
     * Deleting a student removes them from their team; the team keeps its solves.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account here.');

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Deleted {$user->name}."]);

        return back();
    }

    /**
     * The admin vouches for the address, so no verification mail is needed.
     */
    private function createStudent(string $name, string $email, string $password): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(User::STUDENT);

        return $user;
    }
}
