<?php declare(strict_types=1);

namespace Lkrms\Sync\Event;

use Lkrms\Sync\Support\SyncStore;

/**
 * Base class for entity store events
 */
abstract class SyncStoreEvent extends SyncEvent
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