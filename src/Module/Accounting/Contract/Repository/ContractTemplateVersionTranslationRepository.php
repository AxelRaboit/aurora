<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionTranslation;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionTranslationInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<ContractTemplateVersionTranslationInterface>
 */
class ContractTemplateVersionTranslationRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractTemplateVersionTranslation::class, ContractTemplateVersionTranslationInterface::class);
    }
}
