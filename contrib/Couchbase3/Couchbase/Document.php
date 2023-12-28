<?php

declare(strict_types=1);

namespace Couchbase;

/**
 * Represents Couchbase Document, which stores metadata and the value.
 *
 * The instances of this class returned by K/V commands of the \Couchbase\Bucket.
 *
 * Class Couchbase\Document exists in Couchbase 2 only; it doesn't exist in Couchbase 3.
 *
 * @see Bucket
 */
class Document
{
    /**
     * @var Exception|null exception object in case of error, or NULL
     */
    public ?Exception $error;

    /**
     * @var mixed the value stored in the Couchbase
     */
    public $value;

    /**
     * @var int|null flags, describing the encoding of the document on the server side
     */
    public ?int $flags;

    /**
     * @var string|null The last known CAS value of the document
     */
    public ?string $cas;

    /**
     * @var MutationToken The optional, opaque mutation token set after a successful mutation.
     *
     * Note that the mutation token is always NULL, unless they are explicitly enabled on the
     * connection string (`?fetch_mutation_tokens=true`), the server version is supported (>= 4.0.0)
     * and the mutation operation succeeded.
     *
     * If set, it can be used for enhanced durability requirements, as well as optimized consistency
     * for N1QL queries.
     */
    public $token;
}
