<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;

mutates(SteamServiceProvider::class);

// One per parallel process: mutants of the provider run this file side by side.
function publishDirectory(): string
{
    return sys_get_temp_dir().'/laravel-steam-api-sdk-publish-'.ParallelTesting::token();
}

beforeEach(function (): void {
    File::deleteDirectory(publishDirectory());

    app()->useConfigPath(publishDirectory().'/config');
    app()->useLangPath(publishDirectory().'/lang');

    // The provider reads both destinations when it boots.
    new SteamServiceProvider(app())->boot();
});

afterEach(function (): void {
    File::deleteDirectory(publishDirectory());
});

it('publishes the config file under its tag', function (): void {
    $this->pendingCommand('vendor:publish', ['--tag' => 'steam-api-config'])->assertSuccessful();

    expect(File::get(publishDirectory().'/config/steam-api.php'))
        ->toBe(File::get(__DIR__.'/../../config/steam-api.php'));
});

it('publishes the translations under their tag', function (): void {
    $this->pendingCommand('vendor:publish', ['--tag' => 'steam-api-translations'])->assertSuccessful();

    expect(File::get(publishDirectory().'/lang/vendor/steam-api/en/validation.php'))
        ->toBe(File::get(__DIR__.'/../../lang/en/validation.php'));
});
