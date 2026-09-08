<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Manager;

use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Accounting\Contract\Dto\ContractTemplateInputInterface;
use Aurora\Module\Accounting\Contract\Dto\ContractTemplateVersionInputInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionInterface;
use Aurora\Module\Accounting\Contract\Exception\PublishedVersionIsImmutableException;

interface ContractTemplateManagerInterface
{
    public function create(ContractTemplateInputInterface $input): ContractTemplateInterface;

    public function update(ContractTemplateInterface $template, ContractTemplateInputInterface $input): void;

    public function archive(ContractTemplateInterface $template): void;

    public function restore(ContractTemplateInterface $template): void;

    public function delete(ContractTemplateInterface $template): void;

    /**
     * Opens the next draft, seeded from the wording currently published.
     *
     * This is how published wording gets edited: not in place, but as a new
     * numbered version that can be worked on and published in turn.
     *
     * @throws FieldException when a draft is already open
     */
    public function openDraft(ContractTemplateInterface $template): ContractTemplateVersionInterface;

    /**
     * @throws PublishedVersionIsImmutableException
     */
    public function updateDraft(ContractTemplateVersionInterface $version, ContractTemplateVersionInputInterface $input): void;

    /**
     * @throws PublishedVersionIsImmutableException
     * @throws FieldException                       when the draft has no wording
     */
    public function publish(ContractTemplateVersionInterface $version): void;

    /**
     * @throws PublishedVersionIsImmutableException
     */
    public function discardDraft(ContractTemplateVersionInterface $version): void;
}
