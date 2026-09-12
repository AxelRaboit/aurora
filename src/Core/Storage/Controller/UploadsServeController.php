<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Controller;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Storage\Adapter\R2StorageAdapter;
use Aurora\Core\Storage\Adapter\StorageAdapterInterface;
use Aurora\Core\Storage\Adapter\StoredObject;
use Aurora\Core\Storage\BinaryFileServer;
use Aurora\Core\Storage\Enum\StorageDeliveryModeEnum;
use Aurora\Core\Storage\StorageDeliveryModeProviderInterface;
use Aurora\Core\Storage\StoredFileLocator;
use Aurora\Core\Storage\Workspace\LocalPathAware;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catch-all serve endpoint for everything Aurora stores.
 *
 * Aurora keeps its files outside the document root (see CLAUDE.md §5bis), so
 * every URL of the shape `/uploads/{path}` is intercepted here, the
 * path-traversal guard runs, and the bytes are delivered.
 *
 * **This address never changes.** Not when the file moves to another backend,
 * not when an administrator changes how files are delivered. That is not a
 * convenience: the block editor writes the URL of an image into the body of a
 * publication, so an address that moved with its file would break every page
 * that embedded it. What varies is only what this endpoint answers with.
 *
 * Locally, the bytes are streamed as they always were, through
 * `mod_xsendfile` in production. Remotely, an administrator's choice decides:
 * a redirect to a signed link, a redirect to the public hostname, or a stream
 * through PHP for installations that want their access rules to keep applying.
 *
 * **Auth model**: public by default, since these assets are typically embedded
 * on public pages. An area needing stricter gating defines its OWN route under
 * a backend prefix, which takes precedence over this catch-all. Note that the
 * two redirecting delivery modes hand the visitor a link the application no
 * longer sees, which is exactly why they are a choice and not the default.
 */
final class UploadsServeController extends AbstractController
{
    /**
     * How long a signed link lives. Long enough for a page full of images to
     * fetch them, short enough that a copied address stops working before it
     * is useful to anyone else.
     */
    private const int SIGNED_URL_TTL = 300;

    public function __construct(
        private readonly BinaryFileServer $binaryFileServer,
        private readonly StoredFileLocator $locator,
        private readonly StorageDeliveryModeProviderInterface $deliveryModeProvider,
        #[Autowire(param: 'app.upload_dir')]
        private readonly string $uploadRoot,
    ) {}

    #[Route(
        '/uploads/{path}',
        name: 'uploads_serve',
        requirements: ['path' => '[^.][^./].*'],
        methods: [HttpMethodEnum::Get->value],
    )]
    public function serve(string $path): Response
    {
        // Router-level guard against `..` segments. The adapters re-check, but
        // rejecting earlier is cheaper.
        if (str_contains($path, '/../') || str_starts_with($path, '../') || str_ends_with($path, '/..')) {
            throw $this->createNotFoundException();
        }

        $adapter = $this->locator->locate($path);

        if (!$adapter instanceof StorageAdapterInterface) {
            throw $this->createNotFoundException();
        }

        if ($adapter instanceof LocalPathAware) {
            return $this->serveLocal($path);
        }

        return $this->serveRemote($adapter, $path);
    }

    private function serveLocal(string $path): Response
    {
        try {
            return $this->binaryFileServer->servePublic(
                $this->binaryFileServer->path($this->uploadRoot, $path),
                $this->uploadRoot,
            );
        } catch (RuntimeException) {
            throw $this->createNotFoundException();
        }
    }

    private function serveRemote(StorageAdapterInterface $adapter, string $path): Response
    {
        $mode = $this->deliveryModeProvider->deliveryMode();

        if ($adapter instanceof R2StorageAdapter && $mode->isRedirect()) {
            $target = StorageDeliveryModeEnum::PublicUrl === $mode
                ? $adapter->publicUrl($path)
                : $adapter->temporaryUrl($path, self::SIGNED_URL_TTL);

            if (null !== $target) {
                // 302 rather than 301: the destination is either a signed link
                // that expires or a hostname an administrator may change, and
                // neither should be remembered by a browser forever.
                return new RedirectResponse($target);
            }

            // Public delivery asked for, no hostname configured. Falls through
            // to the proxy rather than failing: a missing optional setting
            // should degrade, not take the images down.
        }

        return $this->streamThrough($adapter, $path);
    }

    /**
     * Streams the object through PHP, a chunk at a time.
     *
     * Chunked rather than read whole: this mode exists for installations that
     * want their access rules to keep applying, and those are the same
     * installations most likely to be serving something too big to hold in
     * memory.
     */
    private function streamThrough(StorageAdapterInterface $adapter, string $path): Response
    {
        $stored = $adapter->stat($path);

        $response = new StreamedResponse(static function () use ($adapter, $path): void {
            foreach ($adapter->readStream($path) as $chunk) {
                echo $chunk;
                flush();
            }
        });

        $response->setPublic();
        $response->setMaxAge(86400);
        $response->headers->addCacheControlDirective('immutable');

        if ($stored instanceof StoredObject) {
            $response->headers->set('Content-Length', (string) $stored->size);

            if (null !== $stored->checksum) {
                $response->setEtag($stored->checksum);
            }
        }

        return $response;
    }
}
