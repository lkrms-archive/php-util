<?php declare(strict_types=1);

namespace Salient\Sync\Event;

use Salient\Sync\SyncStore;

/**
 * Base class for entity store events
 */
abstract class AbstractSyncStoreEvent extends AbstractSyncEvent
{
    protected SyncStore $Store;

    public function __construct(SyncStore $store)
    {
        $this->Store = $store;
    }

    public function store(): SyncStore
    {
        return $this->Store;
    }
}
