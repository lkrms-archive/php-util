<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Closure;
use DateTimeInterface;
use Lkrms\Concern\RequiresContainer;
use Lkrms\Concern\TConstructible;
use Lkrms\Concern\TExtensible;
use Lkrms\Concern\TProvidable;
use Lkrms\Concern\TReadable;
use Lkrms\Concern\TResolvable;
use Lkrms\Concern\TWritable;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Reflect;
use Lkrms\Facade\Sync;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\NormaliserFlag;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Support\Iterator\IterableIterator;
use Lkrms\Sync\Catalog\SyncSerializeLinkType as SerializeLinkType;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\DeferredSyncEntity;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncIntrospector as IS;
use Lkrms\Sync\Support\SyncSerializeRules as SerializeRules;
use Lkrms\Sync\Support\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Test;
use RuntimeException;
use UnexpectedValueException;

/**
 * Represents the state of an entity in an external system
 *
 * {@see SyncEntity} implements {@see \Lkrms\Contract\IReadable} and
 * {@see \Lkrms\Contract\IWritable}, but `protected` properties are not
 * accessible by default. Override {@see SyncEntity::getReadable()} and/or
 * {@see SyncEntity::getWritable()} to change this.
 *
 * The following "magic" property methods are discovered automatically and don't
 * need to be returned by {@see SyncEntity::getReadable()} or
 * {@see SyncEntity::getWritable()}:
 * - `protected function _get<PropertyName>()`
 * - `protected function _isset<PropertyName>()` (optional; falls back to
 *   `_get<PropertyName>()` if not declared)
 * - `protected function _set<PropertyName>($value)`
 * - `protected function _unset<PropertyName>()` (optional; falls back to
 *   `_set<PropertyName>(null)` if not declared)
 *
 * Accessible properties are mapped to associative arrays with snake_case keys
 * when {@see SyncEntity} objects are serialized. Override
 * {@see SyncEntity::buildSerializeRules()} to provide serialization rules for
 * nested entities.
 *
 */
abstract class SyncEntity implements ISyncEntity
{
    /**
     * @use TProvidable<ISyncProvider,ISyncContext>
     */
    use TResolvable, TConstructible, TReadable, TWritable, TExtensible, TProvidable, RequiresContainer;

    /**
     * The unique identifier assigned to the entity by its provider
     *
     * @var int|string|null
     */
    public $Id;

    /**
     * The unique identifier assigned to the entity by its canonical backend
     *
     * A {@see SyncEntity}'s canonical backend is the provider regarded as the
     * "single source of truth" for its underlying {@see TProvidable::service()}
     * and any properties that aren't "owned" by another provider.
     *
     * To improve the accuracy and performance of sync operations, providers
     * should propagate this value to and from backends capable of storing it,
     * but this is not strictly required.
     *
     * @var int|string|null
     */
    public $CanonicalId;

    /**
     * @var ISyncProvider|null
     */
    private $_Provider;

    /**
     * @var ISyncContext|null
     */
    private $_Context;

    /**
     * @var bool
     */
    private $DoNotHydrate = false;

    /**
     * Class name => entity type ID
     *
     * @var array<string,int>
     */
    private static $TypeId = [];

    /**
     * Class name => [ Property name => normalised property name ]
     *
     * @var array<string,array<string,string>>
     */
    private static $Normalised = [];

    public static function plural(): string
    {
        return Convert::nounToPlural(Convert::classToBasename(static::class));
    }

    public static function getDateProperties(): array
    {
        return [];
    }

    public function name(): ?string
    {
        return IS::get(static::class)->getGetNameClosure()($this);
    }

    public function description(): ?string
    {
        return null;
    }

    final public function id()
    {
        return $this->Id;
    }

    final public function canonicalId()
    {
        return $this->CanonicalId;
    }

    /**
     * Override to specify how the object graph below this entity type should be
     * serialized
     *
     * To prevent infinite recursion when `json_encode()` or similar is used to
     * serialize an instance of this class, return a
     * {@see SerializeRulesBuilder} object configured to remove or replace
     * circular references.
     *
     * @param SerializeRulesBuilder<static> $rulesB
     * @return SerializeRulesBuilder<static>
     */
    protected static function buildSerializeRules(SerializeRulesBuilder $rulesB): SerializeRulesBuilder
    {
        return $rulesB;
    }

    /**
     * Return prefixes to remove when normalising field/property names
     *
     * Entity names are removed by default, e.g. for an `AdminUser` class that
     * extends a {@see SyncEntity} subclass called `User`, prefixes "AdminUser"
     * and "User" are removed to ensure fields like "USER_ID" and
     * "ADMIN_USER_NAME" match with properties like "Id" and "Name".
     *
     * Return `null` to suppress prefix removal.
     *
     * @return string[]|null
     */
    protected static function getRemovablePrefixes(): ?array
    {
        return array_map(
            fn(string $name): string => Convert::classToBasename($name),
            Reflect::getClassesBetween(static::class, self::class, false)
        );
    }

