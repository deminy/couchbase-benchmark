<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3\Exceptions;

/**
 * This exception class is thrown out only when some unexpected behavior happens with \Crowdstar\Couchbase3 classes.
 * e.g., when calling a method that hasn't yet been implemented.
 */
class Exception extends \Exception
{
    /**
     * @return string returns an empty string if no errors found; otherwise, returns a non-empty error string
     */
    protected function parseBacktrace(array &$trace, string $class): string
    {
        if (empty($trace[1]['object'])) {
            return 'The exception was thrown out from nowhere.';
        }
        if (!$trace[1]['object'] instanceof $class) {
            return sprintf(
                'The exception should be thrown out from a %s instance only; instance %s found.',
                $class,
                get_class($trace[1]['object'])
            );
        }

        return '';
    }
}
