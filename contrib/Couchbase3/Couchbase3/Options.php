<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3;

/**
 * This self-define class is to help to easily access option fields in Couchbase 2.
 */
class Options
{
    public const CAS = 'cas';

    public const EXPIRY = 'expiry';

    public const INITIAL = 'initial';

    public const LOCKTIME = 'lockTime';
}
