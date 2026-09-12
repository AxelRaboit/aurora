<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Enum;

/**
 * The top-level folders Aurora stores things under.
 *
 * A case's value is a path prefix, so it is part of where every existing file
 * lives: renaming one strands everything written before the rename. Add and
 * remove, never rename.
 *
 * Four cases were removed in 0.9.132 - `media`, `ocr`, `photo` and `users` -
 * because nothing named them any more. The first three belonged to modules
 * that are gone; `users` never matched reality, since profile photos have
 * always gone to `profile-photos`. Only their own test still asserted their
 * values, which is the shape of a constant nobody uses.
 */
enum StorageAreaEnum: string
{
    case Ged = 'ged';

    case ProfilePhotos = 'profile-photos';

    case Contracts = 'contracts';
}
