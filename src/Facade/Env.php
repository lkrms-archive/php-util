<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Environment;

/**
 * A facade for \Lkrms\Utility\Environment
 *
 * @method static Environment load() Load and return an instance of the underlying Environment class
 * @method static Environment getInstance() Get the underlying Environment instance
 * @method static bool isLoaded() True if an underlying Environment instance has been loaded
 * @method static void unload() Clear the underlying Environment instance
 * @method static void apply() Apply values from the environment to the running script (see {@see Environment::apply()})
 * @method static bool debug(?bool $newState = null) Optionally turn debug mode on or off, then return its current state (see {@see Environment::debug()})
 * @method static bool dryRun(?bool $newState = null) Optionally turn dry-run mode on or off, then return its current state (see {@see Environment::dryRun()})
 * @method static string|null get(string $name, ?string $default = null) Get an environment variable (see {@see Environment::get()})
 * @method static bool|null getBool(string $name, ?bool $default = null) Get a boolean value from the environment (see {@see Environment::getBool()})
 * @method static int|null getInt(string $name, ?int $default = null) Get an integer value from the environment (see {@see Environment::getInt()})
 * @method static string[]|null getList(string $name, string[]|null $default = null, string $delimiter = "\054") Get a list of strings from the environment (see {@see Environment::getList()})
 * @method static string|null getNullable(string $name, ?string $default = null) Get the value of an environment variable, or null if it's set but empty (see {@see Environment::getNullable()})
 * @method static bool|null getNullableBool(string $name, ?bool $default = null) Get a boolean value from the environment, or null if it's set but empty (see {@see Environment::getNullableBool()})
 * @method static int|null getNullableInt(string $name, ?int $default = null) Get an integer value from the environment, or null if it's set but empty (see {@see Environment::getNullableInt()})
 * @method static bool has(string $name) True if a variable exists in the environment
 * @method static string|null home() Get the current user's home directory from the environment
 * @method static bool isLocaleUtf8() True if the current locale for character classification and conversion (LC_CTYPE) supports UTF-8
 * @method static void loadFile(string $filename) Load values into the environment from a file (see {@see Environment::loadFile()})
 * @method static void set(string $name, string $value) Set an environment variable (see {@see Environment::set()})
 * @method static void unset(string $name) Unset an environment variable (see {@see Environment::unset()})
 *
 * @uses Environment
 *
 * @extends Facade<Environment>
 *
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Environment' 'Lkrms\Facade\Env'
 */
final class Env extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Environment::class;
    }
}
