<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Iterator\IterableIterator;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Introspector;
use Generator;
use LogicException;

/**
 * Implements IProvidable to represent an external entity
 *
 * @template TProvider of IProvider
 * @template TContext of IProviderContext
 *
 * @see \Lkrms\Contract\IProvidable
 */
trait TProvidable
{
    /**
     * @var TProvider|null
     */
    private $Provider;

    /**
     * @var TContext|null
     */
    private $Context;

    /**
     * @var class-string|null
     */
    private $Service;

    /**
     * @param TProvider $provider
     * @return $this
     */
    final public function setProvider(IProvider $provider)
    {
        if ($this->Provider) {
            throw new LogicException('Provider already set');
        }
        $this->Provider = $provider;

        return $this;
    }

    /**
     * @return TProvider|null
     */
    final public function provider(): ?IProvider
    {
        return $this->Provider;
    }

    /**
     * @return TProvider
     */
    final public function requireProvider(): IProvider
    {
        if (!$this->Provider) {
            throw new LogicException('Provider required');
        }
        return $this->Provider;
    }

    /**
     * @param TContext $context
     * @return $this
     */
    final public function setContext(IProviderContext $context)
    {
        $this->Context = $context;

        return $this;
    }

    /**
     * @return TContext|null
     */
    final public function context(): ?IProviderContext
    {
        return $this->Context;
    }

    /**
     * @param class-string $service
     * @return $this
     */
    final public function setService(string $service)
    {
        $this->Service = $service;

        return $this;
    }

    /**
     * @return class-string
     */
    final public function service(): string
    {
        return $this->Service ?? static::class;
    }

    /**
     * @return TContext
     */
    final public function requireContext(): IProviderContext
    {
        if (!$this->Context) {
            throw new LogicException('Context required');
        }

        return $this->Context;
    }

    /**
     * Create an instance of the class from an array on behalf of a provider
     *
     * The constructor (if any) is invoked with values from `$data`. If `$data`
     * values remain, they are assigned to writable properties. If further
     * values remain and the class implements
     * {@see \Lkrms\Contract\IExtensible}, they are assigned via
     * {@see \Lkrms\Contract\IExtensible::setMetaProperty()}.
     *
     * `$data` keys, constructor parameters and writable properties are
     * normalised for comparison.
     *
     * @param mixed[] $data
     * @param TProvider $provider
     * @param TContext|null $context
     * @return static
     */
    final public static function provide(
        array $data,
        IProvider $provider,
        ?IProviderContext $context = null
    ) {
        /** @var IContainer */
        $container = $context
            ? $context->container()
            : $provider->container();
        $container = $container->inContextOf(get_class($provider));

        $context = $context
            ? $context->withContainer($container)
            : $provider->getContext($container);

        $closure = Introspector::getService(
            $container, static::class
        )->getCreateProvidableFromClosure();

        return $closure($data, $provider, $context);
    }

    /**
     * Create instances of the class from arrays on behalf of a provider
     *
     * See {@see TProvidable::provide()} for more information.
     *
     * @param iterable<array-key,mixed[]> $list
     * @param TProvider $provider
     * @param ArrayKeyConformity::* $conformity
     * @param TContext|null $context
     * @return FluentIteratorInterface<array-key,static>
     */
    final public static function provideList(
        iterable $list,
        IProvider $provider,
        $conformity = ArrayKeyConformity::NONE,
        ?IProviderContext $context = null
    ): FluentIteratorInterface {
        return IterableIterator::from(
            self::_provideList($list, $provider, $conformity, $context)
        );
    }

    /**
     * @param iterable<array-key,mixed[]> $list
     * @param TProvider $provider
     * @param ArrayKeyConformity::* $conformity
     * @param TContext|null $context
     * @return Generator<array-key,static>
     */
    private static function _provideList(
        iterable $list,
        IProvider $provider,
        $conformity,
        ?IProviderContext $context
    ): Generator {
        /** @var IContainer */
        $container = $context
            ? $context->container()
            : $provider->container();
        $container = $container->inContextOf(get_class($provider));

        /** @var IProviderContext */
        $context = $context
            ? $context->withContainer($container)
            : $provider->getContext($container);
        $context = $context->withConformity($conformity);

        $introspector = Introspector::getService($container, static::class);

        foreach ($list as $key => $data) {
            if (!isset($closure)) {
                $closure =
                    in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE], true)
                        ? $introspector->getCreateProvidableFromSignatureClosure(array_keys($data))
                        : $introspector->getCreateProvidableFromClosure();
            }

            yield $key => $closure($data, $provider, $context);
        }
    }
}
