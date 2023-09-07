<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3\Exceptions;

use Crowdstar\Couchbase3\Bucket;
use Crowdstar\Couchbase3\CustomizedHandler;

class PartiallyImplementedException extends Exception
{
    public function __construct(string $method = '', string $class = CustomizedHandler::class)
    {
        $trace = debug_backtrace();

        $message = $this->parseBacktrace($trace, Bucket::class);
        if (empty($message)) {
            $method  = $method ?: $trace[1]['function'];
            $message = "Method {$trace[1]['class']}::{$trace[1]['function']}() can not be called directly; it is partially implemented in Method {$class}::{$method}().";
        }

        parent::__construct($message);
    }
}