    final public static function defaultProvider(?IContainer $container = null): ISyncProvider
    {
        return self::requireContainer($container)
            ->get(IS::entityToProvider(static::class));
    }

    final public static function withDefaultProvider(?IContainer $container = null): ISyncEntityProvider
    {
        return static::defaultProvider()->with(static::class);
    }

    final public static function getSerializeRules(?IContainer $container = null): SerializeRules
    {
        $rulesB = static::buildSerializeRules(
            SerializeRulesBuilder::build(self::requireContainer($container))
                ->entity(static::class)
        );

        return $rulesB->go();
    }

    /**
     * Get a closure to normalise property names
     *
     * Prefixes returned by {@see SyncEntity::getRemovablePrefixes()} are
     * removed from `$name` unless `$greedy` is `false`. Otherwise, if `$hints`
     * are provided and `$name` matches one of them after snake_case conversion,
     * prefix removal is skipped.
     *
     */
    final public static function normaliser(): Closure
    {
        // If there aren't any prefixes to remove, return a closure that
        // converts everything to snake_case
        if (!($prefixes = static::getRemovablePrefixes())) {
            return
                static function (string $name): string {
                    return self::$Normalised[static::class][$name]
                        ?? (self::$Normalised[static::class][$name] =
                            Convert::toSnakeCase($name));
                };
        }

        // ['admin_user_group'] -> ['admin_user_group', 'user_group', 'group']
        foreach ($prefixes as $prefix) {
            $prefix = Convert::toSnakeCase($prefix);
            $expanded[$prefix] = true;
            $prefix = explode('_', $prefix);
            while (array_shift($prefix)) {
                $expanded[implode('_', $prefix)] = true;
            }
        }
        $prefixes = array_keys($expanded);
        $regex = implode('|', $prefixes);
        $regex = count($prefixes) > 1 ? "($regex)" : $regex;
        $regex = "/^{$regex}_/";

        return
            static function (string $name, bool $greedy = true, string ...$hints) use ($regex): string {
                if ($greedy && !$hints) {
                    return self::$Normalised[static::class][$name]
                        ?? (self::$Normalised[static::class][$name] =
                            preg_replace($regex, '', Convert::toSnakeCase($name)));
                }
                $_name = Convert::toSnakeCase($name);
                if (!$greedy || in_array($_name, $hints)) {
                    return $_name;
                }

                return preg_replace($regex, '', $_name);
            };
    }

    final public function toArray(): array
    {
        return $this->_toArray(static::getSerializeRules());
    }

    final public function toArrayWith($rules): array
    {
        return $this->_toArray(SerializeRules::resolve($rules));
    }

    final public function toLink(int $type = SerializeLinkType::DEFAULT, bool $compact = true): array
    {
        switch ($type) {
            case SerializeLinkType::INTERNAL:
            case SerializeLinkType::DEFAULT:
                return [
                    '@type' => $this->typeUri($compact),
                    '@id' => is_null($this->Id)
                        ? spl_object_id($this)
                        : $this->Id,
                ];

            case SerializeLinkType::COMPACT:
                return [
                    '@id' => $this->uri($compact),
                ];

            case SerializeLinkType::FRIENDLY:
                return array_filter([
                    '@type' => $this->typeUri($compact),
                    '@id' => is_null($this->Id)
                        ? spl_object_id($this)
                        : $this->Id,
                    '@name' => $this->name(),
                    '@description' => $this->description(),
                ]);
        }

        throw new UnexpectedValueException("Invalid link type: $type");
    }

    final public function uri(bool $compact = true): string
    {
        return sprintf(
            '%s/%s',
            $this->typeUri($compact),
            is_null($this->Id)
                ? spl_object_id($this)
                : $this->Id
        );
    }

    /**
     * Return true if the value of a property is the same between this and
     * another instance of the same class
     *
     * @param string $property
     * @param SyncEntity $entity
     * @return bool
     */
    final public function propertyHasSameValueAs(string $property, SyncEntity $entity): bool
    {
        // $entity must be an instance of the same class
        if (!is_a($entity, static::class)) {
            return false;
        }

        $a = $this->$property;
        $b = $entity->$property;

        return $a === $b ||
            ($a instanceof SyncEntity && $b instanceof SyncEntity &&
                get_class($a) == get_class($b) &&
                $a->provider() === $b->provider() &&
                $a->Id === $b->Id);
    }

    final protected function store(): SyncStore
    {
        return $this->_Provider
            ? $this->_Provider->store()
            : Sync::getInstance();
    }

