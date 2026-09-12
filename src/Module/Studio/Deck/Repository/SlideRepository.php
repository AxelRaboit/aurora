<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Studio\Deck\Entity\Slide;
use Aurora\Module\Studio\Deck\Entity\SlideInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<SlideInterface>
 */
class SlideRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Slide::class, SlideInterface::class);
    }
}
