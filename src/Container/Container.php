<?php declare(strict_types=1);

namespace Lkrms\Container;

use Dice\Dice;
use Dice\DiceException;
use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Exception\ContainerNotLocatedException;
use Lkrms\Container\Exception\ContainerServiceNotFoundException;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IService;
use Lkrms\Contract\IServiceShared;
use Lkrms\Contract\IServiceSingleton;
use Lkrms\Contract\ReceivesContainer;
use Lkrms\Contract\ReceivesService;
use Lkrms\Facade\Event;
use Psr\Container\ContainerInterface;
use Closure;
use ReflectionClass;
use UnexpectedValueException;

/**
 * A simple service container with context-based dependency injection
 *
 * A static interface to the global service container is provided by
 * {@see \Lkrms\Facade\DI}.
 */
class Container extends FluentInterface implements IContainer
{
    private static ?IContainer $GlobalContainer = null;

    private Dice $Dice;

    /**
     * @see Container::service()
     * @var array<class-string<IService>,true>
     */
    private array $Services = [];

    /**
     * @see Container::inContextOf()
     * @var class-string[]
     */
    private array $ContextStack = [];

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        $this->Dice = new Dice();
        $this->bindContainer();
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        if ($this === self::$GlobalContainer) {
            self::setGlobalContainer(null);
        }

