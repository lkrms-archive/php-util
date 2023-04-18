<?php declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Concept;

use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Contract\IProvider;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\LkUtil\Dictionary\EnvVar;

/**
 * Base class for lk-util commands
 *
 */
abstract class Command extends CliCommand
{
    public function getLongDescription(): ?string
    {
        return null;
    }

    public function getUsageSections(): ?array
    {
        return null;
    }

    /**
     * Normalise a user-supplied class name, optionally assigning its base name
     * and/or namespace to variables passed by reference
     *
     * @return class-string<object>
     */
    protected function getFqcnOptionValue(
        string $value,
        ?string $namespaceEnvVar = null,
        ?string &$class = null,
        ?string &$namespace = null
    ): string {
        $namespace = null;
        if ($namespaceEnvVar) {
            $namespace = Env::get($namespaceEnvVar, null);
        }
        if (is_null($namespace)) {
            $namespace = Env::get(EnvVar::NS_DEFAULT, null);
        }
        if ($namespace && trim($value) && strpos($value, '\\') === false) {
            $fqcn = trim($namespace, '\\') . "\\$value";
        } else {
            $fqcn = ltrim($value, '\\');
        }
        $class = Convert::classToBasename($fqcn);
        $namespace = Convert::classToNamespace($fqcn);

        return $fqcn;
    }

    /**
     * Normalise user-supplied class names
     *
     * @param string[] $values
     * @return array<class-string<object>>
     */
    protected function getMultipleFqcnOptionValue(array $values, ?string $namespaceEnvVar = null): array
    {
        $fqcn = [];
        foreach ($values as $value) {
            $fqcn[] = $this->getFqcnOptionValue($value, $namespaceEnvVar);
        }

        return $fqcn;
    }

    /**
     * @template TBaseProvider of IProvider
     * @template TProvider of TBaseProvider
     * @param class-string<TProvider> $provider
     * @param class-string<TBaseProvider> $class
     * @return TProvider
     */
    protected function getProvider(string $provider, string $class = IProvider::class): IProvider
    {
        $provider = $this->getFqcnOptionValue($provider, EnvVar::NS_PROVIDER);
        if (is_a($provider, $class, true)) {
            return $this->app()->get($provider);
        }

        throw class_exists($provider)
            ? new CliArgumentsInvalidException("not a subclass of $class: $provider")
            : new CliArgumentsInvalidException("class does not exist: $provider");
    }

    /**
     * @return mixed
     */
    protected function getJson(string $file, ?string &$path = null, bool $associative = true)
    {
        if ($file === '-') {
            $file = 'php://stdin';
        } elseif (($file = File::realpath($_file = $file)) === false) {
            throw new CliArgumentsInvalidException("file not found: $_file");
        } elseif (strpos($file, $this->app()->BasePath) === 0) {
            $path = './' . ltrim(substr($file, strlen($this->app()->BasePath)), '/');
        } else {
            $path = $file;
        }

        return json_decode(file_get_contents($file), $associative);
    }
}