    /**
     * Defer instantiation of an entity or list of entities
     *
     * @param int|string|int[]|string[] $deferred An entity ID or list thereof.
     * @param mixed $replace A reference to the variable to replace when the
     * entity or list is resolved. Do not assign anything else to it after
     * calling this method.
     */
    final protected function defer($deferred, &$replace, ?string $entity = null): void
    {
        DeferredSyncEntity::defer(
            $this->provider(),
            $this->requireContext()->push($this),
            $entity ?: static::class,
            $deferred,
            $replace
        );
    }

    /**
     * If true, data should not be retrieved to hydrate the entity or resolve
     * any deferred values
     *
     * Used in scenarios where retrieving additional data is unnecessary and
     * would be wasteful or slow, e.g. during serialization.
     *
     */
    final protected function doNotHydrate(): bool
    {
        return $this->DoNotHydrate;
    }

    private function typeUri(bool $compact): string
    {
        return $this->store()->getEntityTypeUri($this->service(), $compact)
            ?: '/' . str_replace('\\', '/', ltrim($this->service(), '\\'));
    }

    private function objectId(): string
    {
        return implode("\0", [
            $this->service(),
            $this->Id ?: spl_object_id($this),
            $this->_Provider ? $this->_Provider->getProviderHash() : null,
        ]);
    }

    /**
     * @param SerializeRules<static> $rules
     * @return array<string,mixed>
     */
    private function _toArray(SerializeRules $rules): array
    {
        $array = $this;
        $this->_serialize($array, [], $rules);

        return (array) $array;
    }

    /**
     * @param SyncEntity|DeferredSyncEntity|mixed[] $node
     * @param string[] $path
     * @param SerializeRules<static> $rules
     * @param array<string,true> $parents
     */
    private function _serialize(&$node, array $path, SerializeRules $rules, array $parents = []): void
    {
        if (!is_null($maxDepth = $rules->getMaxDepth()) && count($path) > $maxDepth) {
            throw new RuntimeException('In too deep: ' . implode('.', $path));
        }

        if ($node instanceof DeferredSyncEntity) {
            $node = $node->toLink($rules->getFlags() & SerializeRules::SYNC_STORE
                ? SerializeLinkType::INTERNAL
                : SerializeLinkType::DEFAULT);

            return;
        }

        if ($node instanceof SyncEntity) {
            if ($path && $rules->getFlags() & SerializeRules::SYNC_STORE) {
                $node = $node->toLink(SerializeLinkType::INTERNAL);

                return;
            }

            if ($rules->getDetectRecursion()) {
                // Prevent infinite recursion by replacing each SyncEntity
                // descended from itself with a link
                if ($parents[$node->objectId()] ?? false) {
                    $node = $node->toLink(SerializeLinkType::DEFAULT);
                    $node['@why'] = 'Circular reference detected';

                    return;
                }
                $parents[$node->objectId()] = true;
            }

            $class = get_class($node);
            $node = $node->serialize($rules);
        }

        $delete = $rules->getRemove($class ?? null, null, $path);
        $replace = $rules->getReplace($class ?? null, null, $path);

        // Don't delete values returned in both lists
        $delete = array_diff_key($delete, $replace);

        if ($delete) {
            $node = array_diff_key($node, array_flip($delete));
        }
        foreach ($replace as $rule) {
            if (is_array($rule)) {
                $_rule = $rule;
                $key = array_shift($rule);
                $newKey = $key;
                $callback = null;

                while ($rule) {
                    $arg = array_shift($rule);
                    if (is_string($arg)) {
                        $newKey = IS::get(static::class)->maybeNormalise($arg, NormaliserFlag::CAREFUL);
                        continue;
                    }
                    if ($arg instanceof Closure) {
                        $callback = $arg;
                        continue;
                    }
                    throw new UnexpectedValueException('Invalid rule: ' . var_export($_rule, true));
                }

                if ($key !== '[]') {
                    if (!array_key_exists($key, $node)) {
                        continue;
                    }

                    if ($key !== $newKey) {
                        if (array_key_exists($newKey, $node)) {
                            throw new UnexpectedValueException("Cannot rename '$key': '$newKey' already set");
                        }
                        Convert::arraySpliceAtKey($node, $key, 1, [$newKey => $node[$key]]);
                        $key = $newKey;
                    }

                    if ($callback) {
                        $node[$key] = $callback($node[$key]);

                        continue;
                    }
                } elseif ($callback) {
                    $node = array_map($callback, $node);

                    continue;
                }
            } else {
                $key = $rule;
            }

            if ($key === '[]') {
                $_path = $path;
                $lastKey = array_pop($_path);
                $_path[] = $lastKey . '[]';

                foreach ($node as &$child) {
                    $this->_serializeId($child, $_path);
                }
                unset($child);

                continue;
            }

            if (!array_key_exists($key, $node)) {
                continue;
            }

            $_path = $path;
            $_path[] = $key;
            $this->_serializeId($node[$key], $_path);
        }

        if (is_array($node)) {
            if (Test::isIndexedArray($node)) {
                $isList = true;
                $lastKey = array_pop($path);
                $path[] = $lastKey . '[]';
            }
            foreach ($node as $key => &$child) {
                if (is_null($child) || is_scalar($child)) {
                    continue;
                }
                if (!($isList ?? null)) {
                    $_path = $path;
                    $_path[] = $key;
                }
                $this->_serialize($child, $_path ?? $path, $rules, $parents);
            }
        } elseif ($node instanceof DateTimeInterface) {
            $node = ($rules->getDateFormatter()
                ?: ($this->provider() ? $this->provider()->dateFormatter() : null)
                ?: new DateFormatter())->format($node);
        } else {
            throw new UnexpectedValueException('Array or SyncEntity expected: ' . print_r($node, true));
        }
    }

