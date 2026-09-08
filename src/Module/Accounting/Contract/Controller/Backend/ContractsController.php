<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Http\JsonRequestTrait;
use Aurora\Core\Http\JsonResponseTrait;
use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Core\Validation\Service\PayloadValidator;
use Aurora\Module\Accounting\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Accounting\Contract\Access\Manager\ContractAccessLinkManagerInterface;
use Aurora\Module\Accounting\Contract\Access\Repository\ContractAccessLinkRepository;
use Aurora\Module\Accounting\Contract\Dto\ContractInputFactoryInterface;
use Aurora\Module\Accounting\Contract\Dto\ContractInputInterface;
use Aurora\Module\Accounting\Contract\Entity\Contract;
use Aurora\Module\Accounting\Contract\Exception\FrozenContractIsImmutableException;
use Aurora\Module\Accounting\Contract\Manager\ContractManagerInterface;
use Aurora\Module\Accounting\Contract\Serializer\ContractSerializerInterface;
use Aurora\Module\Accounting\Contract\View\ContractsViewBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/backend/accounting/contracts', name: 'backend_accounting_contracts')]
#[IsGranted('accounting.contracts.view')]
class ContractsController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        protected readonly ContractManagerInterface $contractManager,
        protected readonly ContractInputFactoryInterface $inputFactory,
        protected readonly ContractSerializerInterface $serializer,
        protected readonly ContractsViewBuilder $viewBuilder,
        protected readonly PayloadValidator $payloadValidator,
        protected readonly ContractAccessLinkManagerInterface $accessLinks,
        protected readonly ContractAccessLinkRepository $accessLinkRepository,
        protected readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: '', methods: [HttpMethodEnum::Get->value])]
    public function index(): Response
    {
        return $this->render('@Accounting/backend/contracts/index.html.twig', $this->viewBuilder->indexView());
    }

    /**
     * One contract, with its document if it has one.
     *
     * This is where somebody comes to read what was actually sent, and where
     * the seal is checked - recomputed on the spot rather than read back.
     */
    #[Route('/{id}', name: '_show', requirements: ['id' => '\d+'], methods: [HttpMethodEnum::Get->value])]
    public function show(Contract $contract): Response
    {
        return $this->render('@Accounting/backend/contracts/show.html.twig', $this->viewBuilder->showView($contract));
    }

    #[Route('/create', name: '_create', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('accounting.contracts.create')]
    public function create(Request $request): JsonResponse
    {
        return $this->withInput($request, fn (ContractInputInterface $input): JsonResponse => $this->jsonSuccess([
            'contract' => $this->serializer->serialize($this->contractManager->create($input)),
            'contracts' => $this->viewBuilder->contracts(),
        ]));
    }

    #[Route('/{id}/update', name: '_update', requirements: ['id' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('accounting.contracts.edit')]
    public function update(Contract $contract, Request $request): JsonResponse
    {
        return $this->withInput($request, function (ContractInputInterface $input) use ($contract): JsonResponse {
            try {
                $this->contractManager->update($contract, $input);
            } catch (FrozenContractIsImmutableException) {
                return $this->frozenRefusal();
            }

            return $this->jsonSuccess($this->viewBuilder->listPayload());
        });
    }

    #[Route('/{id}/delete', name: '_delete', requirements: ['id' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('accounting.contracts.delete')]
    public function delete(Contract $contract): JsonResponse
    {
        try {
            $this->contractManager->delete($contract);
        } catch (FrozenContractIsImmutableException) {
            return $this->frozenRefusal();
        }

        return $this->jsonSuccess($this->viewBuilder->listPayload());
    }

    /**
     * Seals the document.
     *
     * The point of no return, and the screen says so before the click. Under
     * `edit` for now; when the link and the mail land, sending will be its own
     * permission because it is the act that reaches somebody outside.
     */
    #[Route('/{id}/freeze', name: '_freeze', requirements: ['id' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('accounting.contracts.edit')]
    public function freeze(Contract $contract): JsonResponse
    {
        try {
            $this->contractManager->freeze($contract);
        } catch (FieldException $fieldException) {
            return $this->jsonInvalidInput([$fieldException->getField() => $fieldException->getMessage()]);
        } catch (FrozenContractIsImmutableException) {
            return $this->frozenRefusal();
        }

        return $this->jsonSuccess([
            'contract' => $this->serializer->serialize($contract),
            'contracts' => $this->viewBuilder->contracts(),
            'showPath' => $this->generateUrl('backend_accounting_contracts_show', ['id' => $contract->getId()]),
        ]);
    }

    /**
     * Mints an address and mails it.
     *
     * Its own permission, unlike sealing: this is the act that reaches somebody
     * outside the application, and the person allowed to prepare a contract is
     * not necessarily the person allowed to send one.
     */
    #[Route('/{id}/send', name: '_send', requirements: ['id' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('accounting.contracts.send')]
    public function send(Contract $contract): JsonResponse
    {
        try {
            $link = $this->accessLinks->send($contract);
        } catch (FieldException $fieldException) {
            return $this->jsonInvalidInput([$fieldException->getField() => $fieldException->getMessage()]);
        }

        return $this->jsonSuccess([
            'contract' => $this->serializer->serialize($contract),
            'contracts' => $this->viewBuilder->contracts(),
            'sentTo' => $link->getRecipientEmail(),
        ]);
    }

    /**
     * Closes the address without touching the document.
     *
     * Under `send` rather than `delete`: revoking is undoing a send, and
     * whoever may open a door may close it.
     */
    #[Route('/{id}/revoke-link', name: '_revoke_link', requirements: ['id' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('accounting.contracts.send')]
    public function revokeLink(Contract $contract): JsonResponse
    {
        $link = $this->accessLinkRepository->findActiveFor($contract);

        if (!$link instanceof ContractAccessLinkInterface) {
            return $this->jsonInvalidInput([
                'status' => $this->translator->trans('backend.accounting.contracts.errors.no_active_link'),
            ]);
        }

        $this->accessLinks->revoke($link);

        return $this->jsonSuccess([
            'contract' => $this->serializer->serialize($contract),
            'contracts' => $this->viewBuilder->contracts(),
        ]);
    }

    /** @param callable(ContractInputInterface):JsonResponse $save */
    private function withInput(Request $request, callable $save): JsonResponse
    {
        $input = $this->inputFactory->fromArray($this->decodeJson($request));

        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        try {
            return $save($input);
        } catch (FieldException $fieldException) {
            return $this->jsonInvalidInput([$fieldException->getField() => $fieldException->getMessage()]);
        }
    }

    private function frozenRefusal(): JsonResponse
    {
        return $this->jsonInvalidInput([
            'status' => $this->translator->trans('backend.accounting.contracts.errors.already_frozen'),
        ]);
    }
}
