<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Share\Controller;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Module\Studio\Deck\Serializer\DeckSerializer;
use Aurora\Module\Studio\Deck\Share\Entity\DeckShareLinkInterface;
use Aurora\Module\Studio\Deck\Share\Repository\DeckShareLinkRepository;
use Aurora\Module\Studio\StudioContext;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A deck, read by somebody holding its address and nothing else.
 *
 * **Everything this page can reach is decided by the link**, the way
 * `SharedNoteScope` decides it for a note: no identifier from the request ever
 * widens the set. There is one deck behind one token, and no route here takes
 * a deck id at all.
 *
 * Outside the `/backend` firewall on purpose - the reader has no account, and
 * that is the point of the address existing.
 */
#[Route('/decks', name: 'public_deck')]
final class PublicDeckController extends AbstractController
{
    public function __construct(
        private readonly DeckShareLinkRepository $links,
        private readonly DeckSerializer $serializer,
        private readonly StudioContext $studioContext,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/{token}', name: '_show', requirements: ['token' => '[a-f0-9]{64}'], methods: [HttpMethodEnum::Get->value])]
    public function show(string $token): Response
    {
        $link = $this->links->findByToken($token);
        $now = new DateTimeImmutable();

        // One answer for "no such link", "revoked" and "expired", and it is the
        // same 404 a wrong address gets. Telling a holder which of the three it
        // is would confirm that the address was real, which is the one thing a
        // guessed token must not learn.
        if (!$link instanceof DeckShareLinkInterface || !$link->isUsable($now)) {
            throw $this->createNotFoundException();
        }

        // A module switched off takes its public pages with it, like every
        // other front in Aurora: leaving them up would publish decks from a
        // module the owner believes is closed.
        if (!$this->studioContext->isBackendEnabled() || !$this->studioContext->areDecksEnabled()) {
            throw $this->createNotFoundException();
        }

        $link->touch($now);
        $this->entityManager->flush();

        // `full()` carries the speaker notes, which are the presenter's and
        // never the audience's, so they are dropped here rather than trusted
        // to stay out of the template.
        $deck = $this->serializer->full($link->getDeck());
        $deck['slides'] = array_map(
            static function (array $slide): array {
                unset($slide['speakerNotes']);

                return $slide;
            },
            $deck['slides'],
        );

        return $this->render('@Studio/public/deck.html.twig', [
            'deck' => $deck,
            'expiresAt' => $link->getExpiresAt(),
        ]);
    }
}
