<?php

use App\Models\Attempt;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->challenge = Challenge::factory()->create();
    $this->player = teamPlayer();
});

test('a team uploads a write-up that only the team and admins can download', function () {
    $this->actingAs($this->player)
        ->post(route('challenges.writeup', $this->challenge), ['writeup' => UploadedFile::fake()->create('How we did it.pdf', 50, 'application/pdf')])
        ->assertRedirect();

    $attempt = Attempt::firstOrFail();
    Storage::disk('local')->assertExists("writeups/{$this->player->team_id}/{$this->challenge->id}.pdf");

    $teammate = $this->player->team->members()->whereKeyNot($this->player->id)->firstOrFail();
    $this->actingAs($teammate)
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page
            ->where("categories.{$this->challenge->category}.0.writeup.name", 'How we did it.pdf'));
    $this->get(route('writeups.show', $attempt))->assertOk()->assertDownload('How we did it.pdf');

    $this->actingAs(User::factory()->admin()->create())->get(route('writeups.show', $attempt))->assertOk();
    $this->actingAs(teamPlayer())->get(route('writeups.show', $attempt))->assertForbidden();
});

test('a new upload replaces the old write-up', function () {
    $this->actingAs($this->player);
    $this->post(route('challenges.writeup', $this->challenge), ['writeup' => UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf')]);
    $this->post(route('challenges.writeup', $this->challenge), ['writeup' => UploadedFile::fake()->create('final.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]);

    Storage::disk('local')->assertMissing("writeups/{$this->player->team_id}/{$this->challenge->id}.pdf");
    Storage::disk('local')->assertExists("writeups/{$this->player->team_id}/{$this->challenge->id}.docx");
    expect(Attempt::sole()->writeup_name)->toBe('final.docx');
});

test('only PDF or Word files are accepted', function () {
    $this->actingAs($this->player)
        ->post(route('challenges.writeup', $this->challenge), ['writeup' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php')])
        ->assertSessionHasErrors(['writeup' => 'Upload a PDF or Word (.docx) file of 10 MB or less.']);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('write-up uploads are limited to 5 per minute per student', function () {
    $this->actingAs($this->player);

    foreach (range(1, 5) as $upload) {
        $this->post(route('challenges.writeup', $this->challenge), ['writeup' => UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf')])
            ->assertInertiaFlash('toast.message', 'Write-up uploaded.');
    }

    $this->post(route('challenges.writeup', $this->challenge), ['writeup' => UploadedFile::fake()->create('final.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
        ->assertInertiaFlash('toast.message', 'Too many requests. Try again in a minute.');
    Storage::disk('local')->assertMissing("writeups/{$this->player->team_id}/{$this->challenge->id}.docx");
});

test('resetting scores deletes the write-ups', function () {
    $this->actingAs($this->player)
        ->post(route('challenges.writeup', $this->challenge), ['writeup' => UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf')]);

    $this->actingAs(User::factory()->admin()->create())->delete(route('admin.game.scores.destroy'));

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('the admin shares a write-up template that every team can download', function () {
    Storage::fake('ctf');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.game.writeup-template'), ['template' => UploadedFile::fake()->create('Template.pdf', 50, 'application/pdf')])
        ->assertRedirect();
    $this->actingAs($admin)
        ->post(route('admin.game.writeup-template'), ['template' => UploadedFile::fake()->create('Template v2.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]);

    // The replacement removed the old PDF.
    Storage::disk('ctf')->assertMissing('templates/writeup-template.pdf');
    Storage::disk('ctf')->assertExists('templates/writeup-template.docx');

    $this->actingAs($this->player)
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page->where('game.writeup_template_url', fn (string $url) => str_contains($url, '/ctf/uploads/templates/writeup-template.docx?v=')));

    // Removing it deletes the file and hides the link.
    $this->actingAs($admin)->delete(route('admin.game.writeup-template.destroy'))->assertRedirect();
    Storage::disk('ctf')->assertMissing('templates/writeup-template.docx');
    $this->actingAs($this->player)
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page->where('game.writeup_template_url', null));
});
