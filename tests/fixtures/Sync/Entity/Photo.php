<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;

/**
 * Represents the state of a Photo entity in a backend
 */
class Photo extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var Album|null
     */
    public $Album;

    /**
     * @var string|null
     */
    public $Title;

    /**
     * @var string|null
     */
    public $Url;

    /**
     * @var string|null
     */
    public $ThumbnailUrl;

    /**
     * @inheritDoc
     */
    public static function getRelationships(): array
    {
        return [
            'Album' => [RelationshipType::ONE_TO_ONE => Album::class],
        ];
    }
}
