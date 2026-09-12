<?php

declare(strict_types=1);

namespace Aurora\Fixtures\Studio;

use Aurora\Core\Money\Enum\CurrencyEnum;
use Aurora\Fixtures\Core\AppFixtures;
use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnum;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Studio\Contract\Dto\ContractInput;
use Aurora\Module\Studio\Contract\Dto\ContractTemplateInput;
use Aurora\Module\Studio\Contract\Dto\ContractTemplateVersionInput;
use Aurora\Module\Studio\Contract\Entity\ContractInterface;
use Aurora\Module\Studio\Contract\Entity\ContractTemplateInterface;
use Aurora\Module\Studio\Contract\Entity\ContractTemplateVersionInterface;
use Aurora\Module\Studio\Contract\Enum\ContractStatusEnum;
use Aurora\Module\Studio\Contract\Enum\ContractTemplateKindEnum;
use Aurora\Module\Studio\Contract\Enum\ContractTerminationOriginEnum;
use Aurora\Module\Studio\Contract\Manager\ContractManagerInterface;
use Aurora\Module\Studio\Contract\Manager\ContractTemplateManagerInterface;
use Aurora\Module\Studio\Contract\Repository\ContractRepository;
use Aurora\Module\Studio\Contract\Repository\ContractTemplateRepository;
use Aurora\Module\Studio\Contract\Signature\Entity\ContractSignature;
use Aurora\Module\Studio\Contract\Signature\Enum\ContractSignatureRoleEnum;
use Aurora\Module\Studio\Customer\Dto\CustomerInput;
use Aurora\Module\Studio\Customer\Entity\CustomerInterface;
use Aurora\Module\Studio\Customer\Manager\CustomerManagerInterface;
use Aurora\Module\Studio\Customer\Repository\CustomerRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Demo content for the Studio module: three customers, three trames and a
 * contract in each state the list can draw.
 *
 * Written because the module had none, and because a module with no demo data
 * has no screenshot of itself - the three screens could be opened locally and
 * showed "nothing yet", which is not a picture anybody can put on a page.
 *
 * **Every word of contract wording here is invented.** The five real trames
 * live in production and nowhere else: this repository is public, and a real
 * client's clauses have no business in it. What the demo needs is text of the
 * right shape and length - a title, a few clauses, the tokens - not the real
 * text.
 *
 * **The customers are invented too**, with SIRETs that pass the checksum
 * because the validator is real and would reject a made-up string of digits.
 * They are the same three names the rest of the demo data uses, so a reader
 * moving from the users screen to the customers screen recognises them.
 *
 * **Dates are relative to today**, like the calendar fixture next door: a
 * contract signed on a date written into the file is a contract that reads as
 * ancient history six months from now.
 *
 * **Re-running is safe.** `make demo` loads with `--append`, so everything is
 * found before it is created and the contracts are only built for trames this
 * run created itself.
 */
class StudioDemoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /**
     * The provider identity the trames read through `{{provider.*}}`.
     *
     * Filled here because the seal refuses to run while one of them is empty -
     * a guard added on purpose, so that a contract can never go out quoting a
     * blank. A demo instance therefore needs an identity of its own, and an
     * invented one is the only honest choice in a public repository.
     */
    private const array PROVIDER = [
        'studio_provider_name' => 'Studio Aurora (demonstration)',
        'studio_provider_representative' => 'Camille Vasseur, gerante',
        'studio_provider_address' => '12 rue des Fabriques, 69000 Lyon',
        'studio_provider_siret' => '83787330207491',
        'studio_provider_ape_code' => '62.01Z (programmation informatique)',
        'studio_provider_vat_mention' => 'TVA non applicable, article 293 B du CGI',
        'studio_provider_email' => 'contact@studio-aurora.test',
        'studio_provider_phone' => '04 00 00 00 00',
        'studio_provider_bank_holder' => 'Studio Aurora',
        'studio_provider_bank_iban' => 'FR7630001007941234567890185',
        'studio_provider_bank_bic' => 'DEMOFRPP',
        'studio_provider_bank_name' => 'Banque de demonstration',
    ];

    public function __construct(
        private readonly CustomerManagerInterface $customers,
        private readonly CustomerRepository $customerRepository,
        private readonly ContractTemplateManagerInterface $templates,
        private readonly ContractTemplateRepository $templateRepository,
        private readonly ContractManagerInterface $contracts,
        private readonly ContractRepository $contractRepository,
        private readonly SettingRepository $settings,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public static function getGroups(): array
    {
        return ['demo'];
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->seedProviderIdentity();

        $marie = $this->customer(
            legalName: 'Atelier Dupont',
            legalForm: 'SARL',
            siret: '11281704039760',
            office: '4 place du Marche, 38230 Pont-de-Cheruy',
            firstName: 'Marie',
            lastName: 'Dupont',
            role: 'Gerante',
            email: 'marie.dupont@aurora.app',
            sector: 'Menuiserie',
            capitalCents: 1_000_000,
        );

        $jean = $this->customer(
            legalName: 'Martin Documents',
            legalForm: 'SAS',
            siret: '73245630779356',
            office: '17 avenue de la Gare, 69100 Villeurbanne',
            firstName: 'Jean',
            lastName: 'Martin',
            role: 'President',
            email: 'jean.martin@aurora.app',
            sector: 'Archivage',
            capitalCents: 5_000_000,
        );

        $sophie = $this->customer(
            legalName: 'Roux Photographie',
            legalForm: 'Entreprise individuelle',
            siret: '98700210228712',
            office: '3 chemin des Vignes, 38200 Vienne',
            firstName: 'Sophie',
            lastName: 'Roux',
            role: 'Photographe',
            email: 'sophie.roux@aurora.app',
            sector: 'Photographie',
            capitalCents: null,
        );

        // A body and an annex, which is the pairing the module is built around:
        // the body never changes per offer, the annex carries what does. Plus a
        // second body left with an unpublished draft, so the list shows the
        // amber badge and the "open draft" state without anybody having to
        // click first.
        $monthly = $this->template('Contrat de prestation mensuelle', ContractTemplateKindEnum::Body, $this->monthlyBody());
        $annex = $this->template('Annexe - formule Suivi', ContractTemplateKindEnum::Annex, $this->annexFormula());
        $oneShot = $this->template('Contrat de prestation ponctuelle', ContractTemplateKindEnum::Body, $this->oneShotBody());
        $amendmentTrame = $this->template('Avenant', ContractTemplateKindEnum::Body, $this->amendmentBody());

        // The draft that stays a draft. Opened after publication, so the trame
        // has both a version in force and a version being written - the pair
        // the version badge exists to tell apart. Only when there is not one
        // already: a trame may hold a single draft, guaranteed by a partial
        // index, and a second `make demo` must not go asking for a second.
        if (!$oneShot->getDraft() instanceof ContractTemplateVersionInterface) {
            $this->templates->openDraft($oneShot);
            $this->entityManager->flush();
        }

        // Nothing below is built if the instance already has contracts. The
        // seal mints a reference from a yearly sequence, so a second run would
        // not collide - it would just quietly double a list that is meant to be
        // read, which is worse.
        if (0 !== $this->contractRepository->count([])) {
            return;
        }

        // 1. A draft, still editable, no reference yet.
        $this->contract($marie, $monthly, $annex, 490_00, '+1 month', [
            'formule' => 'Suivi',
            'duree' => '12 mois',
        ]);

        // 2. Sealed and sent, waiting for an answer. The state most of the list
        //    is in on any given day.
        $waiting = $this->contract($jean, $monthly, $annex, 690_00, '+2 weeks', [
            'formule' => 'Suivi',
            'duree' => '24 mois',
        ]);
        $this->seal($waiting, ContractStatusEnum::Sent);
        $waiting->markReminded(new DateTimeImmutable('-1 day'));

        // 3. Signed by the customer and countersigned: a concluded contract,
        //    with both signatures on the same hash.
        $concluded = $this->contract($sophie, $monthly, $annex, 390_00, '+1 week', [
            'formule' => 'Essentiel',
            'duree' => '12 mois',
        ]);
        $this->seal($concluded, ContractStatusEnum::Countersigned);
        $this->sign($concluded, ContractSignatureRoleEnum::Customer, $sophie, '-2 days');
        $this->sign($concluded, ContractSignatureRoleEnum::Provider, $sophie, '-1 day');

        // 4. An amendment of the concluded one, which is the whole point of the
        //    design: the parent is untouched and this document says what it
        //    changes.
        $amendment = $this->amendment($concluded, $amendmentTrame, 490_00, '+1 month', [
            'avenant_objet' => 'Passage de la formule Essentiel a la formule Suivi',
            'avenant_duree' => 'Jusqu au terme du contrat initial',
        ]);

        $this->seal($amendment, ContractStatusEnum::Countersigned);
        $this->sign($amendment, ContractSignatureRoleEnum::Customer, $sophie, '-1 day');
        $this->sign($amendment, ContractSignatureRoleEnum::Provider, $sophie, 'now');

        // 5. A refusal, because a list that only shows agreements teaches the
        //    wrong thing about what the module handles.
        $refused = $this->contract($marie, $monthly, $annex, 890_00, '+3 weeks', [
            'formule' => 'Suivi',
            'duree' => '36 mois',
        ]);
        $this->seal($refused, ContractStatusEnum::Sent);
        $refused->refuse(
            new DateTimeImmutable('-1 day'),
            'Budget reporte au prochain exercice.',
            '203.0.113.24',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        );

        // 6. A terminated relationship: concluded, then ended with notice.
        $ended = $this->contract($jean, $monthly, $annex, 290_00, '-1 year', [
            'formule' => 'Essentiel',
            'duree' => '12 mois',
        ]);
        $this->seal($ended, ContractStatusEnum::Countersigned);
        $this->sign($ended, ContractSignatureRoleEnum::Customer, $jean, '-4 days');
        $this->sign($ended, ContractSignatureRoleEnum::Provider, $jean, '-3 days');
        $ended->terminate(
            new DateTimeImmutable('now'),
            new DateTimeImmutable('+2 months'),
            ContractTerminationOriginEnum::Customer,
            'Fin de la mission, arret a l echeance annuelle.',
        );

        $this->entityManager->flush();
    }

    /**
     * Twelve settings, overwritten whatever was there.
     *
     * Deliberately not "only when empty", which is what this did first and
     * which leaked immediately: a local database seeded from production
     * already held the real identity, so the demo contracts sealed a real
     * name, a real SIRET and a real IBAN into their snapshots - and a snapshot
     * cannot be corrected afterwards, which is the whole point of it.
     *
     * The reasoning is the same one that keeps the real trames out of a local
     * instance. A demo instance is for looking at and for taking pictures of,
     * so nothing that must not appear in a picture belongs in it.
     */
    private function seedProviderIdentity(): void
    {
        foreach (self::PROVIDER as $key => $value) {
            $this->settings->set(ApplicationParameterEnum::from($key)->value, $value);
        }
    }

    private function customer(
        string $legalName,
        string $legalForm,
        string $siret,
        string $office,
        string $firstName,
        string $lastName,
        string $role,
        string $email,
        string $sector,
        ?int $capitalCents,
    ): CustomerInterface {
        $existing = $this->customerRepository->findOneBy(['legalName' => $legalName]);

        if (null !== $existing) {
            return $existing;
        }

        return $this->customers->create(new CustomerInput(
            legalName: $legalName,
            legalForm: $legalForm,
            shareCapitalCents: $capitalCents,
            shareCapitalCurrency: null === $capitalCents ? null : CurrencyEnum::EUR,
            registeredOffice: $office,
            siret: $siret,
            vatNumber: null,
            activitySector: $sector,
            representativeFirstName: $firstName,
            representativeLastName: $lastName,
            representativeRole: $role,
            contractualEmail: $email,
        ));
    }

    /**
     * A trame with its first version published, or the one already there.
     *
     * Found by name, because that is what a person reads in the back office.
     * Returning the existing one rather than skipping matters on a database
     * that has been seeded from elsewhere: the demo has to be able to run on
     * top of whatever is already there without doubling it.
     *
     * @param list<array<string, mixed>> $blocks
     */
    private function template(string $name, ContractTemplateKindEnum $kind, array $blocks): ContractTemplateInterface
    {
        $existing = $this->templateRepository->findOneBy(['name' => $name]);

        if (null !== $existing) {
            return $existing;
        }

        $template = $this->templates->create(new ContractTemplateInput(name: $name, kind: $kind));
        $this->entityManager->flush();

        $version = $template->getDraft();

        if (!$version instanceof ContractTemplateVersionInterface) {
            return $template;
        }

        $this->templates->updateDraft($version, new ContractTemplateVersionInput(
            translations: ['fr' => ['title' => $name, 'content' => ['blocks' => $blocks]]],
        ));
        $this->templates->publish($version);

        $this->entityManager->flush();

        return $template;
    }

    /** @param array<string, string> $customFields */
    private function contract(
        CustomerInterface $customer,
        ContractTemplateInterface $body,
        ?ContractTemplateInterface $annex,
        int $amountCents,
        string $effectiveDate,
        array $customFields,
    ): ContractInterface {
        $contract = $this->contracts->create(new ContractInput(
            customerId: $customer->getId(),
            bodyTemplateId: $body->getId(),
            annexTemplateId: $annex?->getId(),
            locale: 'fr',
            amountCents: $amountCents,
            amountCurrency: CurrencyEnum::EUR->value,
            effectiveDate: new DateTimeImmutable($effectiveDate)->format('Y-m-d'),
            customFields: $customFields,
        ));

        $this->entityManager->flush();

        return $contract;
    }

    /** @param array<string, string> $customFields */
    private function amendment(
        ContractInterface $parent,
        ContractTemplateInterface $body,
        int $amountCents,
        string $effectiveDate,
        array $customFields,
    ): ContractInterface {
        $customer = $parent->getCustomer();

        $contract = $this->contracts->create(new ContractInput(
            customerId: $customer->getId(),
            bodyTemplateId: $body->getId(),
            locale: 'fr',
            amountCents: $amountCents,
            amountCurrency: CurrencyEnum::EUR->value,
            effectiveDate: new DateTimeImmutable($effectiveDate)->format('Y-m-d'),
            customFields: $customFields,
            amendsId: $parent->getId(),
        ));

        $this->entityManager->flush();

        return $contract;
    }

    /**
     * Seal, then move the clock back and set the status the demo wants.
     *
     * The seal itself goes through the manager, because everything worth
     * looking at on the document page is computed there: the canonical form,
     * the hash, the rendered HTML and the reference. Only the status is then
     * set directly - the real paths to `sent` and `countersigned` post emails,
     * and a fixture that fills a mailbox every time it loads is a fixture
     * people stop loading.
     *
     * The seal date is not back-dated, and cannot be: `frozenAt` is written by
     * the seal and has no setter, which is the immutability doing its job. So
     * every demo contract reads as sealed today, and the dates that do vary -
     * effective date, signature, notice - are set around that.
     */
    private function seal(ContractInterface $contract, ContractStatusEnum $status): void
    {
        $this->contracts->freeze($contract);
        $this->entityManager->flush();

        $contract->setStatus($status);
    }

    private function sign(
        ContractInterface $contract,
        ContractSignatureRoleEnum $role,
        CustomerInterface $customer,
        string $signedAt,
    ): void {
        $signature = new ContractSignature();
        $signature
            ->setContract($contract)
            ->setRole($role)
            ->setDeclaredFirstName(ContractSignatureRoleEnum::Provider === $role ? 'Camille' : (string) $customer->getRepresentativeFirstName())
            ->setDeclaredLastName(ContractSignatureRoleEnum::Provider === $role ? 'Vasseur' : (string) $customer->getRepresentativeLastName())
            ->setDeclaredEmail(ContractSignatureRoleEnum::Provider === $role ? 'contact@studio-aurora.test' : $customer->getContractualEmail())
            ->setDeclaredPlace(ContractSignatureRoleEnum::Provider === $role ? 'Lyon' : 'Pont-de-Cheruy')
            ->setDeclaredDate(new DateTimeImmutable($signedAt))
            ->setSignedAt(new DateTimeImmutable($signedAt))
            ->setSignedContentHash((string) $contract->getContentHash())
            ->setIpAddress(ContractSignatureRoleEnum::Provider === $role ? '198.51.100.7' : '203.0.113.24')
            ->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)');

        if (ContractSignatureRoleEnum::Customer === $role) {
            $signature->setChallengeVerifiedAt(new DateTimeImmutable($signedAt));
        }

        $this->entityManager->persist($signature);
    }

    /** @return list<array<string, mixed>> */
    private function monthlyBody(): array
    {
        return [
            $this->header('Objet du contrat'),
            $this->paragraph('Le present contrat definit les conditions dans lesquelles {{provider.name}}, representee par {{provider.representative}}, assure pour {{customer.legal_name}} une prestation de suivi mensuel de son site internet.'),
            $this->header('Les parties'),
            $this->paragraph('Le prestataire : {{provider.name}}, {{provider.address}}, SIRET {{provider.siret}}, code APE {{provider.ape_code}}. {{provider.vat_mention}}.'),
            $this->paragraph('Le client : {{customer.legal_name}}, {{customer.legal_form}} au capital de {{customer.share_capital}}, dont le siege est {{customer.registered_office}}, SIRET {{customer.siret}}, representee par {{customer.representative_full_name}} en qualite de {{customer.representative_role}}.'),
            $this->header('Duree et formule'),
            $this->paragraph('La formule retenue est la formule {{contract.custom.formule}}, pour une duree de {{contract.custom.duree}} a compter du {{contract.effective_date}}.'),
            $this->header('Prix'),
            $this->paragraph('La prestation est facturee {{contract.amount}} par mois, payable a reception de facture par virement sur le compte {{provider.bank_iban}} ouvert au nom de {{provider.bank_holder}} chez {{provider.bank_name}}.'),
            $this->header('Signature'),
            $this->paragraph('Fait a {{contract.signature_city}}, le {{contract.signature_date}}, en un exemplaire electronique valant original.'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function annexFormula(): array
    {
        return [
            $this->header('Annexe - contenu de la formule {{contract.custom.formule}}'),
            $this->paragraph('La presente annexe fait partie integrante du contrat de reference {{contract.reference}} conclu avec {{customer.legal_name}}.'),
            $this->list([
                'Mises a jour de securite du socle et des dependances, une fois par mois.',
                'Sauvegarde quotidienne, avec verification de restauration une fois par trimestre.',
                'Deux heures d evolutions incluses par mois, non reportables.',
                'Reponse aux demandes sous un jour ouvre.',
            ]),
            $this->paragraph('Toute prestation hors annexe fait l objet d un devis distinct.'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function oneShotBody(): array
    {
        return [
            $this->header('Objet'),
            $this->paragraph('{{provider.name}} realise pour {{customer.legal_name}} la prestation ponctuelle decrite ci-dessous, sans engagement de duree.'),
            $this->header('Prix et reglement'),
            $this->paragraph('Le montant est de {{contract.amount}}, dont un acompte de {{contract.custom.acompte}} a la commande.'),
            $this->header('Signature'),
            $this->paragraph('Fait a {{contract.signature_city}}, le {{contract.signature_date}}.'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function amendmentBody(): array
    {
        return [
            $this->header('Avenant n {{contract.amends_rank}} au contrat {{contract.amends_reference}}'),
            $this->paragraph('Le present avenant modifie le contrat {{contract.amends_reference}} conclu le {{contract.amends_effective_date}} entre {{provider.name}} et {{customer.legal_name}}. Toutes les clauses du contrat initial non modifiees ci-dessous demeurent applicables.'),
            $this->header('Objet de l avenant'),
            $this->paragraph('{{contract.custom.avenant_objet}}'),
            $this->header('Prise d effet et duree'),
            $this->paragraph('Le present avenant prend effet le {{contract.effective_date}} et s applique {{contract.custom.avenant_duree}}.'),
            $this->header('Prix'),
            $this->paragraph('Le montant de la prestation est porte a {{contract.amount}}.'),
            $this->header('Signature'),
            $this->paragraph('Fait a {{contract.signature_city}}, le {{contract.signature_date}}.'),
        ];
    }

    /** @return array<string, mixed> */
    private function header(string $text): array
    {
        return ['type' => 'header', 'data' => ['text' => $text, 'level' => 2]];
    }

    /** @return array<string, mixed> */
    private function paragraph(string $text): array
    {
        return ['type' => 'paragraph', 'data' => ['text' => $text]];
    }

    /**
     * @param list<string> $items
     *
     * @return array<string, mixed>
     */
    private function list(array $items): array
    {
        return [
            'type' => 'list',
            'data' => [
                'style' => 'unordered',
                'meta' => [],
                'items' => array_map(
                    static fn (string $content): array => ['content' => $content, 'meta' => [], 'items' => []],
                    $items,
                ),
            ],
        ];
    }
}