    /**
     * @param SyncEntity[]|SyncEntity|null $node
     * @param string[] $path
     */
    private function _serializeId(&$node, array $path): void
    {
        if (is_null($node)) {
            return;
        }

        if (Test::isArrayOf($node, SyncEntity::class, true) && Test::isIndexedArray($node, true)) {
            /** @var SyncEntity $child */
            foreach ($node as &$child) {
                $child = $child->Id;
            }

            return;
        }

        if (!($node instanceof SyncEntity)) {
            throw new UnexpectedValueException('Cannot replace (not a SyncEntity): ' . implode('.', $path));
        }

        $node = $node->Id;
    }

    /**
     * Convert the entity to an associative array
     *
     * Nested objects and lists are returned as-is. Only the top-level entity is
     * replaced.
     *
     * @param SerializeRules<static> $rules
     * @return array<string,mixed>
     */
    private function serialize(SerializeRules $rules): array
    {
        $clone = clone $this;
        $clone->DoNotHydrate = true;
        $array = IS::get(static::class)->getSerializeClosure($rules)($clone);
        if ($rules->getRemoveCanonicalId()) {
            unset($array[IS::get(static::class)->maybeNormalise('CanonicalId', NormaliserFlag::CAREFUL)]);
        }

        return $array;
    }

    final public static function setEntityTypeId(int $entityTypeId): void
    {
        self::$TypeId[static::class] = $entityTypeId;
    }

    final public static function entityTypeId(): ?int
    {
        return self::$TypeId[static::class] ?? null;
    }

    /**
     * @internal
     *
     * @return array<string,mixed>
     */
    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param mixed[] $data
     * @param ISyncProvider $provider
     * @param ISyncContext|null $context
     */
    final public static function provide(
        array $data,
        IProvider $provider,
        ?IProviderContext $context = null
    ) {
        $container = ($context
            ? $context->container()
            : $provider->container())->inContextOf(get_class($provider));
        $context = $context
            ? $context->withContainer($container)
            : $container->get(SyncContext::class);
        $introspector = IS::getService($container, static::class);
        $closure = $introspector->getCreateSyncEntityFromClosure();

        return $closure($data, $provider, $context);
    }

    /**
     * @param iterable<array-key,mixed[]> $dataList
     * @param ISyncProvider $provider
     * @phpstan-param ArrayKeyConformity::* $conformity
     * @param ISyncContext|null $context
     * @return FluentIteratorInterface<array-key,static>
     */
    final public static function provideList(
        iterable $dataList,
        IProvider $provider,
        int $conformity = ArrayKeyConformity::NONE,
        ?IProviderContext $context = null
    ): FluentIteratorInterface {
        return IterableIterator::from(
            self::_provideList($dataList, $provider, $conformity, $context)
        );
    }

    /**
     * @param iterable<array-key,mixed[]> $dataList
     * @param ISyncProvider $provider
     * @phpstan-param ArrayKeyConformity::* $conformity
     * @param ISyncContext|null $context
     * @return iterable<array-key,static>
     */
    private static function _provideList(
        iterable $dataList,
        IProvider $provider,
        int $conformity,
        ?IProviderContext $context
    ): iterable {
        $container = ($context
            ? $context->container()
            : $provider->container())->inContextOf(get_class($provider));
        $context = ($context
            ? $context->withContainer($container)
            : $container->get(SyncContext::class))->withConformity($conformity);
        $introspector = IS::getService($container, static::class);

        foreach ($dataList as $key => $data) {
            if (!isset($closure)) {
                $closure =
                    in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                        ? $introspector->getCreateSyncEntityFromSignatureClosure(array_keys($data))
                        : $introspector->getCreateSyncEntityFromClosure();
            }

            yield $key => $closure($data, $provider, $context);
        }
    }
}