        unset($this->Dice);
    }

    private function bindContainer(): void
    {
        $class = new ReflectionClass(static::class);

        // Bind any interfaces that extend PSR-11's ContainerInterface
        foreach ($class->getInterfaces() as $name => $interface) {
            if ($interface->implementsInterface(ContainerInterface::class)) {
                $this->instance($name, $this);
            }
        }

        // Also bind classes between self and static
        do {
            $this->instance($class->getName(), $this);
            $class = $class->getParentClass();
        } while ($class);

        $this->Dice = $this->Dice->addCallback(
            '*',
            Closure::fromCallable([$this, 'callback']),
            __METHOD__,
        );
    }

    private function callback(object $instance, string $name): object
    {
        if ($instance instanceof ReceivesContainer) {
            $instance = $instance->setContainer($this);
        }

        if ($instance instanceof ReceivesService) {
            $instance = $instance->setService($name);
        }

        return $instance;
    }

    /**
     * @inheritDoc
     */
    final public static function hasGlobalContainer(): bool
    {
        return self::$GlobalContainer !== null;
    }

    /**
     * @inheritDoc
     */
    final public static function getGlobalContainer(): IContainer
    {
        if (self::$GlobalContainer !== null) {
            return self::$GlobalContainer;
        }

        return self::$GlobalContainer = new static(...func_get_args());
    }

    /**
     * Get the global container if it exists
     */
    final public static function maybeGetGlobalContainer(): ?IContainer
    {
        return self::$GlobalContainer;
    }

    /**
     * Get the global container if it exists, otherwise throw an exception
     *
     * @throws ContainerNotLocatedException if the global container does not
     * exist.
     */
    final public static function requireGlobalContainer(): IContainer
    {
        if (self::$GlobalContainer === null) {
            throw new ContainerNotLocatedException();
        }

        return self::$GlobalContainer;
    }

    /**
     * @inheritDoc
     */
    final public static function setGlobalContainer(?IContainer $container): ?IContainer
    {
        Event::dispatch('container.global.set', $container);

        self::$GlobalContainer = $container;

        return $container;
    }

    /**
     * @inheritDoc
     */
    final public function get(string $id, array $args = [])
    {
        try {
            return $this->Dice->create($id, $args);
        } catch (DiceException $ex) {
            throw new ContainerServiceNotFoundException($ex->getMessage(), $ex);
        }
    }

    final public function getAs(string $id, string $service, array $args = [])
    {
        if ($this->Dice->hasShared($id)) {
            $instance = $this->get($id);
            if ($instance instanceof ReceivesService) {
                return $instance->setService($service);
            }

            return $instance;
        }

        try {
            return $this->Dice->addCallback(
                $id,
                function (object $instance, string $name, bool &$continue) use ($service): object {
                    $continue = false;

                    return $this->callback($instance, $service);
                },
                null,
                true
            )->create($id, $args);
        } catch (DiceException $ex) {
            throw new ContainerServiceNotFoundException($ex->getMessage(), $ex);
        }
    }

    final public function getName(string $id): string
    {
        return $this->Dice->getRule($id)['instanceOf'] ?? $id;
    }

    final public function has(string $id): bool
    {
        return class_exists($this->getName($id));
    }

    /**
     * @param array<string,mixed> $rule
     */
    private function checkRule(array $rule): void
    {
        if (!empty(
            $subs = array_intersect(
                $rule['shareInstances'] ?? [],
                array_keys($rule['substitutions'] ?? [])
            )
        )) {
            throw new UnexpectedValueException("Dependencies in 'shareInstances' cannot be substituted: " . implode(', ', $subs));
        }
    }

    /**
     * @param array<string,mixed> $rule
     */
    private function addRule(string $id, array $rule): void
    {
        $_dice = $this->Dice->addRule($id, $rule);
        $this->checkRule($_dice->getRule($id));
        $this->Dice = $_dice;
    }

    /**
     * @inheritDoc
     */
    final public function inContextOf(string $id)
    {
        $clone = clone $this;

        // If $id implements IService and hasn't been bound to the container
        // yet, add bindings for everything except its services, which may
        // resolve to another provider
        if (is_subclass_of($id, IService::class) && !isset($this->Services[$id])) {
            $clone->applyService($id, []);
            $clone->Services[$id] = true;

            // If nothing changed, skip `applyService()` in future by setting
            // `$this->Services[$id]`
            if (!$this->compareBindingsWith($clone)) {
                $this->Services[$id] = true;
            }
        }

        if (!$clone->Dice->hasRule($id)) {
            return $this;
        }

        $subs = $clone->Dice->getRule($id)['substitutions'] ?? null;
        if (!$subs) {
            return $this;
        }

        $clone->applyBindings($subs);

        if (!$this->compareBindingsWith($clone)) {
            return $this;
        }

        $clone->ContextStack[] = $id;
        $clone->bindContainer();
        return $clone;
    }

    /**
     * @inheritDoc
     */
    final public function getContextStack(): array
    {
        return $this->ContextStack;
    }

    /**
     * @param array<string,string|object|array<string,mixed>> $subs
     */
    private function applyBindings(array $subs): void
    {
        $defaultRule = [];
        foreach ($subs as $key => $value) {
            if (is_string($value)) {
                if (strcasecmp($this->Dice->getRule($key)['instanceOf'] ?? '', $value)) {
                    $this->addRule($key, ['instanceOf' => $value]);
                }
            } elseif (is_object($value)) {
                if (!$this->Dice->hasShared($key) || $this->get($key) !== $value) {
                    $this->Dice = $this->Dice->addShared($key, $value);
                }
            } elseif (($this->Dice->getDefaultRule()['substitutions'][ltrim($key, '\\')] ?? null) !== $value) {
                // If this substitution can't be converted to a rule, copy it to
                // the default rule and force Dice to use it for the given
                // identifier
                $defaultRule['substitutions'][$key] = $value;
                $this->Dice = $this->Dice->removeRule($key);
            }
        }
        if (!empty($defaultRule)) {
            /**
             * @todo Patch Dice to apply substitutions in `create()`, not just
             * when resolving dependencies
             */
            $this->addRule('*', $defaultRule);
        }
    }

    /**
     * @template T0
     * @template T1 of T0
     * @param class-string<T0> $id
     * @param class-string<T1>|null $instanceOf
     * @param mixed[]|null $constructParams
     * @param class-string[]|null $shareInstances
     * @param array<string,mixed> $rule
     * @return $this
     */
    private function _bind(
        string $id,
        ?string $instanceOf,
        ?array $constructParams,
        ?array $shareInstances,
        array $rule = []
    ) {
        if (!is_null($instanceOf)) {
            $rule['instanceOf'] = $instanceOf;
        }
        if (!is_null($constructParams)) {
            $rule['constructParams'] = $constructParams;
        }
        if (!is_null($shareInstances)) {
            $rule['shareInstances'] = array_merge($rule['shareInstances'] ?? [], $shareInstances);
        }
        $this->addRule($id, $rule);

        return $this;
    }

    final public function bind(
        string $id,
        ?string $instanceOf = null,
        ?array $constructParams = null,
        ?array $shareInstances = null
    ) {
        return $this->_bind($id, $instanceOf, $constructParams, $shareInstances);
    }

    final public function bindIf(
        string $id,
        ?string $instanceOf = null,
        ?array $constructParams = null,
        ?array $shareInstances = null
    ) {
        if (!$this->Dice->hasRule($id)) {
            return $this->_bind($id, $instanceOf, $constructParams, $shareInstances);
        }

        return $this;
    }

    final public function singleton(
        string $id,
        ?string $instanceOf = null,
        ?array $constructParams = null,
        ?array $shareInstances = null
    ) {
        return $this->_bind($id, $instanceOf, $constructParams, $shareInstances, ['shared' => true]);
    }

    final public function singletonIf(
        string $id,
        ?string $instanceOf = null,
        ?array $constructParams = null,
        ?array $shareInstances = null
    ) {
        if (!$this->Dice->hasRule($id)) {
            return $this->_bind($id, $instanceOf, $constructParams, $shareInstances, ['shared' => true]);
        }

        return $this;
    }

    final public function service(
        string $id,
        ?array $services = null,
        ?array $exceptServices = null,
        int $lifetime = ServiceLifetime::INHERIT
    ) {
        if (!is_subclass_of($id, IService::class)) {
            throw new UnexpectedValueException($id . ' does not implement ' . IService::class);
        }
        $this->applyService($id, $services, $exceptServices, $lifetime);
        $this->Services[$id] = true;

        return $this;
    }

    /**
     * @param class-string[]|null $services
     * @param class-string[]|null $exceptServices
     * @param int-mask-of<ServiceLifetime::*> $lifetime
     */
    private function applyService(
        string $id,
        ?array $services = null,
        ?array $exceptServices = null,
        int $lifetime = ServiceLifetime::INHERIT
    ): void {
        if ($lifetime & ServiceLifetime::INHERIT) {
            $lifetime = 0;
            if (is_a($id, IServiceSingleton::class, true)) {
                $lifetime |= ServiceLifetime::SINGLETON;
            }
            if (is_a($id, IServiceShared::class, true)) {
                $lifetime |= ServiceLifetime::SERVICE_SINGLETON;
            }
        }

        $rule = [];
        if ($lifetime & ServiceLifetime::SINGLETON) {
            $rule['shared'] = true;

            // If SINGLETON and SERVICE_SINGLETON are both set, disable
            // inheritance to keep service instances separate
            if ($lifetime & ServiceLifetime::SERVICE_SINGLETON) {
                $rule['inherit'] = false;
            }
        }
        if ($bindings = $id::getContextualBindings()) {
            $rule['substitutions'] = $bindings;
        }
        if ($rule) {
            $this->addRule($id, $rule);
        }

        $bind = $id::getServices();
        if (!is_null($services)) {
            $bind = array_intersect($bind, $services = array_unique($services));
            if (count($bind) < count($services)) {
                throw new UnexpectedValueException($id . ' does not implement: ' . implode(', ', array_diff($services, $bind)));
            }
        }
        if (!is_null($exceptServices)) {
            $bind = array_diff($bind, $exceptServices);
        }

        if (!$bind) {
            return;
        }

        $rule = [
            'instanceOf' => $id
        ];
        if ($lifetime & ServiceLifetime::SERVICE_SINGLETON) {
            $rule['shared'] = true;
        }
        foreach ($bind as $service) {
            $this->addRule($service, $rule);
        }
    }

    final public function instance(string $id, $instance)
    {
        $this->Dice = $this->Dice->addShared($id, $instance);

        return $this;
    }

    final public function instanceIf(string $id, $instance)
    {
        if (!$this->Dice->hasRule($id)) {
            return $this->instance($id, $instance);
        }

        return $this;
    }

    final public function services(array $serviceMap, int $lifetime = ServiceLifetime::INHERIT)
    {
        $idMap = [];
        foreach ($serviceMap as $id => $instanceOf) {
            if (is_int($id)) {
                $idMap[$instanceOf] = $idMap[$instanceOf] ?? [];
                continue;
            }
            $idMap[$instanceOf][] = $id;
        }

        foreach ($idMap as $instanceOf => $services) {
            $this->service($instanceOf, $services, null, $lifetime);
        }

        return $this;
    }

    final public function getServices(): array
    {
        return array_keys($this->Services);
    }

    final public function unbind(string $id)
    {
        $this->Dice = $this->Dice->removeRule($id);

        return $this;
    }

    /**
     * 0 if another container has the same bindings, otherwise 1
     *
     * @param static $container
     */
    private function compareBindingsWith($container): int
    {
        return $this->Dice === $container->Dice ? 0 : 1;
    }
}
