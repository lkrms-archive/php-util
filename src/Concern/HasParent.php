<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\ITreeable;
use Lkrms\Support\Introspector;
use LogicException;

/**
 * Implements ITreeable
 *
 * @see ITreeable
 */
trait HasParent
{
    abstract public static function getParentProperty(): string;

    abstract public static function getChildrenProperty(): string;

    /**
     * @var array<class-string<self>,string>
     */
    private static $_ParentProperties = [];

    /**
     * @var array<class-string<self>,string>
     */
    private static $_ChildrenProperties = [];

    private static function loadHierarchyProperties(): void
    {
        $introspector = Introspector::get(static::class);

        if (!$introspector->IsTreeable) {
            throw new LogicException(
                sprintf(
                    '%s does not implement %s or does not return valid parent/child properties',
                    static::class,
                    ITreeable::class,
                )
            );
        }

        self::$_ParentProperties[static::class] =
            $introspector->Properties[$introspector->ParentProperty]
                ?? $introspector->ParentProperty;
        self::$_ChildrenProperties[static::class] =
            $introspector->Properties[$introspector->ChildrenProperty]
                ?? $introspector->ChildrenProperty;
    }

    /**
     * @return static|null
     */
    final public function getParent()
    {
        if (!isset(self::$_ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$_ParentProperties[static::class];

        return $this->{$_parent};
    }

    /**
     * @return static[]
     */
    final public function getChildren(): array
    {
        if (!isset(self::$_ChildrenProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_children = self::$_ChildrenProperties[static::class];

        return $this->{$_children} ?? [];
    }

    /**
     * @param (ITreeable&static)|null $parent
     * @return $this
     */
    final public function setParent($parent)
    {
        if (!isset(self::$_ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$_ParentProperties[static::class];
        $_children = self::$_ChildrenProperties[static::class];

        if ($parent === $this->{$_parent} &&
            ($parent === null ||
                in_array($this, $parent->{$_children} ?: [], true))) {
            return $this;
        }

        // Remove the object from its current parent
        if ($this->{$_parent} !== null) {
            $this->{$_parent}->{$_children} =
                array_values(
                    array_filter(
                        $this->{$_parent}->{$_children},
                        fn($child) => $child !== $this
                    )
                );
        }

        $this->{$_parent} = $parent;

        if ($parent !== null) {
            return $this->{$_parent}->{$_children}[] = $this;
        }

        return $this;
    }

    /**
     * @param static $child
     * @return $this
     */
    final public function addChild($child)
    {
        return $child->setParent($this);
    }

    /**
     * @param static $child
     * @return $this
     */
    final public function removeChild($child)
    {
        if (!isset(self::$_ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$_ParentProperties[static::class];

        if ($child->{$_parent} !== $this) {
            throw new LogicException('Argument #1 ($child) is not a child of this object');
        }

        return $child->setParent(null);
    }

    final public function getDepth(): int
    {
        if (!isset(self::$_ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$_ParentProperties[static::class];

        $depth = 0;
        $parent = $this->{$_parent};
        while (!is_null($parent)) {
            $depth++;
            $parent = $parent->{$_parent};
        }

        return $depth;
    }
}
