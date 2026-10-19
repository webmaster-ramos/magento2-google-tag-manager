<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model;

/**
 * Parses and validates the host lists the merchant types into the admin.
 *
 * One place for both the reader and the save-time validators, so a host the
 * admin accepted is always a host the storefront understands.
 */
class HostList
{
    private const LABEL = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

    /**
     * Split a comma-, space- or newline-separated list into unique lower-case entries.
     *
     * @param string|null $raw
     * @return string[]
     */
    public function parse(?string $raw): array
    {
        $items = preg_split('/[\s,]+/', strtolower((string)$raw), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($items === false ? [] : $items));
    }

    /**
     * Whether the entry is a bare hostname, optionally with a leading "*." subdomain wildcard.
     *
     * Anything a CSP source list would read as more than a host – a scheme, a path, a port,
     * a quoted keyword, a lone "*" – is refused.
     *
     * @param string $host
     * @param bool $allowWildcard
     * @return bool
     */
    public function isValid(string $host, bool $allowWildcard = false): bool
    {
        if ($allowWildcard && str_starts_with($host, '*.')) {
            $host = substr($host, 2);
        }
        if ($host === '' || strlen($host) > 253) {
            return false;
        }

        return (bool)preg_match('/^' . self::LABEL . '(?:\.' . self::LABEL . ')*$/', $host);
    }

    /**
     * Entries of the list that are not valid hosts.
     *
     * @param string[] $hosts
     * @param bool $allowWildcard
     * @return string[]
     */
    public function invalid(array $hosts, bool $allowWildcard = false): array
    {
        return array_values(array_filter(
            $hosts,
            fn (string $host): bool => !$this->isValid($host, $allowWildcard)
        ));
    }
}
