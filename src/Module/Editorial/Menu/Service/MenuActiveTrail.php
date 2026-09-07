<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Menu\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Which navigation entry the visitor is standing on.
 *
 * Two questions rather than one, because a menu answers two. "Is this the
 * page I am looking at" decides `aria-current`, which must name exactly one
 * entry or it stops meaning anything. "Is the page I am looking at somewhere
 * under this entry" decides the highlight, which should stay lit on
 * /fr/projets/onyx while the entry itself points at /fr/projets - otherwise
 * the navigation goes blank the moment a reader follows a link into a
 * section.
 *
 * Comparison is on the path alone. A query string is a filter or a campaign
 * tag, not a different page, and a trailing slash is the same address
 * written twice.
 *
 * There is a third question the path cannot answer: a hub page at
 * /fr/page/aurora introduces publications at /fr/aurora/…, and neither is
 * under the other. {@see currentPostTypeSlug} is what an entry naming its
 * section compares itself against.
 */
final readonly class MenuActiveTrail
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    /**
     * Null outside a request - a menu rendered from the console, or in a
     * warm-up, marks nothing current rather than guessing.
     */
    public function currentPath(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request instanceof Request ? $this->normalize($request->getPathInfo()) : null;
    }

    /**
     * The content type of the page being looked at, by slug.
     *
     * Read from the route rather than from the controller: both the
     * publication route and the listing route carry `postTypeSlug`, and a
     * menu rendered inside a Twig layout has no other way of knowing which
     * section it is standing in without every page being made to say so.
     *
     * Null everywhere else - the home page, a term page, an error page - and
     * an entry that heads a section is simply not lit there.
     */
    public function currentPostTypeSlug(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return null;
        }

        $parameters = $request->attributes->get('_route_params');
        $slug = is_array($parameters) ? ($parameters['postTypeSlug'] ?? null) : null;

        return is_string($slug) && '' !== $slug ? $slug : null;
    }

    /** The entry is the page being looked at. */
    public function isCurrent(?string $currentPath, ?string $url): bool
    {
        $path = $this->pathOf($url);

        return null !== $currentPath && null !== $path && $currentPath === $path;
    }

    /**
     * The page being looked at sits under the entry.
     *
     * The caller decides whether an entry may answer this at all: the home
     * link is the parent of every address on the site, so treating it as an
     * ancestor would light it up everywhere and tell the reader nothing.
     */
    public function isAncestorOf(?string $currentPath, ?string $url): bool
    {
        $path = $this->pathOf($url);

        if (null === $currentPath || null === $path || '/' === $path) {
            return false;
        }

        return str_starts_with($currentPath, $path.'/');
    }

    /**
     * The comparable path of a menu URL, or null when there is nothing to
     * compare: an entry with no destination, or one pointing at another site.
     * An address elsewhere is never the page we are on, whatever its path.
     */
    private function pathOf(?string $url): ?string
    {
        if (null === $url || '' === $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== $this->requestStack->getCurrentRequest()?->getHost()) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $this->normalize($path) : null;
    }

    /** Trailing slashes off, except for the root, which is only a slash. */
    private function normalize(string $path): ?string
    {
        if ('' === $path) {
            return null;
        }

        $trimmed = mb_rtrim($path, '/');

        return '' === $trimmed ? '/' : $trimmed;
    }
}
