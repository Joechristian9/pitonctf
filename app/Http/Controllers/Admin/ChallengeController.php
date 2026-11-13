<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChallengeRequest;
use App\Models\Challenge;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChallengeController extends Controller
{
    private const UPLOAD_PREFIX = 'ctf/uploads/';

    public function create(): Response
    {
        return Inertia::render('admin/challenges/form', [
            'challenge' => null,
        ]);
    }

    /**
     * The flag is hashed on save and never stored or shown in plain text.
     */
    public function store(ChallengeRequest $request): RedirectResponse
    {
        $game = Game::current();

        $challenge = $game->challenges()->create([
            ...$request->safe()->only(['category', 'title', 'points', 'description', 'hint']),
            'number' => ((int) $game->challenges()->max('number')) + 1,
            'flag_hash' => hash('sha256', trim($request->validated('flag'))),
            'links' => [],
        ]);

        $this->storeFiles($request, $challenge);

        return to_route('admin.game');
    }

    public function edit(Challenge $challenge): Response
    {
        return Inertia::render('admin/challenges/form', [
            'challenge' => $challenge->only(['id', 'category', 'title', 'points', 'description', 'hint', 'links']),
        ]);
    }

    public function update(ChallengeRequest $request, Challenge $challenge): RedirectResponse
    {
        $challenge->fill($request->safe()->only(['category', 'title', 'points', 'description', 'hint']));

        if ($request->filled('flag')) {
            $challenge->flag_hash = hash('sha256', trim($request->validated('flag')));
        }

        // Only links the challenge already has can be removed, so no other file can be targeted.
        $removedLinks = array_intersect($challenge->links, $request->validated('remove_files', []));

        foreach ($removedLinks as $link) {
            // Files from the build script are shared and only unlinked; uploaded files are deleted.
            if (str_starts_with($link, self::UPLOAD_PREFIX)) {
                Storage::disk('ctf')->delete(Str::after($link, self::UPLOAD_PREFIX));
            }
        }

        $challenge->links = array_values(array_diff($challenge->links, $removedLinks));
        $challenge->save();

        $this->storeFiles($request, $challenge);

        return to_route('admin.game');
    }

    /**
     * Deleting a challenge also deletes its solves, attempts and uploaded files.
     */
    public function destroy(Challenge $challenge): RedirectResponse
    {
        Storage::disk('ctf')->deleteDirectory((string) $challenge->id);
        $challenge->delete();

        return to_route('admin.game');
    }

    private function storeFiles(ChallengeRequest $request, Challenge $challenge): void
    {
        $links = $challenge->links;

        foreach ($request->file('files', []) as $file) {
            // The slug strips dots from the base name, so "shell.php.txt" can never be run as PHP.
            $name = (Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file')
                .'.'.strtolower($file->getClientOriginalExtension());

            $file->storeAs((string) $challenge->id, $name, 'ctf');
            $links[] = self::UPLOAD_PREFIX."{$challenge->id}/{$name}";
        }

        $challenge->update(['links' => array_values(array_unique($links))]);
    }
}
