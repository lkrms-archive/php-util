<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\Container;
use Lkrms\Container\ContextContainer;

/**
 * A facade for Container
 *
 * @method static Container load() Create and return the underlying Container
 * @method static Container getInstance() Return the underlying Container
 * @method static bool isLoaded() Return true if the underlying Container has been created
 * @method static Container bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = []) Bind a class to the given identifier
 * @method static ContextContainer context(string $id) Get a context-specific facade for the container
 * @method static mixed get(string $id, mixed ...$params) Create a new instance of the given class or interface, or retrieve a singleton created earlier
 * @method static Container getGlobalContainer() Get the current global container, creating one if necessary
 * @method static bool has(string $id) Returns true if the given identifier can be resolved to a concrete class
 * @method static bool hasGlobalContainer() Returns true if a global container exists
 * @method static string name(string $id) Get a concrete class name for the given identifier
 * @method static Container pop() Pop the most recently pushed container off the stack and activate it
 * @method static Container push() Push a copy of the container onto the stack
 * @method static Container service(string $id, string[] $services = null, string[] $exceptServices = null) Bind an IBindable and its services, optionally specifying the services to bind or exclude
 * @method static Container singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = []) Bind a class to the given identifier as a shared instance
 *
 * @uses Container
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\Container' --generate='Lkrms\Facade\DI'
 */
final class DI extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Container::class;
    }
}
