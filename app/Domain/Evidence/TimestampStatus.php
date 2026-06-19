<?php

declare(strict_types=1);

namespace App\Domain\Evidence;

/**
 * The state of an evidence capture's RFC-3161 trusted-timestamp seal.
 */
enum TimestampStatus: string
{
    /** Not yet sealed — the authority was unreachable; eligible for retry. */
    case Pending = 'pending';

    /** Sealed: a third-party Timestamp Authority signed the capture's hash. */
    case Signed = 'signed';

    /** The authority rejected the request (a hard error); eligible for retry. */
    case Failed = 'failed';

    /** Trusted timestamping is switched off — only the visible band applies. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return (string) __('app.timestamp.status_'.$this->value);
    }
}
