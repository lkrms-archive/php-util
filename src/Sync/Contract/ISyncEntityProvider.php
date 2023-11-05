<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\ReturnsProvider;
use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Support\Catalog\TextComparisonAlgorithm;
use Lkrms\Support\Catalog\TextComparisonFlag;
use Lkrms\Sync\Catalog\HydrationFlag;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Support\DeferredRelationship;

/**
 * Provides an entity-agnostic interface to an ISyncProvider's implementation of
 * sync operations for an entity
 *
 * @template TEntity of ISyncEntity
 *
 * @extends ReturnsProvider<ISyncProvider>
 */
interface ISyncEntityProvider extends ReturnsProvider
{
    /**
     * Get the sync entity being serviced
     *
     * @return class-string<TEntity>
     */
    public function entity(): string;

    /**
     * Perform an arbitrary sync operation on one or more backend entities
     *
     * @internal
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>|TEntity
     * @phpstan-return (
     *     $operation is SyncOperation::*_LIST
     *     ? FluentIteratorInterface<array-key,TEntity>
     *     : TEntity
     * )
     */
    public function run($operation, ...$args);

    /**
     * Add an entity to the backend
     *
     * @param TEntity $entity
     * @param mixed ...$args
     * @return TEntity
     */
    public function create($entity, ...$args): ISyncEntity;

    /**
     * Get an entity from the backend
     *
     * @param int|string|null $id
     * @param mixed ...$args
     * @return TEntity
     */
    public function get($id, ...$args): ISyncEntity;

    /**
     * Update an entity in the backend
     *
     * @param TEntity $entity
     * @param mixed ...$args
     * @return TEntity
     */
    public function update($entity, ...$args): ISyncEntity;

    /**
     * Delete an entity from the backend
     *
     * @param TEntity $entity
     * @param mixed ...$args
     * @return TEntity
     */
    public function delete($entity, ...$args): ISyncEntity;

    /**
     * Add a list of entities to the backend
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function createList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Get a list of entities from the backend
     *
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function getList(...$args): FluentIteratorInterface;

    /**
     * Update a list of entities in the backend
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function updateList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Delete a list of entities from the backend
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function deleteList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Perform an arbitrary sync operation on a list of backend entities and
     * return an array
     *
     * @internal
     * @param SyncOperation::*_LIST $operation
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function runA($operation, ...$args): array;

    /**
     * Add a list of entities to the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function createListA(iterable $entities, ...$args): array;

    /**
     * Get a list of entities from the backend as an array
     *
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function getListA(...$args): array;

    /**
     * Update a list of entities in the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function updateListA(iterable $entities, ...$args): array;

    /**
     * Delete a list of entities from the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function deleteListA(iterable $entities, ...$args): array;

    /**
     * Use a property of the entity class to resolve names to entities
     *
     * @param int-mask-of<TextComparisonAlgorithm::*|TextComparisonFlag::*> $algorithm
     * @param array<TextComparisonAlgorithm::*,float>|float|null $uncertaintyThreshold
     * @param string|null $weightProperty If multiple entities are equally
     * similar to a given name, the one with the highest weight is preferred.
     * @return ISyncEntityResolver<TEntity>
     */
    public function getResolver(
        ?string $nameProperty = null,
        int $algorithm = TextComparisonAlgorithm::SAME,
        $uncertaintyThreshold = null,
        ?string $weightProperty = null,
        bool $requireOneMatch = false
    ): ISyncEntityResolver;

    /**
     * Perform sync operations on the backend directly, ignoring any entities in
     * the entity store
     *
     * @return $this
     */
    public function online();

    /**
     * Perform "get" operations on the entity store, throwing an exception if
     * entities have never been synced with the backend
     *
     * @return $this
     */
    public function offline();

    /**
     * Do not resolve entities or relationships deferred while performing sync
     * operations on the backend
     *
     * @return $this
     */
    public function withoutResolvingDeferrals();

    /**
     * Do not hydrate entities returned by the backend
     *
     * @param bool $lazy If `true`, {@see HydrationFlag::LAZY} is applied
     * instead of {@see HydrationFlag::SUPPRESS}, and entities may be returned
     * with unresolved {@see DeferredRelationship} objects.
     * @return $this
     */
    public function withoutHydration(bool $lazy = false);

    /**
     * Apply hydration flags to sync operations performed on backend entities
     *
     * @param int-mask-of<HydrationFlag::*> $flags
     * @param class-string<ISyncEntity>|null $entity
     * @param int<1,max>|null $depth
     * @return $this
     */
    public function withHydration(
        int $flags = HydrationFlag::EAGER,
        bool $replace = true,
        ?string $entity = null,
        ?int $depth = null
    );
}
