<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Console\ConsoleLevels;
use Lkrms\Container\AppContainer;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;

/**
 * A facade for AppContainer
 *
 * @method static AppContainer load(?string $basePath = null) Create and return the underlying AppContainer
 * @method static AppContainer getInstance() Return the underlying AppContainer
 * @method static bool isLoaded() Return true if the underlying AppContainer has been created
 * @method static AppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container
 * @method static mixed call(callable $callback) Make this the global container while running the given callback
 * @method static AppContainer enableCache()
 * @method static AppContainer enableExistingCache()
 * @method static AppContainer enableMessageLog(?string $name = null, array $levels = ConsoleLevels::ALL_DEBUG)
 * @method static mixed get(string $id, mixed ...$params) Finds an entry of the container by its identifier and returns it.
 * @method static IContainer getGlobalContainer() Get the current global container, loading it if necessary
 * @method static string getName(string $id) Resolve the given class or interface to a concrete class
 * @method static bool has(string $id) Returns true if the container can return an entry for the given identifier. Returns false otherwise.
 * @method static bool hasGlobalContainer() Return true if a global container has been loaded
 * @method static Container inContextOf(string $id) Get a copy of the container where the contextual bindings of the given class or interface are applied
 * @method static AppContainer instance(string $id, mixed $instance) Register an existing instance as a shared binding
 * @method static AppContainer service(string $id, null|string[] $services = null, null|string[] $exceptServices = null, ?array $constructParams = null, ?array $shareInstances = null) Add bindings to the container for an IBindable implementation and its services, optionally specifying services to bind or exclude
 * @method static IContainer|null setGlobalContainer(?IContainer $container) Set (or unset) the global container
 * @method static AppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container
 *
 * @uses AppContainer
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\AppContainer' --generate='Lkrms\Facade\App'
 */
final class App extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return AppContainer::class;
    }
}
