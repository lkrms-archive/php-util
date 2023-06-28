<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;

/**
 * A fluent interface for creating HttpSyncDefinition objects
 *
 * @method static $this build(?IContainer $container = null) Create a new HttpSyncDefinitionBuilder (syntactic sugar for 'new HttpSyncDefinitionBuilder()')
 * @method $this entity(class-string<ISyncEntity> $value) The ISyncEntity being serviced
 * @method $this provider(HttpSyncProvider $value) The ISyncProvider servicing the entity
 * @method $this operations(array<SyncOperation::*> $value) A list of supported sync operations
 * @method $this path(?string $value) The path to the provider endpoint servicing the entity, e.g. "/v1/user" (see {@see HttpSyncDefinition::$Path})
 * @method $this query(mixed[]|null $value) Query parameters applied to the sync operation URL (see {@see HttpSyncDefinition::$Query})
 * @method $this headers(?ICurlerHeaders $value) HTTP headers applied to the sync operation request (see {@see HttpSyncDefinition::$Headers})
 * @method $this pager(?ICurlerPager $value) The pagination handler for the endpoint servicing the entity (see {@see HttpSyncDefinition::$Pager})
 * @method $this callback((callable(HttpSyncDefinition<ISyncEntity,HttpSyncProvider>, SyncOperation::*, ISyncContext, mixed...): HttpSyncDefinition<ISyncEntity,HttpSyncProvider>)|null $value) A callback applied to the definition before every sync operation (see {@see HttpSyncDefinition::$Callback})
 * @method $this conformity(ArrayKeyConformity::* $value) The conformity level of data returned by the provider for this entity (see {@see SyncDefinition::$Conformity})
 * @method $this filterPolicy(SyncFilterPolicy::* $value) The action to take when filters are ignored by the provider (see {@see SyncDefinition::$FilterPolicy})
 * @method $this expiry(?int $value) The time, in seconds, before responses from the provider expire (see {@see HttpSyncDefinition::$Expiry})
 * @method $this methodMap(array<SyncOperation::*,string> $value) An array that maps sync operations to HTTP request methods (see {@see HttpSyncDefinition::$MethodMap})
 * @method $this syncOneEntityPerRequest(bool $value = true) If true, perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on one entity per HTTP request (default: false)
 * @method $this overrides(array<SyncOperation::*,Closure(ISyncDefinition<ISyncEntity,HttpSyncProvider>, SyncOperation::*, ISyncContext, mixed...): mixed> $value) An array that maps sync operations to closures that override any other implementations (see {@see SyncDefinition::$Overrides})
 * @method $this pipelineFromBackend(IPipeline<mixed[],ISyncEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $value) A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required
 * @method $this pipelineToBackend(IPipeline<ISyncEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $value) A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required
 * @method $this returnEntitiesFrom(SyncEntitySource::*|null $value) Where to acquire entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 * @method mixed get(string $name) The value of $name if applied to the unresolved HttpSyncDefinition by calling $name(), otherwise null
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved HttpSyncDefinition by calling $name()
 * @method HttpSyncDefinition go() Get a new HttpSyncDefinition object
 * @method static HttpSyncDefinition resolve(HttpSyncDefinition|HttpSyncDefinitionBuilder $object) Resolve a HttpSyncDefinitionBuilder or HttpSyncDefinition object to a HttpSyncDefinition object
 *
 * @uses HttpSyncDefinition
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of HttpSyncProvider
 *
 * @extends Builder<HttpSyncDefinition<TEntity,TProvider>>
 */
final class HttpSyncDefinitionBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return HttpSyncDefinition::class;
    }
}
