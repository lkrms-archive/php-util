<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * May have children or a parent of the same type
 *
 */
interface ITreeNode
{
    /**
     * Set or unset the parent from which the instance descends
     *
     * This method can be used to implicitly add/remove children to/from their
     * new/previous parents.
     *
     * @param static|null $parent
     * @return $this
     */
    public function setParent($parent);

}