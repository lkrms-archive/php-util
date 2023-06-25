<?php declare(strict_types=1);

namespace Lkrms\Utility;

/**
 * Array functions for functional programming
 *
 *
 */
final class Arr
{
    /**
     * Shift an element off the beginning of an array
     *
     * Wrapper for `array_shift()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param TValue|null $shifted
     * @return array<TKey,TValue>
     */
    public static function shift(array $array, &$shifted = null): array
    {
        $shifted = array_shift($array);
        return $array;
    }

    /**
     * Add one or more elements to the beginning of an array
     *
     * Wrapper for `array_unshift()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param TValue ...$values
     * @return array<TKey,TValue>
     */
    public static function unshift(array $array, ...$values): array
    {
        array_unshift($array, ...$values);
        return $array;
    }

    /**
     * Pop a value off the end of an array
     *
     * Wrapper for `array_pop()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param TValue|null $popped
     * @return array<TKey,TValue>
     */
    public static function pop(array $array, &$popped = null): array
    {
        $popped = array_pop($array);
        return $array;
    }

    /**
     * Push one or more values onto the end of an array
     *
     * Wrapper for `array_push()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param TValue ...$values
     * @return array<TKey,TValue>
     */
    public static function push(array $array, ...$values): array
    {
        array_push($array, ...$values);
        return $array;
    }

    /**
     * Sort an array by value in ascending order
     *
     * Wrapper for `sort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<int,TValue>
     */
    public static function sort(array $array, int $flags = SORT_REGULAR): array
    {
        sort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by value in descending order
     *
     * Wrapper for `rsort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<int,TValue>
     */
    public static function rsort(array $array, int $flags = SORT_REGULAR): array
    {
        rsort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by value in ascending order and maintain index association
     *
     * Wrapper for `asort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<TKey,TValue>
     */
    public static function asort(array $array, int $flags = SORT_REGULAR): array
    {
        asort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by value in descending order and maintain index association
     *
     * Wrapper for `arsort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<TKey,TValue>
     */
    public static function arsort(array $array, int $flags = SORT_REGULAR): array
    {
        arsort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by value with a user-defined comparison function
     *
     * Wrapper for `usort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param callable(TValue $a, TValue $b): int $callback
     * @return array<int,TValue>
     */
    public static function usort(array $array, callable $callback): array
    {
        usort($array, $callback);
        return $array;
    }

    /**
     * Sort an array by value with a user-defined comparison function and
     * maintain index association
     *
     * Wrapper for `uasort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param callable(TValue $a, TValue $b): int $callback
     * @return array<TKey,TValue>
     */
    public static function uasort(array $array, callable $callback): array
    {
        uasort($array, $callback);
        return $array;
    }

    /**
     * Sort an array by key in ascending order
     *
     * Wrapper for `ksort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<TKey,TValue>
     */
    public static function ksort(array $array, int $flags = SORT_REGULAR): array
    {
        ksort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by key in descending order
     *
     * Wrapper for `krsort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<TKey,TValue>
     */
    public static function krsort(array $array, int $flags = SORT_REGULAR): array
    {
        krsort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by key with a user-defined comparison function
     *
     * Wrapper for `uksort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param callable(TKey $a, TKey $b): int $callback
     * @return array<TKey,TValue>
     */
    public static function uksort(array $array, callable $callback): array
    {
        uksort($array, $callback);
        return $array;
    }

    /**
     * Sort an array by value with a "natural order" algorithm and maintain
     * index association
     *
     * Wrapper for `natsort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<TKey,TValue>
     */
    public static function natsort(array $array): array
    {
        natsort($array);
        return $array;
    }

    /**
     * Sort an array by value with a case-insensitive "natural order" algorithm
     * and maintain index association
     *
     * Wrapper for `natcasesort()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<TKey,TValue>
     */
    public static function natcasesort(array $array): array
    {
        natcasesort($array);
        return $array;
    }
}
