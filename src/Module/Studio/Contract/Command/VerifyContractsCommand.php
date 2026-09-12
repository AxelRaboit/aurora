<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Command;

use Aurora\Module\Studio\Contract\Entity\ContractInterface;
use Aurora\Module\Studio\Contract\Repository\ContractRepository;
use Aurora\Module\Studio\Contract\Service\ContractSeal;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function count;
use function sprintf;

/**
 * Recomputes every frozen contract's hash and says what moved.
 *
 * This is the mesure that turns the snapshot from a hope into a fact. The
 * other five in that phase prevent drift; this one detects it. Without it, a
 * document altered by a bad migration, a manual database edit or a bug in a
 * later refactor stays altered and nobody learns, which is precisely the
 * failure mode the whole design is built against: silent, and irreversible by
 * the time it surfaces.
 *
 * Meant to be run on a schedule and after every deployment that touched this
 * module. It reads and never writes, so running it is always safe.
 *
 * Exit code 1 on any mismatch, so a scheduler or a CI step fails on it rather
 * than printing red text nobody reads.
 */
#[AsCommand(
    name: 'aurora:contracts:verify',
    description: 'Recompute the hash of every frozen contract and report any that no longer match',
)]
final class VerifyContractsCommand extends Command
{
    public function __construct(
        private readonly ContractRepository $contracts,
        private readonly ContractSeal $seal,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $frozen = $this->contracts->findFrozen();

        if ([] === $frozen) {
            $io->success('No frozen contract to verify.');

            return Command::SUCCESS;
        }

        $altered = [];
        $unverifiable = [];

        foreach ($frozen as $contract) {
            try {
                if (!$this->seal->verify($contract)) {
                    $altered[] = $this->describe($contract);
                }
            } catch (RuntimeException $exception) {
                // A contract sealed under a canonical form this code no longer
                // implements is not evidence of tampering, and reporting it as
                // such would be the fastest way to make this command
                // untrustworthy. It is listed apart.
                $unverifiable[] = sprintf('%s: %s', $this->describe($contract), $exception->getMessage());
            } catch (Throwable $throwable) {
                $unverifiable[] = sprintf('%s: %s', $this->describe($contract), $throwable->getMessage());
            }
        }

        $io->writeln(sprintf('%d frozen contract(s) checked.', count($frozen)));

        if ([] !== $unverifiable) {
            $io->warning('These contracts could not be verified by this version of the code:');
            $io->listing($unverifiable);
        }

        if ([] !== $altered) {
            $io->error('These contracts no longer match the hash they were sealed with:');
            $io->listing($altered);
            $io->writeln('A signed document has changed since it was sealed. Do not repair the hash: find out what wrote to it.');

            return Command::FAILURE;
        }

        if ([] !== $unverifiable) {
            return Command::FAILURE;
        }

        $io->success(sprintf('Every one of the %d frozen contracts still matches its hash.', count($frozen)));

        return Command::SUCCESS;
    }

    private function describe(ContractInterface $contract): string
    {
        return sprintf(
            '%s (id %d, %s)',
            $contract->getReference() ?? 'no reference',
            (int) $contract->getId(),
            $contract->getCustomer()->getLegalName(),
        );
    }
}
