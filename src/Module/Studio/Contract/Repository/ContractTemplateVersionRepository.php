<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Studio\Contract\Entity\ContractTemplateInterface;
use Aurora\Module\Studio\Contract\Entity\ContractTemplateVersion;
use Aurora\Module\Studio\Contract\Entity\ContractTemplateVersionInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<ContractTemplateVersionInterface>
 */
class ContractTemplateVersionRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractTemplateVersion::class, ContractTemplateVersionInterface::class);
    }

    /**
     * The open draft of this template, if there is one.
     *
     * Asked of the database rather than of the loaded collection when the
     * caller has not hydrated the versions: the one-draft rule is enforced
     * against what is stored, not against what happens to be in memory.
     */
    public function findDraftFor(ContractTemplateInterface $template): ?ContractTemplateVersionInterface
    {
        return $this->findOneBy(['template' => $template, 'publishedAt' => null]);
    }
}
