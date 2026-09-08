<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Access\Controller;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Module\Accounting\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Accounting\Contract\Access\Manager\ContractAccessLinkManagerInterface;
use Aurora\Module\Accounting\Contract\Enum\ContractStatusEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reading a contract without an account.
 *
 * This is the only page of the module a customer ever sees, and it is
 * unauthenticated by design: the address is the credential. Four properties
 * hold it together, all enforced here rather than assumed.
 *
 * - **Nothing in the request widens the view.** The contract shown is the one
 *   the link points at. There is no id to tamper with.
 * - **Every failure looks the same.** Unknown selector, wrong secret, revoked,
 *   expired: one page, 404. Telling them apart tells a stranger which guesses
 *   landed.
 * - **The document is the stored HTML**, never re-rendered. What the customer
 *   reads is byte for byte what the hash covers.
 * - **Nothing here is indexable.** The layout sends `noindex` and the response
 *   sends `Referrer-Policy: no-referrer`, so the address does not leak through
 *   a search engine or through the referrer of any link the reader follows.
 *
 * Read-only for now. The signing form lands with the signature itself, and
 * with the rate limit and the code by email that a write route needs.
 */
#[Route('/contracts', name: 'public_contract')]
final class PublicContractController extends AbstractController
{
    public function __construct(private readonly ContractAccessLinkManagerInterface $links) {}

    /**
     * The alphabets are constrained in the route, so a path carrying anything
     * else never reaches a query.
     */
    #[Route(
        '/{selector}/{token}',
        name: '_show',
        requirements: ['selector' => '[a-f0-9]{32}', 'token' => '[a-f0-9]{64}'],
        methods: [HttpMethodEnum::Get->value],
    )]
    public function show(string $selector, string $token): Response
    {
        $link = $this->links->resolveUsable($selector, $token);

        if (!$link instanceof ContractAccessLinkInterface) {
            return $this->unavailable();
        }

        $this->links->markOpened($link);

        $contract = $link->getContract();

        return $this->privately($this->render('@Accounting/public/contract.html.twig', [
            'contract' => $contract,
            'customer' => $contract->getCustomer(),
            'link' => $link,
            // The document is handed over as it was stored. The template prints
            // it raw on purpose: it is the only markup on this page that must
            // not be regenerated.
            'documentHtml' => $contract->getRenderedHtml() ?? '',
            'isSigned' => $contract->getStatus()->isEngaged(),
            'isConcluded' => ContractStatusEnum::Countersigned === $contract->getStatus(),
        ]));
    }

    private function unavailable(): Response
    {
        // 404 rather than 410: "gone" would confirm that this address was once
        // real, which is one bit more than a stranger should get.
        return $this->privately($this->render(
            '@Accounting/public/unavailable.html.twig',
            [],
            new Response(status: Response::HTTP_NOT_FOUND),
        ));
    }

    /**
     * Headers that keep a secret address from travelling.
     *
     * `no-referrer` so following any link off this page does not hand the URL
     * to another site. `no-store` so a shared machine's back button and a proxy
     * cache do not keep a copy of a contract.
     */
    private function privately(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');

        return $response;
    }
}
