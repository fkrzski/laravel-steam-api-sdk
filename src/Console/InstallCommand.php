<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;

/**
 * The `steam:install` command — publishes the config file and puts the API key
 * in `.env`. Neither step overwrites anything without asking.
 */
final class InstallCommand extends Command
{
    private const string ENV_KEY = 'STEAM_API_KEY';

    private const string CONFIG_FILE = 'steam-api.php';

    /**
     * Anchored per line, so a commented-out key is not a match.
     */
    private const string ENV_KEY_PATTERN = '/^'.self::ENV_KEY.'=.*$/m';

    protected $signature = 'steam:install';

    protected $description = 'Publish the Steam API config file and set your Steam Web API key';

    public function handle(Application $app, Filesystem $files): int
    {
        $this->publishConfig($app, $files);
        $this->storeApiKey($app, $files);

        return self::SUCCESS;
    }

    private function publishConfig(Application $app, Filesystem $files): void
    {
        $path = $app->configPath(self::CONFIG_FILE);

        if ($files->exists($path)) {
            $this->components->info('config/'.self::CONFIG_FILE.' already exists, leaving it untouched.');

            return;
        }

        $files->copy(__DIR__.'/../../config/'.self::CONFIG_FILE, $path);

        $this->components->info('Published config/'.self::CONFIG_FILE);
    }

    private function storeApiKey(Application $app, Filesystem $files): void
    {
        $path = $app->environmentFilePath();

        if (! $files->exists($path)) {
            $this->components->warn(sprintf('No .env file found — set %s once you have one.', self::ENV_KEY));

            return;
        }

        $contents = $files->get($path);
        $alreadySet = preg_match(self::ENV_KEY_PATTERN, $contents) === 1;

        if ($alreadySet && ! $this->confirm(sprintf('%s is already set in .env. Replace it?', self::ENV_KEY))) {
            $this->components->info(sprintf('Kept the existing %s.', self::ENV_KEY));

            return;
        }

        $key = $this->askForKey();

        if ($key === '') {
            $this->components->warn('No key given — .env left untouched.');

            return;
        }

        $files->put($path, $alreadySet
            ? $this->replaceKey($contents, $key)
            : $this->appendKey($contents, $key));

        $this->components->info(sprintf('Wrote %s to .env.', self::ENV_KEY));
    }

    /**
     * Hidden input: the key is a credential, and installs get screen-shared.
     */
    private function askForKey(): string
    {
        $key = $this->secret('Your Steam Web API key (get one at https://steamcommunity.com/dev)');

        return is_string($key) ? trim($key) : '';
    }

    private function replaceKey(string $contents, string $key): string
    {
        return Str::of($contents)
            ->replaceMatches(self::ENV_KEY_PATTERN, static fn (): string => self::ENV_KEY.'='.$key)
            ->toString();
    }

    /**
     * LF rather than PHP_EOL: `.env` files stay LF-only, Windows included.
     */
    private function appendKey(string $contents, string $key): string
    {
        $separator = $contents === '' || str_ends_with($contents, "\n") ? '' : "\n";

        return $contents.$separator.self::ENV_KEY.'='.$key."\n";
    }
}
