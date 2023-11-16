<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Store\CacheStore;
use DateTimeInterface;

/**
 * A facade for \Lkrms\Store\CacheStore
 *
 * @method static CacheStore load(string $filename = ':memory:') Load and return an instance of the underlying CacheStore class
 * @method static CacheStore getInstance() Get the underlying CacheStore instance
 * @method static bool isLoaded() True if an underlying CacheStore instance has been loaded
 * @method static void unload() Clear the underlying CacheStore instance
 * @method static CacheStore asOfNow(int|null $now = null) Get a copy of the store where items do not expire over time (see {@see CacheStore::asOfNow()})
 * @method static CacheStore close() Close the database
 * @method static CacheStore delete(string $key) Delete an item stored under a given key
 * @method static CacheStore deleteAll() Delete all items
 * @method static CacheStore flush() Delete expired items
 * @method static mixed|false get(string $key, ?int $maxAge = null) Retrieve an item stored under a given key (see {@see CacheStore::get()})
 * @method static string|null getFilename() Get the filename of the database
 * @method static bool has(string $key, ?int $maxAge = null) True if an item exists and has not expired (see {@see CacheStore::has()})
 * @method static bool isOpen() Check if a database is open
 * @method static mixed maybeGet(string $key, callable(): mixed $callback, DateTimeInterface|int|null $expires = null) Retrieve an item stored under a given key, or get it from a callback and store it for subsequent retrieval (see {@see CacheStore::maybeGet()})
 * @method static CacheStore set(string $key, mixed $value, DateTimeInterface|int|null $expires = null) Store an item under a given key (see {@see CacheStore::set()})
 *
 * @uses CacheStore
 *
 * @extends Facade<CacheStore>
 */
final class Cache extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getServiceName(): string
    {
        return CacheStore::class;
    }
}
