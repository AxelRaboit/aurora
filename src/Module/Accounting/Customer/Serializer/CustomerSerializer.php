<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Customer\Serializer;

use Aurora\Module\Accounting\Customer\Entity\CustomerInterface;
use Aurora\Module\Platform\User\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use const DATE_ATOM;

#[AsAlias(CustomerSerializerInterface::class)]
class CustomerSerializer implements CustomerSerializerInterface
{
    /** @return array<string, mixed> */
    public function serialize(CustomerInterface $customer): array
    {
        $user = $customer->getUser();

        return [
            'id' => $customer->getId(),
            'legalName' => $customer->getLegalName(),
            'legalForm' => $customer->getLegalForm(),
            // Sent as cents, formatted by the page: a number formatted server
            // side arrives as a string the form then has to parse back, and
            // the two parsers disagree the first time someone types a comma.
            'shareCapitalCents' => $customer->getShareCapitalCents(),
            'shareCapitalCurrency' => $customer->getShareCapitalCurrency()?->value,
            'registeredOffice' => $customer->getRegisteredOffice(),
            'siret' => $customer->getSiret(),
            'tradeRegister' => $customer->getTradeRegister(),
            'vatNumber' => $customer->getVatNumber(),
            'activitySector' => $customer->getActivitySector(),
            'representativeFirstName' => $customer->getRepresentativeFirstName(),
            'representativeLastName' => $customer->getRepresentativeLastName(),
            'representativeFullName' => $customer->getRepresentativeFullName(),
            'representativeRole' => $customer->getRepresentativeRole(),
            'contractualEmail' => $customer->getContractualEmail(),
            'phone' => $customer->getPhone(),
            'userId' => $user?->getId(),
            // The account's name is here so the list can say who it is rather
            // than showing an id, and nothing more of the account travels: this
            // payload reaches a page about companies, not about people.
            'userName' => $user instanceof User ? $user->getName() : null,
            'userEmail' => $user?->getUserIdentifier(),
            'createdAt' => $customer->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
