<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web\Fixtures;

/**
 * Writable stream wrapper where {@see fwrite()} fails, for {@see \Manychois\PhpStrong\Web\UploadedFile} copy tests.
 */
final class FailingFwriteStreamWrapper
{
    public const REGISTER_SCHEME = 'phpstrongfailwrite';

    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return str_contains($mode, 'w') || str_contains($mode, 'a') || str_contains($mode, '+');
    }

    public function stream_close(): void
    {
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_read(int $count): string|false
    {
        return '';
    }

    public function stream_stat(): array|false
    {
        $s = stat(__FILE__);

        return $s === false ? [] : $s;
    }

    /** @return false */
    public function stream_write(string $data): int|false
    {
        return false;
    }

    /** @return true */
    public function stream_flush(): bool
    {
        return true;
    }
}
