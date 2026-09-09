<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Accounting\Contract;

use Aurora\Core\Content\BlockHtmlSanitizer;
use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Accounting\Contract\Dto\ContractInput;
use Aurora\Module\Accounting\Contract\Dto\ContractTemplateInput;
use Aurora\Module\Accounting\Contract\Dto\ContractTemplateVersionInput;
use Aurora\Module\Accounting\Contract\Entity\Contract;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplate;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionInterface;
use Aurora\Module\Accounting\Contract\Enum\ContractTemplateKindEnum;
use Aurora\Module\Accounting\Contract\Manager\ContractManager;
use Aurora\Module\Accounting\Contract\Manager\ContractTemplateManager;
use Aurora\Module\Accounting\Contract\Repository\ContractTemplateRepository;
use Aurora\Module\Accounting\Contract\Repository\ContractTemplateVersionRepository;
use Aurora\Module\Accounting\Contract\Service\ContractCanonicalizer;
use Aurora\Module\Accounting\Contract\Service\ContractCustomFieldScanner;
use Aurora\Module\Accounting\Contract\Service\ContractDocumentRenderer;
use Aurora\Module\Accounting\Contract\Service\ContractSeal;
use Aurora\Module\Accounting\Contract\Service\ContractVariableCatalogue;
use Aurora\Module\Accounting\Contract\Service\ContractVariableResolver;
use Aurora\Module\Accounting\Customer\Entity\Customer;
use Aurora\Module\Accounting\Customer\Entity\CustomerInterface;
use Aurora\Module\Accounting\Customer\Repository\CustomerRepository;
use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnum;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Dev\Audit\Service\AuditLogger;
use Aurora\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function sprintf;

/**
 * The provider's own identity, read from the settings.
 *
 * The block is the same in every document, and it was typed into all five
 * trames. Five copies is five places to forget when a bank changes, so the
 * wording now asks for `{{provider.*}}` and the settings answer.
 *
 * What the tests pin down is the refusal: a trame that prints a setting nobody
 * filled in must not seal a document with a hole where the SIRET should be,
 * and the message has to say "settings", not "unknown token" - the person
 * reading it has to know where to go.
 */
final class ContractProviderSettingsTest extends IntegrationTestCase
{
    private ContractManager $contracts;

    private ContractTemplateManager $templates;

    private EntityManagerInterface $entityManager;

    private SettingRepository $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->settings = $container->get(SettingRepository::class);

        $canonicalizer = new ContractCanonicalizer();

        $this->templates = new ContractTemplateManager(
            $this->entityManager,
            $container->get(AuditLogger::class),
            $container->get(ContractTemplateVersionRepository::class),
            $container->get(TranslatorInterface::class),
        );

        $this->contracts = new ContractManager(
            $this->entityManager,
            $container->get(AuditLogger::class),
            new ContractVariableResolver(new ContractVariableCatalogue(), $this->settings),
            new ContractDocumentRenderer(new BlockHtmlSanitizer()),
            $canonicalizer,
            new ContractSeal($canonicalizer),
            $container->get(SequenceGenerator::class),
            $this->settings,
            $container->get(CustomerRepository::class),
            $container->get(ContractTemplateRepository::class),
            $container->get(TranslatorInterface::class),
            new ContractCustomFieldScanner(),
        );
    }

    protected function tearDown(): void
    {
        $this->entityManager->createQuery(sprintf('DELETE FROM %s', Contract::class))->execute();
        $this->entityManager->createQuery(sprintf('DELETE FROM %s', ContractTemplate::class))->execute();
        $this->entityManager->createQuery(sprintf('DELETE FROM %s', Customer::class))->execute();

        parent::tearDown();
    }

    public function testTheSettingIsWrittenIntoTheSealedDocument(): void
    {
        $this->setProviderSiret('904 512 336 00010');

        $contract = $this->contractAskingForSiret();

        $this->contracts->freeze($contract);

        self::assertStringContainsString('SIRET du prestataire : 904 512 336 00010', (string) $contract->getRenderedHtml());
    }

    /**
     * The refusal, and the wording of it. "Unknown token" would send somebody
     * hunting through the trame for a typo that is not there.
     */
    public function testAnUnsetSettingRefusesTheFreezeAndNamesTheToken(): void
    {
        $this->setProviderSiret('');

        $contract = $this->contractAskingForSiret();

        $this->expectException(FieldException::class);

        try {
            $this->contracts->freeze($contract);
        } catch (FieldException $fieldException) {
            self::assertStringContainsString('provider.siret', $fieldException->getMessage());
            // Nothing consumed: no reference, no snapshot.
            self::assertFalse($contract->isFrozen());
            self::assertNull($contract->getReference());

            throw $fieldException;
        }
    }

    /** A trame that asks for nothing from the settings is unaffected. */
    public function testATrameThatPrintsNoProviderTokenFreezes(): void
    {
        $this->setProviderSiret('');

        $version = $this->publishedVersion('Le forfait est payable d\'avance.');
        $contract = $this->contractFor($version);

        $this->contracts->freeze($contract);

        self::assertTrue($contract->isFrozen());
    }

    private function contractAskingForSiret(): ContractInterface
    {
        return $this->contractFor(
            $this->publishedVersion('SIRET du prestataire : {{provider.siret}}'),
        );
    }

    private function publishedVersion(string $text): ContractTemplateVersionInterface
    {
        $template = $this->templates->create(new ContractTemplateInput('Contrat mensuel', ContractTemplateKindEnum::Body));
        $version = $template->getDraft();

        self::assertInstanceOf(ContractTemplateVersionInterface::class, $version);

        $this->templates->updateDraft($version, new ContractTemplateVersionInput([
            'fr' => [
                'title' => 'CONTRAT DE PRESTATION DE SERVICES',
                'content' => ['blocks' => [
                    ['type' => 'paragraph', 'data' => ['text' => $text]],
                ]],
            ],
        ]));

        $this->templates->publish($version);

        return $version;
    }

    private function contractFor(ContractTemplateVersionInterface $version): ContractInterface
    {
        return $this->contracts->create(new ContractInput(
            customerId: $this->customer()->getId(),
            bodyTemplateId: $version->getTemplate()->getId(),
            locale: 'fr',
        ));
    }

    private function setProviderSiret(string $value): void
    {
        // Written through the repository, which is what the settings screen
        // writes through too, and which owns the cache the resolver reads.
        $this->settings->set(ApplicationParameterEnum::AccountingProviderSiret->value, $value);
    }

    private function customer(): CustomerInterface
    {
        $customer = new Customer();
        $customer
            ->setLegalName('Boulangerie Durand')
            ->setSiret('73282932000074')
            ->setContractualEmail('contact@durand.test')
            ->setRepresentativeFirstName('Camille')
            ->setRepresentativeLastName('Durand');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }
}
