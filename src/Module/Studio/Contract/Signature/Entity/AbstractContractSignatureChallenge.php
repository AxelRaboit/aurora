<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Signature\Entity;

use Aurora\Module\Studio\Contract\Access\Entity\ContractAccessLinkInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function hash;
use function random_int;
use function sprintf;

/**
 * A six-digit code, mailed to the address the contract names.
 *
 * This is the second factor, and the reason it exists rather than a shared
 * password: a password would have to be transmitted, and transmitting it in the
 * same email as the link protects nothing - whoever reads the mail has both.
 * A code sent at the moment of signing proves something a link cannot, that the
 * person signing controls the mailbox the contract was addressed to.
 *
 * Its own table rather than columns on the link, because a code has a life of
 * its own: several are issued over a session, each expires, each counts its own
 * failed attempts, and only one is ever consumed. Folding that onto the link
 * would mean resetting fields and losing the history of how many times somebody
 * asked.
 *
 * Only a hash is stored. Six digits is a million possibilities, which is why
 * the attempt limit and the expiry carry the security here rather than the
 * length - but a code readable in a database dump would let somebody sign
 * without ever seeing the mailbox, which is the one thing this is for.
 */
#[ORM\MappedSuperclass]
abstract class AbstractContractSignatureChallenge implements ContractSignatureChallengeInterface
{
    /** How long a code stays usable. */
    public const int LIFETIME_MINUTES = 10;

    /**
     * How many wrong codes before this one is dead.
     *
     * Five, against a million possibilities and a ten-minute window. The limit
     * is what makes six digits enough: without it, a script walks the space.
     */
    public const int MAX_ATTEMPTS = 5;

    /**
     * The link the code was issued for.
     *
     * Tied to the address rather than to the contract: revoking a link has to
     * kill the codes issued through it, and a code issued for an address that
     * has since been replaced must not open the new one.
     */
    #[ORM\ManyToOne(targetEntity: ContractAccessLinkInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ContractAccessLinkInterface $link;

    /** SHA-256 of the six digits, hex. */
    #[ORM\Column(length: 64)]
    protected string $hashedCode;

    /** The address the code went to, as it was at the time. */
    #[ORM\Column(length: 180)]
    protected string $sentTo;

    #[ORM\Column]
    protected DateTimeImmutable $expiresAt;

    #[ORM\Column(options: ['default' => 0])]
    protected int $attempts = 0;

    /**
     * When the code was used.
     *
     * Single use: a consumed code is dead even inside its ten minutes. Without
     * this a code could sign twice, and "the code was verified" would stop
     * meaning one act.
     */
    #[ORM\Column(nullable: true)]
    protected ?DateTimeImmutable $consumedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    /**
     * The six digits, held in memory only.
     *
     * Set once when the code is minted, long enough to put it in an email, and
     * never persisted. After that request nothing can recover it.
     */
    protected ?string $plainCode = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    abstract public function getId(): ?int;

    /**
     * Mints a code and returns it, once.
     *
     * `random_int` rather than `rand`: this is a credential, and a predictable
     * one is not one. Padded to six digits so a code starting with a zero is
     * six characters like every other, which the person typing it expects.
     */
    public function mint(): string
    {
        $this->plainCode = sprintf('%06d', random_int(0, 999999));
        $this->hashedCode = self::hashCode($this->plainCode);
        $this->expiresAt = new DateTimeImmutable(sprintf('+%d minutes', self::LIFETIME_MINUTES));

        return $this->plainCode;
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    public function getPlainCode(): ?string
    {
        return $this->plainCode;
    }

    public function getLink(): ContractAccessLinkInterface
    {
        return $this->link;
    }

    public function setLink(ContractAccessLinkInterface $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getHashedCode(): string
    {
        return $this->hashedCode;
    }

    public function getSentTo(): string
    {
        return $this->sentTo;
    }

    public function setSentTo(string $sentTo): static
    {
        $this->sentTo = $sentTo;

        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Counts a wrong code.
     *
     * Counted before the answer is given, and persisted whether or not the
     * caller goes on: a limit that only applies when somebody is polite is not
     * a limit.
     */
    public function recordFailedAttempt(): static
    {
        ++$this->attempts;

        return $this;
    }

    public function getConsumedAt(): ?DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function consume(DateTimeImmutable $at): static
    {
        $this->consumedAt ??= $at;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt instanceof DateTimeImmutable;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt < $now;
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempts < self::MAX_ATTEMPTS;
    }

    /**
     * Whether this code could still be accepted.
     *
     * Three ways to be dead, folded into one question because the answer given
     * to whoever is typing is the same: ask for a new code.
     */
    public function isUsable(DateTimeImmutable $now): bool
    {
        return !$this->isConsumed() && !$this->isExpired($now) && $this->hasAttemptsLeft();
    }
}
