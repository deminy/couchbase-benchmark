<?php

declare(strict_types=1);

namespace Couchbase;

/**
 * Class Helper.
 *
 * This class is a self-defined classes. It's not from the Couchbase 2 extension.
 */
class Helper
{
    /**
     * In Couchbase 2, if the expiry field is larger than 30 days (60*60*24*30), it will be interpreted by the server as
     * absolute UNIX time (seconds from epoch 1970-01-01T00:00:00).
     *
     * @see https://github.com/couchbase/php-couchbase/blob/v2.6.2/api/couchbase.php#L697
     */
    public const A_MONTH_IN_SECONDS = 2592000;

    public const TS_20000101 = 946684800; // Some day that is less than 50 years from epoch.

    public const TS_20210822 = 1629590400; // Some day that is greater than 50 years from epoch.

    protected static int $version;

    public static function getCouchbaseMajorVersion(): int
    {
        if (!isset(self::$version)) {
            self::$version = (int) explode('.', phpversion('couchbase'))[0];
            if ((self::$version !== 2) && (self::$version !== 3)) {
                throw new Exception('The Couchbase drivers only work with PHP 7 or 8.');
            }
        }

        return self::$version;
    }

    public static function isCouchbase2(): bool
    {
        return self::getCouchbaseMajorVersion() === 2;
    }

    public static function isCouchbase3(): bool
    {
        return self::getCouchbaseMajorVersion() === 3;
    }

    /**
     * Please note that property "flags" and "token" of Couchbase Documents are not handled in this library.
     *
     * @todo Support property "cas".
     * @param mixed $value
     */
    public static function createDocument($value, ?string $cas = null): Document
    {
        $doc        = new Document();
        $doc->error = null;
        $doc->value = $value;
        $doc->flags = null; // Property "flags" is not handled in this library.
        $doc->cas   = $cas;
        $doc->token = null; // Mutation tokens is always disabled.

        return $doc;
    }

    public static function createDocumentByCas(string $cas): Document
    {
        return self::createDocument(null, $cas);
    }

    /**
     * Please note that property "flags" and "token" of Couchbase Documents are not handled in this library.
     *
     * @todo Error message not yet supported.
     * @todo Support property "cas".
     */
    public static function createErrorDocument(int $errorCode, string $message = ''): Document
    {
        $doc        = new Document();
        $doc->error = self::createException($errorCode, $message);
        $doc->value = null;
        $doc->flags = null; // Property "flags" is not handled in this library.
        $doc->cas   = null;
        $doc->token = null; // Mutation tokens is always disabled.

        return $doc;
    }

    /**
     * @param MutationResult $result A \Couchbase\MutationResultImpl or \Couchbase\StoreResultImpl object.
     *                               1. removeMulti(): \Couchbase\MutationResultImpl.
     *                               2. upsertMulti(): \Couchbase\StoreResultImpl.
     */
    public static function createDocumentFromMutationResult(MutationResult $result): Document
    {
        if ($e = $result->error()) {
            // This happens only during batch operation.
            if ($e instanceof DocumentNotFoundException) {
                return self::createErrorDocument(COUCHBASE_KEY_ENOENT, $e->getMessage());
            }
            if ($e instanceof KeyExistsException) {
                return self::createErrorDocument(COUCHBASE_KEY_EEXISTS, $e->getMessage());
            }

            /* @var \Couchbase\BaseException $e */
            return self::createErrorDocument($e->getCode(), $e->getMessage());
        }

        return self::createDocumentByCas($result->cas());
    }

    public static function createDocumentFromGetResult(GetResult $result): Document
    {
        if ($e = $result->error()) {
            // This happens only during batch operation.
            if ($e instanceof DocumentNotFoundException) {
                return self::createErrorDocument(COUCHBASE_KEY_ENOENT, $e->getMessage());
            }

            /* @var \Couchbase\BaseException $e */
            return self::createErrorDocument($e->getCode(), $e->getMessage());
        }

        return self::createDocument($result->content(), $result->cas());
    }

    public static function getTimestamp(int $expiry): int
    {
        return ($expiry > self::A_MONTH_IN_SECONDS) ? $expiry : (time() + $expiry);
    }

    public static function getDateTime(int $expiry): \DateTime
    {
        return (new \DateTime())->setTimestamp(self::getTimestamp($expiry));
    }

    public static function createException(int $errorCode, string $message = ''): Exception
    {
        return new Exception($message, $errorCode);
    }

    public static function createUnprocessedCouchbase3Exception(BaseException $e): Exception
    {
        return new Exception(
            sprintf('Unprocessed Couchbase 3 Exception (%s): %s', get_class($e), $e->getMessage()),
            $e->getCode()
        );
    }
}
