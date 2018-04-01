<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Tsufeki\Tenkawa\Server\Exception\UriException;
use Tsufeki\Tenkawa\Server\Utils\Platform;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class Uri
{
    /**
     * @var string|null
     */
    private $scheme;

    /**
     * @var string|null
     */
    private $authority;

    /**
     * @var string|null
     */
    private $path;

    /**
     * @var string|null
     */
    private $query;

    /**
     * @var string|null
     */
    private $fragment;

    const REGEX =
        '~^(?:([a-zA-Z][-a-zA-Z0-9+.]*):)?' .
        '(?://([^/?#]*))?' .
        '([^?#]*)' .
        '(?:\?([^#]*))?' .
        '(?:\#(.*))?\z~s';

    /**
     * @return string|null
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * @return string|null
     */
    public function getAuthority()
    {
        return $this->authority;
    }

    /**
     * @return string|null
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string|null
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string|null
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    public function __toString(): string
    {
        $result = '';

        if ($this->scheme !== null) {
            $result .= $this->scheme . ':';
        }

        if ($this->authority !== null || $this->scheme === 'file') {
            $result .= '//';
        }

        if ($this->authority !== null) {
            $result .= self::encodeParts($this->authority, ':');
        }

        if ($this->path !== null) {
            $result .= self::encodeParts($this->path, '/');
        }

        if ($this->query !== null) {
            $result .= '?' . str_replace('#', '%23', $this->query);
        }

        if ($this->fragment !== null) {
            $result .= '#' . rawurlencode($this->fragment);
        }

        return $result;
    }

    private static function encodeParts(string $string, string $delimiter): string
    {
        return implode($delimiter, array_map('rawurlencode', explode($delimiter, $string)));
    }

    public function getFilesystemPath(): string
    {
        if (!in_array($this->scheme, ['file', null], true)) {
            throw new UriException('Not a file URI');
        }

        if (!in_array(strtolower((string)$this->authority), ['localhost', ''], true)) {
            throw new UriException("Unsupported authority in a file URI: $this->authority");
        }

        if (Platform::isWindows()) {
            $path = ltrim((string)$this->path, '/');
            $path = str_replace('/', '\\', $path);

            return $path;
        }

        return $this->path ?? '/';
    }

    public static function fromString(string $string): self
    {
        if (preg_match(self::REGEX, $string, $matches) !== 1) {
            throw new UriException("Invalid URI: $string"); // @codeCoverageIgnore
        }

        $uri = new self();

        $uri->scheme = $matches[1] ?: null;
        $uri->authority = self::decodeComponent($matches[2] ?? null);
        $uri->path = self::decodeComponent($matches[3] ?? null);
        $uri->query = self::decodeComponent($matches[4] ?? null, false);
        $uri->fragment = self::decodeComponent($matches[5] ?? null);

        return $uri;
    }

    private static function decodeComponent($component, bool $decode = true)
    {
        if ($component === null || $component === '') {
            return null;
        }

        if ($decode) {
            $component = rawurldecode($component);
        }

        return $component;
    }

    /**
     * @param string $path Absolute path.
     *
     * @return self
     */
    public static function fromFilesystemPath(string $path): self
    {
        $uri = new self();
        $uri->scheme = 'file';

        if (Platform::isWindows()) {
            $path = str_replace('\\', '/', $path);
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $uri->path = $path;

        return $uri;
    }

    public function equals(self $other): bool
    {
        return $this->getNormalized() === $other->getNormalized();
    }

    /**
     * Return normalized form of the URI, which should be suitable to use as array key.
     */
    public function getNormalized(): string
    {
        $normalized = clone $this;

        if ($normalized->scheme === 'file') {
            if ($normalized->authority !== null && strtolower($normalized->authority) === 'localhost') {
                $normalized->authority = null;
            }

            if ($normalized->path !== null) {
                $normalized->path = rtrim($normalized->path, '/');
            } else {
                $normalized->path = '/';
            }

            if (Platform::isWindows()) {
                $normalized->path = strtolower($normalized->path);
            }
        }

        return (string)$normalized;
    }

    public function getNormalizedGlob(): string
    {
        $normalized = $this->getNormalized();
        $normalized = str_replace(['%2a', '%2A'], '*', $normalized);

        return $normalized;
    }

    public function isParentOf(self $other): bool
    {
        if (!in_array($this->scheme, ['file', null], true) || !in_array($other->scheme, ['file', null], true)) {
            return $this->equals($other);
        }

        $thisNormalized = $this->getNormalized();
        $otherNormalized = $other->getNormalized();

        $thisNormalized .= '/';

        return StringUtils::startsWith($otherNormalized, $thisNormalized);
    }
}
