<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Normalises property names
 *
 */
interface IResolvable
{
    /**
     * Normalise the given property name
     *
     * @param string $name
     * @return string
     */
    public static function normaliseProperty(string $name): string;
}
