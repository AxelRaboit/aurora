<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Signature\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Studio\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Studio\Contract\Signature\Entity\ContractSignatureChallenge;
use Aurora\Module\Studio\Contract\Signature\Entity\ContractSignatureChallengeInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<ContractSignatureChallengeInterface>
 */
class ContractSignatureChallengeRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractSignatureChallenge::class, ContractSignatureChallengeInterface::class);
    }

    /**
     * The most recent code issued for this address.
     *
     * One code at a time by construction: asking for a new one supersedes the
     * last, and only the newest is ever checked. Verifying against any code
     * still inside its window would multiply the guesses a limit is supposed
     * to cap.
     */
    public function findLatestFor(ContractAccessLinkInterface $link): ?ContractSignatureChallengeInterface
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.link = :link')
            ->setParameter('link', $link)
            ->orderBy('c.createdAt', Order::Descending->value)
            ->addOrderBy('c.id', Order::Descending->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * How many codes were asked for through this address in a window.
     *
     * The rate limiter caps requests per IP; this caps them per link, which is
     * the thing being attacked. Somebody rotating addresses still cannot mail
     * a hundred codes to a customer.
     */
    public function countIssuedSince(ContractAccessLinkInterface $link, DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.link = :link')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('link', $link)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
