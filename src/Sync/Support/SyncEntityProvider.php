<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Contract\IContainer;
use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Iterator\IterableIterator;
use Lkrms\Support\Catalog\TextComparisonAlgorithm;
use Lkrms\Sync\Catalog\DeferredSyncEntityPolicy as DeferredEntityPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Exception\SyncOperationNotImplementedException;
use Generator;
use LogicException;

/**
 * An interface to an ISyncProvider's implementation of sync operations for an
 * ISyncEntity class
 *
 * So you can do this:
 *
 * ```php
 * <?php
 * $faculties = $provider->with(Faculty::class)->getList();
 * ```
 *
 * or, if a `Faculty` provider is bound to the current global container:
 *
 * ```php
 * <?php
 * $faculties = Faculty::withDefaultProvider()->getList();
 * ```
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of ISyncProvider
 * @implements ISyncEntityProvider<TEntity>
 */
final class SyncEntityProvider implements ISyncEntityProvider
{
    /**
     * @var class-string<TEntity>
     */
    private $Entity;

    /**
     * @var TProvider
     */
    private $Provider;

    /**
     * @var ISyncDefinition<TEntity,TProvider>
     */
    private $Definition;

    /**
     * @var ISyncContext
     */
    private $Context;

    /**
     * @var SyncStore
     */
    private $Store;

    /**
     * @var bool|null
     * @phpstan-ignore-next-line
     */
    private $Offline;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param ISyncDefinition<TEntity,TProvider> $definition
     * @param ISyncContext|null $context
     */
    public function __construct(
        IContainer $container,
        string $entity,
        ISyncProvider $provider,
        ISyncDefinition $definition,
        ?ISyncContext $context = null
    ) {
        if (!is_a($entity, ISyncEntity::class, true)) {
            throw new LogicException("Does not implement ISyncEntity: $entity");
        }

        $entityProvider = SyncIntrospector::entityToProvider($entity);
        if (!($provider instanceof $entityProvider)) {
            throw new LogicException(get_class($provider) . ' does not implement ' . $entityProvider);
        }

        $this->Entity = $entity;
        $this->Provider = $provider;
        $this->Definition = $definition;
        $this->Context = $context ?? $provider->getContext($container);
        $this->Store = $provider->store();
    }

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return iterable<TEntity>|TEntity
     * @phpstan-return (
     *     $operation is SyncOperation::*_LIST
     *     ? iterable<TEntity>
     *     : TEntity
     * )
     */
    private function _run($operation, ...$args)
    {
        $closure =
            $this
                ->Definition
                ->getSyncOperationClosure($operation);

        if (!$closure) {
            throw new SyncOperationNotImplementedException(
                $this->Provider,
                $this->Entity,
                $operation
            );
        }

        return $closure(
            $this->Context->withArgs($operation, ...$args),
            ...$args
        );
    }

    /**
     * @inheritDoc
     */
    public function run($operation, ...$args)
    {
        $fromCheckpoint = $this->Store->getDeferredEntityCheckpoint();

        if (!SyncOperation::isList($operation)) {
            $result = $this->_run($operation, ...$args);
            if ($this->Context->getDeferredSyncEntityPolicy() !==
                    DeferredEntityPolicy::DO_NOT_RESOLVE) {
                $this->resolveDeferredEntities($fromCheckpoint);
            }
            return $result;
        }

        switch ($this->Context->getDeferredSyncEntityPolicy()) {
            case DeferredEntityPolicy::DO_NOT_RESOLVE:
                $result = $this->_run($operation, ...$args);
                break;

            case DeferredEntityPolicy::RESOLVE_EARLY:
                $result = $this->resolveDeferredEntitiesBeforeYield($fromCheckpoint, $operation, ...$args);
                break;

            case DeferredEntityPolicy::RESOLVE_LATE:
                $result = $this->resolveDeferredEntitiesAfterRun($fromCheckpoint, $operation, ...$args);
                break;
        }

        if (!($result instanceof FluentIteratorInterface)) {
            return new IterableIterator($result);
        }

        return $result;
    }

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return Generator<TEntity>
     */
    private function resolveDeferredEntitiesBeforeYield(int $fromCheckpoint, $operation, ...$args): Generator
    {
        foreach ($this->_run($operation, ...$args) as $key => $entity) {
            $this->resolveDeferredEntities($fromCheckpoint);
            $fromCheckpoint = $this->Store->getDeferredEntityCheckpoint();
            yield $key => $entity;
        }
    }

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return Generator<TEntity>
     */
    private function resolveDeferredEntitiesAfterRun(int $fromCheckpoint, $operation, ...$args): Generator
    {
        yield from $this->_run($operation, ...$args);
        $this->resolveDeferredEntities($fromCheckpoint);
    }

    private function resolveDeferredEntities(int $fromCheckpoint): void
    {
        while ($this->Store->resolveDeferredEntities($fromCheckpoint)) {
            $fromCheckpoint = $this->Store->getDeferredEntityCheckpoint();
        }
    }

    /**
     * Defer retrieval of an entity from the backend
     *
     * @param int|string $id
     * @param ISyncEntity|DeferredSyncEntity|null $replace A reference to the
     * variable, property or array element to replace when the entity is
     * resolved.
     */
    public function defer($id, &$replace): void
    {
        DeferredSyncEntity::defer($this->Provider, $this->Context, $this->Entity, $id, $replace);
    }

