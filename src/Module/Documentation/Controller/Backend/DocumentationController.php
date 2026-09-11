<?php

declare(strict_types=1);

namespace Aurora\Module\Documentation\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Http\JsonResponseTrait;
use Aurora\Module\Documentation\Service\DocumentationIndex;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_file;
use function realpath;
use function sprintf;
use function str_starts_with;

/**
 * The manual, served from the files that ship with the product.
 *
 * Readable by anybody who may open the back office, and writable by nobody:
 * there is no form here, and no row in any database. A client who wants to
 * write their own notes has the Notes module for that.
 */
#[Route('/backend/documentation', name: 'backend_documentation')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DocumentationController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly DocumentationIndex $index,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'aurora.documentation.images_dir')]
        private readonly string $imagesDir,
    ) {}

    #[Route('', name: '', methods: [HttpMethodEnum::Get->value])]
    public function index(): Response
    {
        $tree = $this->index->tree();
        $first = $tree[0]['pages'][0]['slug'] ?? null;

        if (null === $first) {
            return $this->render('@Documentation/backend/empty.html.twig');
        }

        return $this->redirectToRoute('backend_documentation_page', ['slug' => $first]);
    }

    /**
     * The rubrics and their pages, for the side menu's panel.
     *
     * The page used to receive this with its payload and draw it in a column
     * of its own. The column moved into the menu, where it is mounted by the
     * menu with no props at all - so it fetches, like every other module
     * panel. One round trip per page read, against a list the reader needs on
     * every one of them.
     */
    #[Route('/tree', name: '_tree', methods: [HttpMethodEnum::Get->value], priority: 10)]
    public function tree(): JsonResponse
    {
        return $this->jsonSuccess(['tree' => $this->index->tree()]);
    }

    #[Route('/search', name: '_search', methods: [HttpMethodEnum::Get->value], priority: 10)]
    public function search(Request $request): JsonResponse
    {
        return $this->jsonSuccess([
            'results' => $this->index->search($request->query->getString('q', '')),
        ]);
    }

    /**
     * The screenshots, served from the package rather than built into the
     * client's assets.
     *
     * A client updates Aurora with Composer and nothing else: asking them to
     * run an asset install for the manual to show its pictures is a step that
     * would be skipped, and a page of empty frames is worse than no page.
     */
    #[Route('/image/{rubric}/{name}', name: '_image', requirements: ['rubric' => '[\w-]+', 'name' => '[\w.-]+'], methods: [HttpMethodEnum::Get->value], priority: 10)]
    public function image(Request $request, string $rubric, string $name): Response
    {
        $path = realpath(sprintf('%s/%s/%s', $this->imagesDir, $rubric, $name));
        $root = realpath($this->imagesDir);

        // Belt and braces over the route requirements: the pattern already
        // refuses a slash and a `..`, and a path that escaped the folder
        // anyway must not be served.
        if (false === $path || false === $root || !str_starts_with($path, $root) || !is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);

        // Private, and that is not a choice made here: the manual sits
        // behind the session, so Symfony marks every response of this
        // firewall private whatever is asked for. A shared cache keeping a
        // client's screenshots would be the worse outcome anyway.
        //
        // What is left is the browser's own cache, and a conditional request
        // is what makes it work: the file changes only when the package
        // does, so a reader who opens the manual twice fetches the bytes
        // once and gets a 304 after that.
        $response->setAutoLastModified();
        $response->setAutoEtag();
        $response->isNotModified($request);

        return $response;
    }

    #[Route('/{slug}', name: '_page', requirements: ['slug' => '[\w-]+'], methods: [HttpMethodEnum::Get->value])]
    public function page(string $slug): Response
    {
        $page = $this->index->page($slug);

        if (null === $page) {
            throw $this->createNotFoundException();
        }

        // Neither the tree nor the search path any more: both belong to the
        // side menu's panel now, which fetches them itself. Handing them to
        // the page as well would ship the whole table of contents twice on
        // every read.
        return $this->render('@Documentation/backend/index.html.twig', [
            'page' => $page,
            'neighbours' => $this->index->neighbours($slug),
            'pagePathTemplate' => $this->urlGenerator->generate('backend_documentation_page', ['slug' => '__slug__']),
            'imagePathTemplate' => $this->urlGenerator->generate('backend_documentation_image', ['rubric' => '__rubric__', 'name' => '__name__']),
        ]);
    }
}
