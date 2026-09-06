<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Unsplash\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Http\JsonRequestTrait;
use Aurora\Core\Http\JsonResponseTrait;
use Aurora\Module\Ged\Document\Serializer\DocumentSerializerInterface;
use Aurora\Module\Ged\Unsplash\Service\UnsplashClient;
use Aurora\Module\Ged\Unsplash\Service\UnsplashImporter;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The picker's window onto Unsplash.
 *
 * Two endpoints, both proxies: searching so the access key stays server-side,
 * importing so the stored URL is checked before any page renders it. Neither
 * accepts anything the browser cannot already see.
 */
#[Route('/backend/ged/unsplash', name: 'backend_ged_unsplash')]
#[IsGranted('ged.documents.view')]
final class UnsplashController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly UnsplashClient $client,
        private readonly UnsplashImporter $importer,
        private readonly DocumentSerializerInterface $serializer,
    ) {}

    #[Route('/search', name: '_search', methods: [HttpMethodEnum::Get->value])]
    public function search(Request $request): JsonResponse
    {
        // Reported rather than failed: the picker draws a "not configured"
        // notice, which tells an administrator what to do. A 500 would only
        // tell them something broke.
        if (!$this->client->isConfigured()) {
            return $this->jsonSuccess(['configured' => false, 'results' => [], 'totalPages' => 0]);
        }

        $result = $this->client->search(
            (string) $request->query->get('q', ''),
            $request->query->getInt('page', 1),
        );

        return $this->jsonSuccess([
            'configured' => true,
            'results' => $result['results'],
            'totalPages' => $result['totalPages'],
        ]);
    }

    /**
     * Files the chosen photo and hands back an ordinary serialized document,
     * so the picker can resolve with it exactly as it does for a library
     * pick - the field on the other side never learns where it came from.
     */
    #[Route('/import', name: '_import', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('ged.documents.create')]
    public function import(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) ($this->decodeJson($request)['photo'] ?? []);

        try {
            $document = $this->importer->import($payload);
        } catch (InvalidArgumentException) {
            return $this->jsonFailure('backend.ged.unsplash.errors.invalid_photo');
        }

        return $this->jsonSuccess(['document' => $this->serializer->serialize($document)]);
    }
}