    /**
     * Defer retrieval of a list of entities from the backend
     *
     * @param int[]|string[] $idList
     * @param array<ISyncEntity|DeferredSyncEntity>|null $replace A reference to
     * the variable, property or array element to replace when the list is
     * resolved.
     */
    public function deferList(array $idList, &$replace): void
    {
        DeferredSyncEntity::deferList($this->Provider, $this->Context, $this->Entity, $idList, $replace);
    }

    /**
     * Add an entity to the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::CREATE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1.
     * public function createFaculty(ISyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function create_Faculty(ISyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being created
     * - must be required
     */
    public function create($entity, ...$args): ISyncEntity
    {
        return $this->run(SyncOperation::CREATE, $entity, ...$args);
    }

    /**
     * Get an entity from the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::READ} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1.
     * public function getFaculty(ISyncContext $ctx, $id): Faculty;
     *
     * // 2.
     * public function get_Faculty(ISyncContext $ctx, $id): Faculty;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must not have a native type declaration, but may be tagged as an
     *   `int|string|null` parameter for static analysis purposes
     * - must be nullable
     *
     * @param int|string|null $id
     */
    public function get($id, ...$args): ISyncEntity
    {
        return $this->run(SyncOperation::READ, $id, ...$args);
    }

    /**
     * Update an entity in the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::UPDATE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1.
     * public function updateFaculty(ISyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function update_Faculty(ISyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being updated
     * - must be required
     */
    public function update($entity, ...$args): ISyncEntity
    {
        return $this->run(SyncOperation::UPDATE, $entity, ...$args);
    }

    /**
     * Delete an entity from the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::DELETE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1.
     * public function deleteFaculty(ISyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function delete_Faculty(ISyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being deleted
     * - must be required
     *
     * The return value:
     * - must represent the final state of the entity before it was deleted
     */
    public function delete($entity, ...$args): ISyncEntity
    {
        return $this->run(SyncOperation::DELETE, $entity, ...$args);
    }

    /**
     * Add a list of entities to the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::CREATE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1. With a plural entity name
     * public function createFaculties(ISyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function createList_Faculty(ISyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function createList(iterable $entities, ...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::CREATE_LIST, $entities, ...$args);
    }

    /**
     * Get a list of entities from the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::READ_LIST} operation, e.g. one of the following for
     * a `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1. With a plural entity name
     * public function getFaculties(ISyncContext $ctx): iterable;
     *
     * // 2. With a singular name
     * public function getList_Faculty(ISyncContext $ctx): iterable;
     * ```
     *
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function getList(...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::READ_LIST, ...$args);
    }

    /**
     * Update a list of entities in the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::UPDATE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1. With a plural entity name
     * public function updateFaculties(ISyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function updateList_Faculty(ISyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function updateList(iterable $entities, ...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::UPDATE_LIST, $entities, ...$args);
    }

    /**
     * Delete a list of entities from the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::DELETE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1. With a plural entity name
     * public function deleteFaculties(ISyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function deleteList_Faculty(ISyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * The return value:
     * - must represent the final state of the entities before they were deleted
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function deleteList(iterable $entities, ...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::DELETE_LIST, $entities, ...$args);
    }

    public function runA($operation, ...$args): array
    {
        if (!SyncOperation::isList($operation)) {
            throw new LogicException('Not a *_LIST operation: ' . $operation);
        }

        $fromCheckpoint = $this->Store->getDeferredEntityCheckpoint();

        $result = $this->_run($operation, ...$args);
        if (!is_array($result)) {
            $result = iterator_to_array($result);
        }

        if ($this->Context->getDeferredSyncEntityPolicy() !==
                DeferredEntityPolicy::DO_NOT_RESOLVE) {
            $this->resolveDeferredEntities($fromCheckpoint);
        }

        return $result;
    }

    public function createListA(iterable $entities, ...$args): array
    {
        return $this->runA(SyncOperation::CREATE_LIST, $entities, ...$args);
    }

    public function getListA(...$args): array
    {
        return $this->runA(SyncOperation::READ_LIST, ...$args);
    }

    public function updateListA(iterable $entities, ...$args): array
    {
        return $this->runA(SyncOperation::UPDATE_LIST, $entities, ...$args);
    }

    public function deleteListA(iterable $entities, ...$args): array
    {
        return $this->runA(SyncOperation::DELETE_LIST, $entities, ...$args);
    }

    public function online()
    {
        $this->Offline = false;

        return $this;
    }

    public function offline()
    {
        $this->Offline = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getResolver(
        string $nameProperty,
        int $algorithm = TextComparisonAlgorithm::SAME,
        $uncertaintyThreshold = null,
        ?string $weightProperty = null,
        bool $requireOneMatch = false
    ): ISyncEntityResolver {
        if ($algorithm === TextComparisonAlgorithm::SAME && !$requireOneMatch) {
            return new SyncEntityResolver($this, $nameProperty);
        }
        return new SyncEntityFuzzyResolver(
            $this,
            $nameProperty,
            $algorithm,
            $uncertaintyThreshold,
            $weightProperty,
            $requireOneMatch,
        );
    }

    /**
     * @deprecated Use {@see SyncEntityProvider::getResolver()} instead
     *
     * @return SyncEntityFuzzyResolver<TEntity>
     */
    public function getFuzzyResolver(
        string $nameProperty,
        ?string $weightProperty,
        int $algorithm = TextComparisonAlgorithm::LEVENSHTEIN,
        ?float $uncertaintyThreshold = null
    ): SyncEntityFuzzyResolver {
        return new SyncEntityFuzzyResolver(
            $this,
            $nameProperty,
            $algorithm,
            $uncertaintyThreshold,
            $weightProperty,
        );
    }
}
