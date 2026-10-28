<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Console\AboutSection;
use Fkrzski\LaravelSteamApiSdk\Console\InstallCommand;
use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestFailed;
use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestSending;
use Fkrzski\LaravelSteamApiSdk\Events\SteamResponseReceived;
use Fkrzski\LaravelSteamApiSdk\Http\HttpOptions;
use Fkrzski\LaravelSteamApiSdk\Logging\LoggingOptions;
use Fkrzski\LaravelSteamApiSdk\Logging\SteamTrafficLogger;
use Fkrzski\LaravelSteamApiSdk\Monitoring\NightwatchReporting;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\RateLimitOptions;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\UtcDayLimit;
use Fkrzski\LaravelSteamApiSdk\Rendering\SteamExceptionRenderer;
use Fkrzski\LaravelSteamApiSdk\SteamManager;

dataset('classes only the bridge builds', function (): iterable {
    $classes = [
        AboutSection::class,
        InstallCommand::class,
        HttpOptions::class,
        LoggingOptions::class,
        SteamTrafficLogger::class,
        NightwatchReporting::class,
        RateLimitOptions::class,
        UtcDayLimit::class,
        SteamExceptionRenderer::class,
    ];

    foreach ($classes as $class) {
        yield $class => [new ReflectionClass($class)];
    }
});

dataset('members the bridge builds', function (): iterable {
    yield SteamManager::class.'::__construct' => [SteamManager::class, '__construct'];

    $exceptions = array_filter(array_map(
        static fn (string $file): string => 'Fkrzski\LaravelSteamApiSdk\Exceptions\\'.basename($file, '.php'),
        glob(__DIR__.'/../../src/Exceptions/*.php') ?: [],
    ), class_exists(...));

    foreach ($exceptions as $class) {
        foreach (new ReflectionClass($class)->getMethods() as $method) {
            $builds = $method->isConstructor() || $method->isStatic();

            if ($builds && $method->getDeclaringClass()->getName() === $class && ! $method->isPrivate()) {
                yield "{$class}::{$method->getName()}" => [$class, $method->getName()];
            }
        }
    }
});

test('classes only the bridge builds are internal', function (ReflectionClass $class): void {
    expect((string) $class->getDocComment())->toContain('@internal');
})->with('classes only the bridge builds');

test('members the bridge builds are internal', function (string $class, string $method): void {
    expect((string) new ReflectionMethod($class, $method)->getDocComment())->toContain('@internal');
})->with('members the bridge builds');

test('the event constructors stay covered for listener tests', function (string $class): void {
    expect((string) new ReflectionMethod($class, '__construct')->getDocComment())->not->toContain('@internal');
})->with([SteamRequestSending::class, SteamResponseReceived::class, SteamRequestFailed::class]);
