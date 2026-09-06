<?php

declare(strict_types=1);

namespace Aurora\Core\Frontend\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class HttpCacheService
{
    public function __construct(
        private AssetBuildStamp $assetBuildStamp,
    ) {}

    /**
     * Checks if the client cache is still fresh.
     * Returns a 304 response if fresh, null otherwise.
     * Use this BEFORE doing expensive rendering to short-circuit the request.
     */
    public function checkNotModified(Request $request, ?DateTimeInterface $lastModified, int $maxAge = 300): ?Response
    {
        $validator = $this->validator($lastModified);

        if (!$validator instanceof DateTimeInterface) {
            return null;
        }

        $response = new Response();
        $response->setLastModified($validator);
        $response->setPublic();
        $response->setMaxAge($maxAge);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return null;
    }

    /**
     * Applies public cache headers to an existing response.
     */
    public function setPublicCache(Response $response, ?DateTimeInterface $lastModified, int $maxAge = 300): void
    {
        $validator = $this->validator($lastModified);

        if ($validator instanceof DateTimeInterface) {
            $response->setLastModified($validator);
        }

        $response->setPublic();
        $response->setMaxAge($maxAge);
    }

    /**
     * Applies a short shared cache (CDN/proxy) without Last-Modified.
     * Suitable for list pages whose content changes more frequently.
     */
    public function setSharedCache(Response $response, int $sharedMaxAge = 60): void
    {
        $response->setPublic();
        $response->setSharedMaxAge($sharedMaxAge);
    }

    /**
     * The date a cached copy is judged against.
     *
     * The later of two things, because a page depends on both: when its
     * content last changed, and when the assets it links to were last built.
     * Judging on the content alone is what let a deploy leave visitors with
     * a valid-looking copy pointing at stylesheets that no longer exist -
     * the page arrived unstyled and only a hard refresh fixed it, because
     * only a hard refresh ignores a validator the server keeps confirming.
     */
    private function validator(?DateTimeInterface $lastModified): ?DateTimeInterface
    {
        if (!$lastModified instanceof DateTimeInterface) {
            return null;
        }

        $builtAt = $this->assetBuildStamp->builtAt();

        return $builtAt instanceof DateTimeImmutable && $builtAt > $lastModified ? $builtAt : $lastModified;
    }
}
