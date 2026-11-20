<?php

use App\Domains\Members\Models\User;
use App\Support\AppVersion;

function fakeGitDir(): string
{
    $dir = sys_get_temp_dir().'/app-version-'.uniqid();
    mkdir("{$dir}/refs/heads", 0777, true);

    return $dir;
}

it('reads the short hash of the checked out branch', function () {
    $git = fakeGitDir();
    file_put_contents("{$git}/HEAD", "ref: refs/heads/main\n");
    file_put_contents("{$git}/refs/heads/main", "af2f6b3c1d2e3f405162738495a6b7c8d9e0f1a2\n");

    expect(AppVersion::fromGit($git))->toBe('af2f6b3');
});

it('reads the hash from packed refs and from a detached head', function () {
    $git = fakeGitDir();
    file_put_contents("{$git}/HEAD", "ref: refs/heads/main\n");
    file_put_contents("{$git}/packed-refs", "# pack-refs with: peeled\n1234567abcdef1234567abcdef1234567abcdef0 refs/heads/main\n");

    expect(AppVersion::fromGit($git))->toBe('1234567');

    file_put_contents("{$git}/HEAD", "abcdef0123456789abcdef0123456789abcdef01\n");

    expect(AppVersion::fromGit($git))->toBe('abcdef0');
});

it('returns null without a git checkout', function () {
    expect(AppVersion::fromGit(sys_get_temp_dir().'/missing-git-'.uniqid()))->toBeNull();
});

it('prefers the configured version and exposes it to the app', function () {
    config(['app.version' => '1.2.3']);

    $this->getJson('/version')
        ->assertOk()
        ->assertExactJson(['version' => '1.2.3'])
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs(User::factory()->create())->get('/')
        ->assertOk()
        ->assertSee('v1.2.3');
});
