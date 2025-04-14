<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use Manychois\PhpStrong\Defaults\DefaultComparer;
use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;

/**
 * Manages the default comparer and equality comparer.
 */
final class Registry
{
    private static ComparerInterface $comparer;
    private static EqualityComparerInterface $equalityComparer;

    /**
     * Returns the default comparer.
     *
     * @return ComparerInterface The default comparer.
     */
    public static function getComparer(): ComparerInterface
    {
        if (!isset(self::$comparer)) {
            self::$comparer = new DefaultComparer();
        }

        return self::$comparer;
    }

    /**
     * Returns the default equality comparer.
     *
     * @return EqualityComparerInterface The default equality comparer.
     */
    public static function getEqualityComparer(): EqualityComparerInterface
    {
        if (!isset(self::$equalityComparer)) {
            self::$equalityComparer = new DefaultEqualityComparer();
        }

        return self::$equalityComparer;
    }

    /**
     * Sets the default comparer.
     *
     * @param ComparerInterface $comparer The comparer to set as the default.
     */
    public static function setComparer(ComparerInterface $comparer): void
    {
        self::$comparer = $comparer;
    }

    /**
     * Sets the default equality comparer.
     *
     * @param EqualityComparerInterface $equalityComparer The equality comparer to set as the default.
     */
    public static function setEqualityComparer(EqualityComparerInterface $equalityComparer): void
    {
        self::$equalityComparer = $equalityComparer;
    }
}
