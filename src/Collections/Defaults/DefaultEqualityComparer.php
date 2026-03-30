<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Defaults;

use Manychois\PhpStrong\Collections\EqualityComparerInterface as IEqualityComparer;
use Manychois\PhpStrong\EquatableInterface as IEquatable;
use Override;

/**
 * A default implementation of the {@see IEqualityComparer} that uses the {@see IEquatable} interface.
 */
class DefaultEqualityComparer implements IEqualityComparer
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function equals(mixed $x, mixed $y): bool
    {
        if ($x === $y) {
            return true;
        }
        if ($x === null || $y === null) {
            return false;
        }
        if ($x instanceof IEquatable) {
            return is_object($y) && $x->equals($y);
        }
        if ($y instanceof IEquatable) {
            return is_object($x) && $y->equals($x);
        }
        return false;
    }
}
