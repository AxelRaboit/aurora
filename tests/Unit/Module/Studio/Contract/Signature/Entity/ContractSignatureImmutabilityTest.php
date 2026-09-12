<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Studio\Contract\Signature\Entity;

use Aurora\Module\Studio\Contract\Entity\Contract;
use Aurora\Module\Studio\Contract\Signature\Entity\ContractSignature;
use Aurora\Module\Studio\Contract\Signature\Enum\ContractSignatureRoleEnum;
use Aurora\Module\Studio\Contract\Signature\Exception\SignedContractIsImmutableException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The strictest of the module's three immutability guards.
 *
 * A template version stops changing when it is published, a contract when it is
 * sealed, and a signature the instant it exists. There is no version of
 * amending a record of what somebody did that is not falsifying it, so the row
 * refuses every write once it has been persisted.
 */
final class ContractSignatureImmutabilityTest extends TestCase
{
    public function testAnUnsavedSignatureCanBeBuilt(): void
    {
        $signature = $this->signature();

        self::assertSame('Camille Durand', $signature->getDeclaredFullName());
        self::assertSame(ContractSignatureRoleEnum::Customer, $signature->getRole());
        self::assertSame('Lyon', $signature->getDeclaredPlace());
    }

    /**
     * The declared date and the observed one are both kept.
     *
     * A signer writing yesterday's date is a fact about the document worth
     * preserving rather than correcting, and the server's clock is the one
     * nobody chose.
     */
    public function testTheDeclaredDateAndTheServerClockAreBothKept(): void
    {
        $signature = $this->signature();

        self::assertSame('2026-09-08', $signature->getDeclaredDate()->format('Y-m-d'));
        self::assertSame('2026-09-09 14:32:11', $signature->getSignedAt()->format('Y-m-d H:i:s'));
        self::assertNotSame(
            $signature->getDeclaredDate()->format('Y-m-d'),
            $signature->getSignedAt()->format('Y-m-d'),
        );
    }

    public function testARecordedSignatureRefusesEveryWrite(): void
    {
        $signature = $this->recorded();

        foreach ([
            fn () => $signature->setDeclaredFirstName('Autre'),
            fn () => $signature->setDeclaredLastName('Nom'),
            fn () => $signature->setDeclaredEmail('autre@example.test'),
            fn () => $signature->setDeclaredPlace('Paris'),
            fn () => $signature->setDeclaredDate(new DateTimeImmutable('2030-01-01')),
            fn () => $signature->setSignedAt(new DateTimeImmutable('2030-01-01')),
            fn () => $signature->setIpAddress('10.0.0.1'),
            fn () => $signature->setUserAgent('another agent'),
            fn () => $signature->setSignedContentHash(str_repeat('f', 64)),
            fn () => $signature->setSignatureImage('data:image/png;base64,AAAA'),
            fn () => $signature->setChallengeVerifiedAt(new DateTimeImmutable()),
            fn () => $signature->setLinkSelector(str_repeat('a', 32)),
            fn () => $signature->setRole(ContractSignatureRoleEnum::Provider),
        ] as $write) {
            try {
                $write();
                self::fail('A recorded signature accepted a write.');
            } catch (SignedContractIsImmutableException) {
                // What every one of them has to do.
                self::assertTrue(true);
            }
        }
    }

    /**
     * The column that makes a divergence detectable.
     *
     * The hash is copied at signing rather than read back from the contract, so
     * if the document is ever re-sealed the two disagree and this row still
     * says what was agreed to.
     */
    public function testASignatureKnowsWhenTheDocumentMovedUnderIt(): void
    {
        $contract = new Contract();
        $signature = new ContractSignature();
        $signature
            ->setContract($contract)
            ->setSignedContentHash(str_repeat('a', 64));

        // A contract with no seal yet: the hashes cannot match, and the
        // signature says so rather than assuming.
        self::assertFalse($signature->coversCurrentDocument());
    }

    private function signature(): ContractSignature
    {
        $signature = new ContractSignature();
        $signature
            ->setContract(new Contract())
            ->setRole(ContractSignatureRoleEnum::Customer)
            ->setDeclaredFirstName('Camille')
            ->setDeclaredLastName('Durand')
            ->setDeclaredEmail('camille@durand.test')
            ->setDeclaredPlace('Lyon')
            ->setDeclaredDate(new DateTimeImmutable('2026-09-08'))
            ->setSignedAt(new DateTimeImmutable('2026-09-09 14:32:11'))
            ->setIpAddress('203.0.113.42')
            ->setUserAgent('Mozilla/5.0')
            ->setLinkSelector(str_repeat('b', 32))
            ->setChallengeVerifiedAt(new DateTimeImmutable('2026-09-09 14:31:40'))
            ->setSignedContentHash(str_repeat('c', 64))
            ->setSignatureImage('data:image/png;base64,AAAA');

        return $signature;
    }

    /**
     * A signature as it is once persisted.
     *
     * The id is set by reflection because that is what Doctrine does, and the
     * guard reads the id rather than a flag: a row that has been written has
     * been recorded, and nothing else needs to be remembered.
     */
    private function recorded(): ContractSignature
    {
        $signature = $this->signature();

        $id = new ReflectionProperty(ContractSignature::class, 'id');
        $id->setValue($signature, 42);

        return $signature;
    }
}
