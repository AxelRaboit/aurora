<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Message;

/**
 * Chase the contracts that went out and were never answered.
 *
 * Empty, like the other scheduled messages: what to chase is a question for
 * the handler and the settings, not something a queued payload should have
 * frozen an hour earlier.
 */
final class RemindUnsignedContractsMessage {}
