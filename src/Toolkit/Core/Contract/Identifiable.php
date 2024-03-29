<?php declare(strict_types=1);

namespace Salient\Core\Contract;

interface Identifiable
{
    /**
     * Get the object's unique identifier
     *
     * @return int|string|null
     */
    public function id();
}
