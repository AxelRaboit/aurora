<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Service;

/**
 * The placeholders a template may carry, and where each one gets its value.
 *
 * Declared in code rather than stored per version, because the set of things
 * that can be filled in is a property of the application, not of a document.
 * A version that writes `{{customer.siret}}` needs no schema of its own: the
 * token either names something this catalogue knows how to resolve, or it does
 * not, and that answer is the same for every template.
 *
 * Only two groups, and the absence of a third is deliberate. The paper trames
 * carry the provider's own identity as ordinary wording - name, SIRET, APE
 * code, address, all typed once into the body - so there is nothing to
 * substitute for it, and a `provider.*` group would advertise tokens with
 * nothing behind them.
 *
 * `customer.*` reads the Customer attached to the contract. `contract.*` reads
 * the contract itself, and those are the fields somebody fills in per
 * signature: the reference, the amount, the date it takes effect, the city and
 * date of signature.
 *
 * The resolution itself lands with the contract, in the phase that builds one.
 * What this class settles now is which tokens exist, so the editor can offer
 * them instead of leaving them to memory and to typos.
 */
final readonly class ContractVariableCatalogue
{
    /**
     * Every token, grouped by where it comes from.
     *
     * The example is not decoration: a token list without one leaves the
     * writer guessing whether a date arrives as 08/09/2026 or 2026-09-08,
     * which is exactly the kind of thing that gets discovered in a signed PDF.
     *
     * @return list<array{
     *     group: string,
     *     labelKey: string,
     *     variables: list<array{token: string, labelKey: string, example: string}>
     * }>
     */
    public function groups(): array
    {
        return [
            [
                'group' => 'customer',
                'labelKey' => 'backend.accounting.contract_templates.variables.customer',
                'variables' => [
                    $this->variable('customer.legal_name', 'Boulangerie Durand'),
                    $this->variable('customer.legal_form', 'SARL'),
                    $this->variable('customer.share_capital', '10 000 €'),
                    $this->variable('customer.registered_office', '12 rue des Lilas, 69003 Lyon'),
                    $this->variable('customer.siret', '732 829 320 00074'),
                    $this->variable('customer.trade_register', 'Lyon B 732 829 320'),
                    $this->variable('customer.vat_number', 'FR12732829320'),
                    $this->variable('customer.activity_sector', 'boulangerie artisanale'),
                    $this->variable('customer.representative_full_name', 'Camille Durand'),
                    $this->variable('customer.representative_role', 'Gérante'),
                    $this->variable('customer.contractual_email', 'contact@durand.fr'),
                    $this->variable('customer.phone', '06 12 34 56 78'),
                ],
            ],
            [
                'group' => 'contract',
                'labelKey' => 'backend.accounting.contract_templates.variables.contract',
                'variables' => [
                    $this->variable('contract.reference', 'CM-2026-0001'),
                    $this->variable('contract.amount', '850 €'),
                    $this->variable('contract.effective_date', '01/10/2026'),
                    $this->variable('contract.signature_city', 'Lyon'),
                    $this->variable('contract.signature_date', '08/09/2026'),
                ],
            ],
        ];
    }

    /**
     * Every token as a flat list, for checking a document against the
     * catalogue.
     *
     * @return list<string>
     */
    public function tokens(): array
    {
        $tokens = [];

        foreach ($this->groups() as $group) {
            foreach ($group['variables'] as $variable) {
                $tokens[] = $variable['token'];
            }
        }

        return $tokens;
    }

    public function knows(string $token): bool
    {
        return in_array($token, $this->tokens(), true);
    }

    /** @return array{token: string, labelKey: string, example: string} */
    private function variable(string $token, string $example): array
    {
        return [
            'token' => $token,
            // The label key mirrors the token, so adding a variable is one
            // entry here and one line in each catalogue rather than a mapping
            // table to keep in agreement.
            'labelKey' => 'backend.accounting.contract_templates.variables.'.str_replace('.', '_', $token),
            'example' => $example,
        ];
    }
}
