<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3\Exceptions;

abstract class AbstractMethodNotImplementedException extends Exception
{
    protected string $class;

    public function __construct()
    {
        $trace = debug_backtrace();

        $message = $this->parseBacktrace($trace, $this->class);
        if (empty($message)) {
            $message = sprintf('Method \%s::%s() has not yet been implemented.', $trace[1]['class'], $trace[1]['function']);
        }

        parent::__construct($message);
    }
}
