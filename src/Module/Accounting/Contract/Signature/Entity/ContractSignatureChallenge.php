<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Signature\Entity;

use Aurora\Module\Accounting\Contract\Signature\Repository\ContractSignatureChallengeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractSignatureChallengeRepository::class)]
#[ORM\Table(name: 'core_contract_signature_challenges')]
class ContractSignatureChallenge extends AbstractContractSignatureChallenge
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_contract_challenge_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
