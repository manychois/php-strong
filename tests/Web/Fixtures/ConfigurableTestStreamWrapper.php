<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web\Fixtures;

/**
 * Stream wrapper used to trigger failure branches inside {@see \Manychois\PhpStrong\Web\Stream}.
 */
final class ConfigurableTestStreamWrapper
{
    public const REGISTER_SCHEME = 'phpstrongtest';

    public $context;

    private static string $behavior = 'normal';

    /**
     * @var resource|null
     */
    private $buffer = null;

    public static function resetBehavior(): void
    {
        self::$behavior = 'normal';
    }

    public static function setBehavior(string $behavior): void
    {
        self::$behavior = $behavior;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->buffer = fopen('php://memory', $mode);
        if ($this->buffer === false) {
            return false;
        }
        $payload = match (self::$behavior) {
            'normal', 'fread_false', 'fstat_false', 'write_false' => 'xy',
            default => '',
        };
        if ($payload !== '') {
            fwrite($this->buffer, $payload);
            rewind($this->buffer);
        }

        return true;
    }

    public function stream_close(): void
    {
        if (is_resource($this->buffer)) {
            fclose($this->buffer);
        }
        $this->buffer = null;
    }

    public function stream_eof(): bool
    {
        if ($this->buffer === null) {
            return true;
        }

        return feof($this->buffer);
    }

    public function stream_read(int $count): string|false
    {
        if (self::$behavior === 'fread_false') {
            return false;
        }
        if ($this->buffer === null) {
            return false;
        }

        $chunk = fread($this->buffer, $count);
        if ($chunk === false) {
            return false;
        }

        return $chunk;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if ($this->buffer === null) {
            return false;
        }

        return fseek($this->buffer, $offset, $whence) === 0;
    }

    public function stream_stat(): array|false
    {
        if (self::$behavior === 'fstat_false') {
            return false;
        }
        if ($this->buffer === null) {
            return false;
        }

        $s = fstat($this->buffer);

        return $s === false ? [] : $s;
    }

    public function stream_tell(): int|false
    {
        if ($this->buffer === null) {
            return false;
        }
        $p = ftell($this->buffer);
        if ($p === false) {
            return false;
        }

        return $p;
    }

    public function stream_write(string $data): int|false
    {
        if (self::$behavior === 'write_false') {
            return false;
        }
        if ($this->buffer === null) {
            return false;
        }
        $n = fwrite($this->buffer, $data);

        return $n === false ? false : $n;
    }

    /** @return true */
    public function stream_flush(): bool
    {
        return true;
    }
}
