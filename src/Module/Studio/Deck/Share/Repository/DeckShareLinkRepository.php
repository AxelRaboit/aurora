<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Share\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Studio\Deck\Entity\DeckInterface;
use Aurora\Module\Studio\Deck\Share\Entity\DeckShareLink;
use Aurora\Module\Studio\Deck\Share\Entity\DeckShareLinkInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<DeckShareLinkInterface>
 */
class DeckShareLinkRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeckShareLink::class, DeckShareLinkInterface::class);
    }

    public function findByToken(string $token): ?DeckShareLinkInterface
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * One deck's links, newest first.
     *
     * Revoked ones come along: the screen shows them struck through rather than
     * hiding them, because "this link no longer works" is the answer somebody
     * is looking for when they come here after sending one.
     *
     * @return list<DeckShareLinkInterface>
     */
    public function findForDeck(DeckInterface $deck): array
    {
        /** @var list<DeckShareLinkInterface> $links */
        $links = $this->createQueryBuilder('l')
            ->andWhere('l.deck = :deck')->setParameter('deck', $deck)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $links;
    }
}
