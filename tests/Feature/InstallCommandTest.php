<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Console\InstallCommand;
use Illuminate\Support\Facades\File;

mutates(InstallCommand::class);

const KEY_QUESTION = 'Your Steam Web API key (get one at https://steamcommunity.com/dev)';

const REPLACE_QUESTION = 'STEAM_API_KEY is already set in .env. Replace it?';

/**
 * A throwaway application directory: the command must not write into the
 * testbench skeleton, which parallel test processes boot out of.
 */
function installDirectory(): string
{
    return sys_get_temp_dir().'/laravel-steam-api-sdk-install';
}

function envFile(): string
{
    return installDirectory().'/.env';
}

function publishedConfig(): string
{
    return installDirectory().'/config/steam-api.php';
}

beforeEach(function (): void {
    File::deleteDirectory(installDirectory());
    File::ensureDirectoryExists(installDirectory().'/config');
    File::put(envFile(), "APP_NAME=Laravel\n");

    $this->app->useEnvironmentPath(installDirectory());
    $this->app->useConfigPath(installDirectory().'/config');
});

afterEach(function (): void {
    File::deleteDirectory(installDirectory());
});

it('publishes the config file', function (): void {
    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, 'test-steam-api-key')
        ->expectsOutputToContain('Published config/steam-api.php.')
        ->assertSuccessful();

    expect(publishedConfig())->toBeFile()
        ->and(File::get(publishedConfig()))->toContain("'key' => env('STEAM_API_KEY')");
});

it('leaves an already published config file untouched', function (): void {
    File::put(publishedConfig(), '<?php return [];');

    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, 'test-steam-api-key')
        ->expectsOutputToContain('config/steam-api.php already exists')
        ->assertSuccessful();

    expect(File::get(publishedConfig()))->toBe('<?php return [];');
});

it('appends the key to the env file', function (): void {
    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, 'test-steam-api-key')
        ->expectsOutputToContain('Wrote STEAM_API_KEY to .env')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("APP_NAME=Laravel\nSTEAM_API_KEY=test-steam-api-key\n");
});

it('breaks the line before appending to an env file that does not end in one', function (): void {
    File::put(envFile(), 'APP_NAME=Laravel');

    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, 'test-steam-api-key')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("APP_NAME=Laravel\nSTEAM_API_KEY=test-steam-api-key\n");
});

it('writes to an empty env file without a leading blank line', function (): void {
    File::put(envFile(), '');

    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, 'test-steam-api-key')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("STEAM_API_KEY=test-steam-api-key\n");
});

it('trims the entered key', function (): void {
    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, '  test-steam-api-key  ')
        ->assertSuccessful();

    expect(File::get(envFile()))->toContain("STEAM_API_KEY=test-steam-api-key\n");
});

it('replaces an existing key in place once confirmed', function (): void {
    File::put(envFile(), "APP_NAME=Laravel\nSTEAM_API_KEY=old-key\nAPP_ENV=local\n");

    $this->artisan('steam:install')
        ->expectsConfirmation(REPLACE_QUESTION, 'yes')
        ->expectsQuestion(KEY_QUESTION, 'new-key')
        ->expectsOutputToContain('Wrote STEAM_API_KEY to .env')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("APP_NAME=Laravel\nSTEAM_API_KEY=new-key\nAPP_ENV=local\n");
});

it('writes a key containing regex backreference syntax verbatim', function (): void {
    File::put(envFile(), "STEAM_API_KEY=old-key\n");

    $this->artisan('steam:install')
        ->expectsConfirmation(REPLACE_QUESTION, 'yes')
        ->expectsQuestion(KEY_QUESTION, '$1\\2')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("STEAM_API_KEY=\$1\\2\n");
});

it('keeps the existing key when the replacement is declined', function (): void {
    File::put(envFile(), "STEAM_API_KEY=old-key\n");

    $this->artisan('steam:install')
        ->expectsConfirmation(REPLACE_QUESTION, 'no')
        ->expectsOutputToContain('Kept the existing STEAM_API_KEY')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("STEAM_API_KEY=old-key\n");
});

it('does not treat a commented out key as one that is already set', function (): void {
    File::put(envFile(), "# STEAM_API_KEY=old-key\n");

    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, 'test-steam-api-key')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("# STEAM_API_KEY=old-key\nSTEAM_API_KEY=test-steam-api-key\n");
});

it('leaves the env file untouched when no key is given', function (): void {
    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, '   ')
        ->expectsOutputToContain('No key given')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("APP_NAME=Laravel\n");
});

it('leaves the env file untouched when the prompt is answered with nothing', function (): void {
    $this->artisan('steam:install')
        ->expectsQuestion(KEY_QUESTION, null)
        ->expectsOutputToContain('No key given')
        ->assertSuccessful();

    expect(File::get(envFile()))->toBe("APP_NAME=Laravel\n");
});

it('still publishes the config when the application has no env file', function (): void {
    File::delete(envFile());

    $this->artisan('steam:install')
        ->expectsOutputToContain('No .env file found')
        ->assertSuccessful();

    expect(publishedConfig())->toBeFile();
});
