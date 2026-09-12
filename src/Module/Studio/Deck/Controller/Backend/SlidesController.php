<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Http\JsonRequestTrait;
use Aurora\Core\Http\JsonResponseTrait;
use Aurora\Module\Studio\Deck\Entity\Deck;
use Aurora\Module\Studio\Deck\Entity\SlideInterface;
use Aurora\Module\Studio\Deck\Enum\SlideLayoutEnum;
use Aurora\Module\Studio\Deck\Manager\DeckManager;
use Aurora\Module\Studio\Deck\Serializer\DeckSerializer;
use Aurora\Module\Studio\Deck\View\DecksViewBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function is_int;
use function is_string;

/**
 * One deck's slides: the page that composes them, and the four writes it makes.
 *
 * A controller of its own rather than more methods on `DecksController`,
 * because these routes are nested under a deck and every one of them has to
 * check that the slide it was handed belongs to the deck in the address. That
 * check is the reason the class exists, and it is cheap to forget once.
 */
#[Route('/backend/studio/decks/{id}', name: 'backend_studio_deck', requirements: ['id' => '\d+'])]
#[IsGranted('studio.decks.view')]
class SlidesController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        protected readonly DeckManager $deckManager,
        protected readonly DeckSerializer $serializer,
        protected readonly DecksViewBuilder $viewBuilder,
        protected readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: '', methods: [HttpMethodEnum::Get->value])]
    public function show(Deck $deck): Response
    {
        return $this->render('@Studio/backend/decks/show.html.twig', $this->viewBuilder->showView($deck));
    }

    #[Route('/slides/create', name: '_slide_create', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('studio.decks.edit')]
    public function create(Deck $deck, Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $layout = SlideLayoutEnum::tryFrom(is_string($payload['layout'] ?? null) ? $payload['layout'] : '');

        if (null === $layout) {
            return $this->jsonInvalidInput(['layout' => 'backend.studio.decks.errors.layout_unknown']);
        }

        $slide = $this->deckManager->addSlide($deck, $layout);
        $this->entityManager->flush();

        return $this->jsonSuccess(['slide' => $this->serializer->slide($slide)]);
    }

    #[Route('/slides/{slideId}/update', name: '_slide_update', requirements: ['slideId' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('studio.decks.edit')]
    public function update(Deck $deck, int $slideId, Request $request): JsonResponse
    {
        $slide = $this->slideOf($deck, $slideId);

        if (!$slide instanceof SlideInterface) {
            return $this->jsonNotFound();
        }

        $payload = $this->decodeJson($request);

        // The layout may change on an existing slide, and that is on purpose:
        // a slide written as bullets often wants to become a section divider
        // once the deck has a shape. The content is then whitelisted against
        // the *new* layout, so the slots it no longer has simply go.
        $layout = SlideLayoutEnum::tryFrom(is_string($payload['layout'] ?? null) ? $payload['layout'] : '');
        if (null !== $layout) {
            $slide->setLayout($layout);
        }

        $this->deckManager->writeContent($slide, is_array($payload['content'] ?? null) ? $payload['content'] : []);
        $slide->setSpeakerNotes(is_string($payload['speakerNotes'] ?? null) && '' !== $payload['speakerNotes'] ? $payload['speakerNotes'] : null);

        $this->entityManager->flush();

        return $this->jsonSuccess(['slide' => $this->serializer->slide($slide)]);
    }

    #[Route('/slides/{slideId}/delete', name: '_slide_delete', requirements: ['slideId' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('studio.decks.edit')]
    public function delete(Deck $deck, int $slideId): JsonResponse
    {
        $slide = $this->slideOf($deck, $slideId);

        if (!$slide instanceof SlideInterface) {
            return $this->jsonNotFound();
        }

        $deck->removeSlide($slide);
        $this->entityManager->remove($slide);
        $this->entityManager->flush();

        return $this->jsonSuccess();
    }

    /**
     * The whole order, sent at once.
     *
     * Not "move this slide up": a drag lands somewhere arbitrary, and a
     * sequence of swaps to describe it is a sequence that can arrive out of
     * order. The page already knows the order it is showing, so it says it.
     */
    #[Route('/slides/reorder', name: '_slide_reorder', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('studio.decks.edit')]
    public function reorder(Deck $deck, Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $ids = is_array($payload['orderedIds'] ?? null) ? $payload['orderedIds'] : [];

        $this->deckManager->reorderSlides($deck, array_values(array_filter($ids, is_int(...))));
        $this->entityManager->flush();

        return $this->jsonSuccess(['deck' => $this->serializer->full($deck)]);
    }

    /**
     * The slide, only if it is this deck's.
     *
     * A slide id from another deck must not be writable through a deck the
     * reader happens to be allowed to open. Reading it off the deck rather
     * than from the repository is what makes that structural rather than a
     * check somebody has to remember.
     */
    private function slideOf(Deck $deck, int $slideId): ?SlideInterface
    {
        foreach ($deck->getSlides() as $slide) {
            if ($slide->getId() === $slideId) {
                return $slide;
            }
        }

        return null;
    }
}
