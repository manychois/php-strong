<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Links\Internal;

/**
 * Decides whether a string is an RFC 6570 URI template.
 *
 * @internal
 */
final class UriTemplate
{
    private const string VARCHAR = '(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})';
    private const string VARNAME = self::VARCHAR . '+(?:\.' . self::VARCHAR . '+)*';
    private const string VARSPEC = self::VARNAME . '(?::[1-9][0-9]{0,3}|\*)?';
    private const string EXPRESSION = '\{[+#./;?&]?' . self::VARSPEC . '(?:,' . self::VARSPEC . ')*\}';
    private const string LITERAL = '(?:[^\x00-\x20\x7F"\'%<>\\\\^`|{}]|%[0-9A-Fa-f]{2})';
    private const string TEMPLATE = '~^(?:' . self::LITERAL . '|' . self::EXPRESSION . ')*+$~u';

    /**
     * Tells whether the given string parses as an RFC 6570 URI template containing at least one expression.
     *
     * A string with no expression, or with a malformed one, is an ordinary URI and yields `false`; this method
     * never throws, because PSR-13 lets any absolute or relative URI be a link href.
     *
     * @param string $href The href to inspect.
     *
     * @return bool True if the whole string is a valid URI template with at least one expression.
     */
    public static function isTemplate(string $href): bool
    {
        if (!str_contains($href, '{')) {
            return false;
        }

        return preg_match(self::TEMPLATE, $href) === 1;
    }
}
