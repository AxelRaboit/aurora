<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Serializer;

use Aurora\Module\Accounting\Contract\Entity\ContractTemplateInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionInterface;

interface ContractTemplateSerializerInterface
{
    /** @return array<string, mixed> */
    public function serialize(ContractTemplateInterface $template): array;

    /**
     * The version with its wording, for the editor.
     *
     * Separate from the template payload on purpose: a list of templates has
     * no business carrying the full text of every version it shows.
     *
     * @return array<string, mixed>
     */
    public function serializeVersion(ContractTemplateVersionInterface $version): array;
}
