<?php

use App\Models\Challenge;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('ctf');
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * @return array<string, mixed>
 */
function challengeInput(array $overrides = []): array
{
    return [
        'category' => 'Web',
        'title' => 'Hidden Comment',
        'points' => 150,
        'description' => 'Look closely at the page.',
        'hint' => 'View the source.',
        'flag' => 'CTF{admin_made_flag}',
        ...$overrides,
    ];
}

test('the admin creates a challenge with a hashed flag and uploaded files', function () {
    $game = Game::factory()->create();
    Challenge::factory()->for($game)->create(['number' => 4]);

    $this->post(route('admin.challenges.store'), challengeInput([
        'flag' => '  CTF{admin_made_flag} ',
        'files' => [UploadedFile::fake()->createWithContent('Index Page.HTML', '<p>hi</p>')],
    ]))->assertRedirect(route('challenges'));

    $challenge = Challenge::where('title', 'Hidden Comment')->sole();
    $link = "ctf/uploads/{$challenge->id}/index-page.html";

    expect($challenge)
        ->game_id->toBe($game->id)
        ->number->toBe(5)
        ->points->toBe(150)
        ->links->toBe([$link])
        ->and($challenge->isCorrectFlag('CTF{admin_made_flag}'))->toBeTrue();
    $this->assertDatabaseMissing('challenges', ['flag_hash' => 'CTF{admin_made_flag}']);
    Storage::disk('ctf')->assertExists("{$challenge->id}/index-page.html");
});

test('a new challenge needs a flag', function () {

    $this->post(route('admin.challenges.store'), challengeInput(['flag' => '']))
        ->assertSessionHasErrors('flag');

    $this->assertDatabaseCount('challenges', 0);
});

test('files the web server could execute are rejected', function (string $filename) {

    $this->post(route('admin.challenges.store'), challengeInput([
        'files' => [UploadedFile::fake()->createWithContent($filename, '<?php echo 1;')],
    ]))->assertSessionHasErrors('files.0');

    $this->assertDatabaseCount('challenges', 0);
})->with(['shell.php', 'shell.phtml', '.htaccess']);

test('dots in an uploaded file name are stripped so it cannot run as PHP', function () {

    $this->post(route('admin.challenges.store'), challengeInput([
        'files' => [UploadedFile::fake()->createWithContent('notes.php.txt', 'hello')],
    ]));

    $challenge = Challenge::sole();
    expect($challenge->links)->toBe(["ctf/uploads/{$challenge->id}/notesphp.txt"]);
});

test('editing without a flag keeps the old flag and removed files are deleted', function () {
    $challenge = Challenge::factory()->withFlag('CTF{original_flag}')->create([
        'links' => ['ctf/web/index.html'],
    ]);
    Storage::disk('ctf')->put("{$challenge->id}/old.txt", 'old');
    $challenge->update(['links' => ['ctf/web/index.html', "ctf/uploads/{$challenge->id}/old.txt"]]);

    $this->put(route('admin.challenges.update', $challenge), challengeInput([
        'flag' => '',
        'title' => 'Renamed',
        // A path that is not one of this challenge's links is ignored.
        'remove_files' => ["ctf/uploads/{$challenge->id}/old.txt", 'ctf/web/robots.txt'],
    ]))->assertRedirect(route('challenges'));

    $challenge->refresh();
    expect($challenge)
        ->title->toBe('Renamed')
        ->links->toBe(['ctf/web/index.html'])
        ->and($challenge->isCorrectFlag('CTF{original_flag}'))->toBeTrue();
    Storage::disk('ctf')->assertMissing("{$challenge->id}/old.txt");
});

test('deleting a challenge removes its uploaded files', function () {
    $challenge = Challenge::factory()->create();
    Storage::disk('ctf')->put("{$challenge->id}/file.txt", 'data');

    $this->delete(route('admin.challenges.destroy', $challenge))
        ->assertRedirect(route('challenges'));

    $this->assertModelMissing($challenge);
    Storage::disk('ctf')->assertMissing("{$challenge->id}/file.txt");
});
