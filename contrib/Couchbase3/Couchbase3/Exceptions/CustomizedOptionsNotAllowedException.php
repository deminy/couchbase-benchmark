<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3\Exceptions;

use Crowdstar\Couchbase3\Bucket;

class CustomizedOptionsNotAllowedException extends Exception
{
    public function __construct(array $options)
    {
        if (empty($options)) {
            $message = 'An empty array was passed in when thrown out the exception.';
        } else {
            $trace = debug_backtrace();
            if (empty($trace[2]['object'])) {
                $message = 'The exception was thrown out from nowhere.';
            } elseif (!($trace[2]['object'] instanceof Bucket)) {
                $message = sprintf('The exception should be thrown out from a %s instance only; instance %s found.', Bucket::class, get_class($trace[2]['object']));
            } else {
                $message = sprintf('Not allow to pass in extra options in method \%s::%s() (options: %s).', Bucket::class, $trace[2]['function'], implode(', ', array_keys($options)));
            }
        }

        parent::__construct($message);
    }
}
