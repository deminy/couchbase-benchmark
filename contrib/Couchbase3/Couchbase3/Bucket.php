<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3;

use Couchbase\AppendOptions;
use Couchbase\BadInputException;
use Couchbase\BaseException;
use Couchbase\Bucket as Couchbase3Bucket;
use Couchbase\CasMismatchException;
use Couchbase\Cluster as Cluster3;
use Couchbase\Collection;
use Couchbase\CounterResult;
use Couchbase\DecrementOptions;
use Couchbase\DocumentNotFoundException;
use Couchbase\GetResultImpl;
use Couchbase\Helper;
use Couchbase\IncrementOptions;
use Couchbase\InsertOptions;
use Couchbase\InvalidRangeException;
use Couchbase\InvalidStateException;
use Couchbase\KeyExistsException;
use Couchbase\MutationResult;
use Couchbase\MutationResultImpl;
use Couchbase\RemoveOptions;
use Couchbase\ReplaceOptions;
use Couchbase\StoreResultImpl;
use Couchbase\TempFailException;
use Couchbase\UpsertOptions;
use Crowdstar\Couchbase3\Exceptions\BucketMethodNotImplementedException;
use Crowdstar\Couchbase3\Exceptions\CustomizedOptionsNotAllowedException;
use Crowdstar\Couchbase3\Exceptions\PartiallyImplementedException;

class Bucket
{
    /**
     * A newly-added property for the Couchbase 2 driver.
     */
    private Cluster $cluster;

    /**
     * A newly-added property for the Couchbase 2 driver.
     */
    private Couchbase3Bucket $bucket;

    /**
     * @Couchbase2 A built-in Couchbase 2 method, but has been overridden with different parameters.
     */
    public function __construct(Cluster $cluster, string $bucketName)
    {
        $this->cluster = $cluster;
        $this->bucket  = $cluster->getCluster3()->bucket($bucketName);
    }

    public function __call(string $name, array $arguments)
    {
        throw new BucketMethodNotImplementedException($name);
    }

    /**
     * @see https://github.com/couchbase/php-couchbase/blob/v3.2.2/src/couchbase/bucket.c#L109
     */
    public function __get(string $name)
    {
        return $this->bucket->{$name};
    }

    /**
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function setTranscoder(callable $encoder, callable $decoder): void
    {
        $this->bucket->setTranscoder($encoder, $decoder);
    }

    /**
     * Retrieves a document
     *
     * @param string|array $ids one or more IDs
     * @return \Couchbase\Document|array document or list of the documents
     *
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function get($ids, ?array $options = [])
    {
        // Class \Couchbase\GetOptions doesn't have an expiry() method for the same purpose in Couchbase 2.
        $this->checkOptions($options);

        if (is_array($ids)) {
            if (empty($ids)) {
                // In Couchbase 2, method call \Couchbase\Bucket::get($ids) returns NULL when an empty array is passed in.
                return null;
            }

            $ids = array_values($ids);
            /** @var GetResultImpl[] $rows */
            $rows   = $this->getCollection()->getMulti($ids);
            $return = [];
            foreach ($ids as $key => $id) {
                $return[$id] = Helper::createDocumentFromGetResult($rows[$key]);
                unset($rows[$key]); // To save memory when processing large array.
            }
            return $return;
        }

        try {
            return Helper::createDocumentFromGetResult($this->getCollection()->get($ids));
        } catch (DocumentNotFoundException $e) {
            throw Helper::createException(COUCHBASE_KEY_ENOENT);
        } catch (BaseException $e) {
            throw Helper::createUnprocessedCouchbase3Exception($e);
        }
    }

    /**
     * Retrieves a document and locks it.
     *
     * After the document has been locked on the server, its CAS would be masked,
     * and all mutations of it will be rejected until the server unlocks the document
     * automatically or it will be done manually with \Couchbase\Bucket::unlock() operation.
     *
     * NOTE: THIS METHOD SHOULD NOT BE USED WHEN MIGRATING COUCHBASE 2 PROJECTS TO COUCHBASE 3.
     *       Some unit tests are inconsistent when running under Couchbase 2 and Couchbase 3. For details, please check
     *       test class \Crowdstar\Tests\Couchbase3\Bucket\CouchbaseGetAndLockAndUnlockSingleItemByIdTest.
     *
     * @param string|array $ids one or more IDs
     * @param int $lockTime time to lock the documents
     * @return \Couchbase\Document|array document or list of the documents
     *
     * @see \Couchbase\Bucket::unlock()
     * @see \Crowdstar\Tests\Couchbase3\Bucket\CouchbaseGetAndLockAndUnlockSingleItemByIdTest
     * @see https://support.couchbase.com/hc/en-us/requests/44799 Method Getandlock() Doesn't Work As Expected in v3.2.2
     * @see https://issues.couchbase.com/browse/PCBC-840 API docs incorrectly specify getAndLock() $lockTime as ms not s
     *
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function getAndLock($ids, int $lockTime, ?array $options = [])
    {
        if (is_array($ids)) {
            if (empty($ids)) {
                // In Couchbase 2, method call \Couchbase\Bucket::get($ids) returns NULL when an empty array is passed in.
                return null;
            }

            $return = [];
            foreach ($ids as $id) {
                try {
                    $return[$id] = Helper::createDocumentFromGetResult($this->getCollection()->getAndLock($id, $lockTime));
                } catch (DocumentNotFoundException $e) {
                    $return[$id] = Helper::createErrorDocument(COUCHBASE_KEY_ENOENT);
                } catch (BaseException $e) {
                    if (($e instanceof KeyExistsException) && ($e->getCode() === COUCHBASE_ERR_DOCUMENT_LOCKED)) {
                        $return[$id] = Helper::createErrorDocument(COUCHBASE_ETMPFAIL);
                    } else {
                        $return[$id] = Helper::createUnprocessedCouchbase3Exception($e);
                    }
                }
            }
            return $return;
        }

        try {
            return Helper::createDocumentFromGetResult($this->getCollection()->getAndLock($ids, $lockTime));
        } catch (DocumentNotFoundException $e) {
            throw Helper::createException(COUCHBASE_KEY_ENOENT);
        } catch (BaseException $e) {
            if (($e instanceof KeyExistsException) && ($e->getCode() === COUCHBASE_ERR_DOCUMENT_LOCKED)) {
                throw Helper::createException(COUCHBASE_ETMPFAIL);
            }
            throw Helper::createUnprocessedCouchbase3Exception($e);
        }
    }

    /**
     * Inserts or updates a document, depending on whether the document already exists on the cluster.
     *
     * @param string|array $ids one or more IDs
     * @param mixed $value value of the document
     * @return \Couchbase\Document|array document or list of the documents
     *
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function upsert($ids, $value, ?array $options = [])
    {
        $this->checkOptions($options, Options::EXPIRY);

        if (isset($options) && !empty($options[Options::EXPIRY])) {
            $upsertOptions = new UpsertOptions();
            $upsertOptions->expiry(Helper::getDateTime($options[Options::EXPIRY]));
        }

        if (is_array($ids)) {
            if (empty($ids)) {
                // In Couchbase 2, method call \Couchbase\Bucket::upsert($ids) returns NULL when an empty array is passed in.
                return null;
            }

            $data = $keys = [];
            foreach ($ids as $key => $rowValue) {
                if (is_numeric($key)) {
                    $keys[] = $rowValue;
                    $data[] = [$rowValue, $value];
                } else {
                    $keys[] = $key;
                    if (is_array($rowValue) && array_key_exists('value', $rowValue)) {
                        $data[] = [$key, $rowValue['value']];
                    } else {
                        $data[] = [$key, $value];
                    }
                }
            }

            /* @var StoreResultImpl[] $rows */
            if (!empty($upsertOptions)) {
                $rows = $this->getCollection()->upsertMulti($data, $upsertOptions);
            } else {
                $rows = $this->getCollection()->upsertMulti($data);
            }

            $return = [];
            foreach ($keys as $index => $key) {
                $return[$key] = Helper::createDocumentFromMutationResult($rows[$index]);
                unset($rows[$key]); // To save memory when processing large array.
            }
            return $return;
        }

        if (!empty($upsertOptions)) {
            $result = $this->getCollection()->upsert($ids, $value, $upsertOptions);
        } else {
            $result = $this->getCollection()->upsert($ids, $value);
        }

        /* @var MutationResult $result */
        return Helper::createDocumentByCas($result->cas());
    }

    /**
     * Inserts a document. This operation will fail if the document already exists on the cluster.
     *
     * @param string|array $ids one or more IDs
     * @param mixed $value value of the document
     * @param array $options options
     *                       * "expiry" document expiration time in seconds. If larger than 30 days (60*60*24*30),
     *                       it will be interpreted by the server as absolute UNIX time (seconds from epoch
     *                       1970-01-01T00:00:00).
     * @return \Couchbase\Document|array document or list of the documents
     *
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function insert($ids, $value, ?array $options = [])
    {
        $this->checkOptions($options, Options::EXPIRY);

        if (isset($options) && !empty($options[Options::EXPIRY])) {
            $insertOptions = new InsertOptions();
            $insertOptions->expiry(Helper::getDateTime($options[Options::EXPIRY]));
        }

        if (is_array($ids)) {
            if (empty($ids)) {
                // In Couchbase 2, method call \Couchbase\Bucket::insert($ids) returns NULL when an empty array is passed in.
                return null;
            }

            $return = [];
            foreach ($ids as $id) {
                try {
                    if (!empty($insertOptions)) {
                        $result = $this->getCollection()->insert($id, $value, $insertOptions);
                    } else {
                        $result = $this->getCollection()->insert($id, $value);
                    }

                    /* @var MutationResult $result */
                    $return[$id] = Helper::createDocumentByCas($result->cas());
                } catch (KeyExistsException $e) {
                    $return[$id] = Helper::createErrorDocument(COUCHBASE_KEY_EEXISTS);
                } catch (BaseException $e) {
                    $return[$id] = Helper::createUnprocessedCouchbase3Exception($e);
                }
            }
            return $return;
        }

        try {
            if (!empty($insertOptions)) {
                $result = $this->getCollection()->insert($ids, $value, $insertOptions);
            } else {
                $result = $this->getCollection()->insert($ids, $value);
            }
        } catch (KeyExistsException $e) {
            throw Helper::createException(COUCHBASE_KEY_EEXISTS);
        } catch (BaseException $e) {
            throw Helper::createUnprocessedCouchbase3Exception($e);
        }

        /* @var MutationResult $result */
        return Helper::createDocumentByCas($result->cas());
    }

    /**
     * Replaces a document. This operation will fail if the document does not exists on the cluster.
     *
     * @param string|array $ids one or more IDs
     * @param mixed $value value of the document
     * @return \Couchbase\Document|array document or list of the documents
     *
     * @see \Crowdstar\Tests\Couchbase3\Bucket\CouchbaseReplaceCasTest
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function replace($ids, $value, ?array $options = [])
    {
        $this->checkOptions($options, Options::CAS, Options::EXPIRY);

        if (!empty($options)) {
            $replaceOptions = new ReplaceOptions();
            if (!empty($options[Options::CAS])) {
                try {
                    $replaceOptions->cas($options[Options::CAS]);
                } catch (BadInputException $e) {
                    // Ideally, this section is never reachable in a production environment.
                    //
                    // When a bad cas value like "${cas}-invalid" (not "${cas}", which is a valid cas value) is used, an
                    // exception is thrown out when this method is called; however, in Couchbase 3, a BadInputException
                    // exception is thrown out right here.
                    //
                    // We use method call Bucket::replace() on single item only. Thus, here we throw out
                    // a COUCHBASE_KEY_EEXISTS exception directly. This helps to have tests cases in class
                    // \Crowdstar\Tests\Couchbase3\Bucket\CouchbaseReplaceCasTest to pass.
                    throw Helper::createException(COUCHBASE_KEY_EEXISTS);
                }
            }
            if (!empty($options[Options::EXPIRY])) {
                $replaceOptions->expiry(Helper::getDateTime($options[Options::EXPIRY]));
            }
        }

        if (is_array($ids)) {
            if (empty($ids)) {
                // In Couchbase 2, method call \Couchbase\Bucket::replace($ids) returns NULL when an empty array is passed in.
                return null;
            }

            $return = [];
            foreach ($ids as $id) {
                try {
                    if (!empty($replaceOptions)) {
                        $result = $this->getCollection()->replace($id, $value, $replaceOptions);
                    } else {
                        $result = $this->getCollection()->replace($id, $value);
                    }

                    /* @var MutationResult $result */
                    $return[$id] = Helper::createDocumentByCas($result->cas());
                } catch (CasMismatchException|KeyExistsException $e) {
                    $return[$id] = Helper::createErrorDocument(COUCHBASE_KEY_EEXISTS);
                } catch (DocumentNotFoundException $e) {
                    $return[$id] = Helper::createErrorDocument(COUCHBASE_KEY_ENOENT);
                } catch (BaseException $e) {
                    $return[$id] = Helper::createUnprocessedCouchbase3Exception($e);
                }
            }
            return $return;
        }

        try {
            if (!empty($replaceOptions)) {
                $result = $this->getCollection()->replace($ids, $value, $replaceOptions);
            } else {
                $result = $this->getCollection()->replace($ids, $value);
            }
        } catch (CasMismatchException|KeyExistsException $e) {
            throw Helper::createException(COUCHBASE_KEY_EEXISTS);
        } catch (DocumentNotFoundException $e) {
            throw Helper::createException(COUCHBASE_KEY_ENOENT);
        } catch (BaseException $e) {
            throw Helper::createUnprocessedCouchbase3Exception($e);
        }

        /* @var MutationResult $result */
        return Helper::createDocumentByCas($result->cas());
    }

    /**
     * We use this method with following limit applies:
     *     1. It doesn't support batch operations.
     *     2. The 2nd parameter "$value" must be a string.
     *     3. It doesn't have any options specified.
     *
     * @return \Couchbase\Document|array document or list of the documents
     * @see https://docs.couchbase.com/php-sdk/2.6/core-operations.html#devguide_kvcore_append_prepend_generic
     *
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function append(string $ids, string $value, ?array $options = [])
    {
        $this->checkOptions($options);

        // When trying to append a locked item with parameter $options not passed in (or empty),
        //   1. In Couchbase 2, the method call fails immediately.
        //   2. In Couchbase 3, the method call waits for 2 to 3 seconds to fail.
        // Thus, here we manually add a timeout option, to make method Bucket::append() work consistently between
        // Couchbase 2 and Couchbase 3.
        $options = new AppendOptions();
        $options->timeout(800); // 0.8 second.

        try {
            $result = $this->getCollection()->binary()->append($ids, $value, $options);
        } catch (InvalidStateException $e) {
            throw Helper::createException(COUCHBASE_NOT_STORED);
        } catch (KeyExistsException $e) {
            throw Helper::createException(COUCHBASE_ETMPFAIL);
        } catch (BaseException $e) {
            throw Helper::createUnprocessedCouchbase3Exception($e);
        }

        /* @var MutationResult $result */
        return Helper::createDocumentByCas($result->cas());
    }

    /**
     * Removes the document.
     *
     * @param string|array $ids one or more IDs
     * @return \Couchbase\Document|array document or list of the documents
     *
     * @see \Crowdstar\Tests\Couchbase3\Bucket\CouchbaseRemoveCasTest
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function remove($ids, ?array $options = [])
    {
        $this->checkOptions($options, Options::CAS);

        if (isset($options)) {
            $removeOptions = new RemoveOptions();
            if (!empty($options[Options::CAS])) {
                try {
                    $removeOptions->cas($options[Options::CAS]);
                } catch (BadInputException $e) {
                    // Ideally, this section is never reachable in a production environment.
                    //
                    // When a bad cas value like "${cas}-invalid" (not "${cas}", which is a valid cas value) is used, an
                    // exception is thrown out when this method is called; however, in Couchbase 3, a BadInputException
                    // exception is thrown out right here.
                    //
                    // We use method call Bucket::remove() on single item only when option "cas" in use.
                    // Thus, here we throw out a COUCHBASE_KEY_EEXISTS exception directly. This helps to have tests
                    // cases in class \Crowdstar\Tests\Couchbase3\Bucket\CouchbaseRemoveCasTest to pass.
                    throw Helper::createException(COUCHBASE_KEY_EEXISTS);
                }
            }
        }

        if (is_array($ids)) {
            if (empty($ids)) {
                // In Couchbase 2, method call \Couchbase\Bucket::remove($ids) returns NULL when an empty array is passed in.
                return null;
            }

            $ids = array_values($ids);
            // Just to note it again here: this implementation doesn't support option "cas" when removing multiple items.
            /** @var MutationResultImpl[] $rows */
            $rows   = $this->getCollection()->removeMulti($ids);
            $return = [];
            foreach ($ids as $key => $id) {
                $return[$id] = Helper::createDocumentFromMutationResult($rows[$key]);
                unset($rows[$key]); // To save memory when processing large array.
            }
            return $return;
        }

        try {
            if (!empty($removeOptions)) {
                $result = $this->getCollection()->remove($ids, $removeOptions);
            } else {
                $result = $this->getCollection()->remove($ids);
            }
        } catch (CasMismatchException $e) {
            throw Helper::createException(COUCHBASE_KEY_EEXISTS);
        } catch (DocumentNotFoundException $e) {
            throw Helper::createException(COUCHBASE_KEY_ENOENT);
        } catch (BaseException $e) {
            throw Helper::createUnprocessedCouchbase3Exception($e);
        }

        /* @var MutationResult $result */
        return Helper::createDocumentByCas($result->cas());
    }

    /**
     * Unlocks previously locked document
     *
     * @param string|array $ids one or more IDs
     * @param array $options options
     *                       * "cas" last known document CAS, which has been returned by locking command
     * @return \Couchbase\Document|array document or list of the documents
     *
     * @see \Couchbase\Bucket::get()
     * @see \Couchbase\Bucket::getAndLock()
     *
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function unlock($ids, $options = [])
    {
        if (empty($options[Options::CAS])) {
            return null;
        }

        if (is_array($ids)) {
            if (empty($ids)) {
                // In Couchbase 2, method call \Couchbase\Bucket::unlock($ids) returns NULL when an empty array is passed in.
                return null;
            }

            $return = [];
            foreach ($ids as $id) {
                try {
                    // The return value of Collection::unlock() is a \Couchbase\ResultImpl object, which inherits from
                    // class \Couchbase\Result.
                    $result      = $this->getCollection()->unlock($id, $options[Options::CAS]);
                    $return[$id] = Helper::createDocumentByCas($result->cas());
                } catch (DocumentNotFoundException $e) {
                    $return[$id] = Helper::createErrorDocument(COUCHBASE_KEY_ENOENT);
                } catch (BadInputException|CasMismatchException|TempFailException $e) {
                    // BadInputException: This happens when passing in an invalid CAS value like "an-invalid-cas-value".
                    // TempFailException: This happens when trying to unlock an unlocked item.
                    $return[$id] = Helper::createErrorDocument(COUCHBASE_ETMPFAIL);
                } catch (BaseException $e) {
                    $return[$id] = Helper::createUnprocessedCouchbase3Exception($e);
                }
            }
            return $return;
        }

        try {
            // The return value of Collection::unlock() is a \Couchbase\ResultImpl object, which inherits from class
            // \Couchbase\Result.
            $result = $this->getCollection()->unlock($ids, $options[Options::CAS]);
        } catch (DocumentNotFoundException $e) {
            throw Helper::createException(COUCHBASE_KEY_ENOENT);
        } catch (BadInputException|TempFailException $e) {
            // BadInputException: This happens when passing in an invalid CAS value like "an-invalid-cas-value".
            // TempFailException: This happens when trying to unlock an unlocked item.
            throw Helper::createException(COUCHBASE_ETMPFAIL);
        } catch (BaseException $e) {
            throw Helper::createUnprocessedCouchbase3Exception($e);
        }

        return Helper::createDocumentByCas($result->cas());
    }

    /**
     * Increments or decrements a key (based on $delta)
     *
     * @param string|array $ids one or more IDs
     * @param int $delta the number whih determines the sign (positive/negative) and the value of the increment
     * @return \Couchbase\Document|array document or list of the documents
     *
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function counter($ids, int $delta = 1, array $options = [])
    {
        // Option "expiry" doesn't work as expected for method counter() in Couchbase 2; it doesn't handle Unix timestamp
        // (including expired timestamps) correctly. Thus, we have it disallowed here.
        $this->checkOptions($options, Options::INITIAL, Options::EXPIRY);

        if ($delta >= 0) {
            $counterOptions = new IncrementOptions();
            $operation      = 'increment';
        } else {
            $counterOptions = new DecrementOptions();
            $operation      = 'decrement';
        }
        $counterOptions->delta(abs($delta));
        if (isset($options[Options::INITIAL])) {
            $counterOptions->initial($options[Options::INITIAL]);
        }
        if (!empty($options[Options::EXPIRY])) {
            // Different from other Options object, here it accepts integer values only in PHP 7.4 (as of Couchbase v3.2.2).
            $counterOptions->expiry(Helper::getTimestamp($options[Options::EXPIRY]));
        }

        if (is_array($ids)) {
            if (empty($ids)) {
                // In Couchbase 2, method call \Couchbase\Bucket::counter($ids) returns NULL when an empty array is passed in.
                return null;
            }

            $return = [];
            foreach ($ids as $id) {
                try {
                    $result = $this->getCollection()->binary()->{$operation}($id, $counterOptions);

                    /* @var CounterResult $result */
                    $return[$id] = Helper::createDocument($result->content(), $result->cas());
                } catch (DocumentNotFoundException $e) {
                    $return[$id] = Helper::createErrorDocument(COUCHBASE_KEY_ENOENT);
                } catch (InvalidRangeException $e) {
                    $return[$id] = Helper::createErrorDocument(COUCHBASE_DELTA_BADVAL);
                } catch (BaseException $e) {
                    $return[$id] = Helper::createUnprocessedCouchbase3Exception($e);
                }
            }

            return $return;
        }

        try {
            $result = $this->getCollection()->binary()->{$operation}($ids, $counterOptions);
        } catch (DocumentNotFoundException $e) {
            throw Helper::createException(COUCHBASE_KEY_ENOENT);
        } catch (InvalidRangeException $e) {
            throw Helper::createException(COUCHBASE_DELTA_BADVAL);
        } catch (BaseException $e) {
            throw Helper::createUnprocessedCouchbase3Exception($e);
        }

        return Helper::createDocument($result->content(), $result->cas());
    }

    /**
     * Returns a builder for reading subdocument API.
     *
     * A dedicated helper method `\Crowdstar\Couchbase3\CustomizedHandler::getExpiryTime()` is added for the upgrade.
     * Manual update is needed during the migration.
     *
     * @param string $id the ID of the JSON document
     * @return LookupInBuilder
     * @see CustomizedHandler::getExpiryTime()
     * @see https://docs.couchbase.com/php-sdk/2.6/subdocument-operations.html Sub-Document Operations
     * @see https://docs.couchbase.com/php-sdk/current/howtos/subdocument-operations.html Sub-Document Operations with the PHP SDK
     *
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function lookupIn(string $id)
    {
        throw new PartiallyImplementedException();
    }

    /**
     * A newly-added method to migrate from Couchbase 2 to Couchbase 3.
     */
    public function getCollection(): Collection
    {
        return $this->bucket->defaultCollection();
    }

    /**
     * A newly-added method for the Couchbase 2 driver.
     */
    public function getCluster3(): Cluster3
    {
        return $this->cluster->getCluster3();
    }

    /**
     * To check if any options are not allowed for given K/V operation.
     */
    private function checkOptions(?array $options, string ...$allowedOptions): void
    {
        if (!isset($options)) {
            return;
        }

        foreach ($allowedOptions as $option) {
            unset($options[$option]);
        }
        if (!empty($options)) {
            throw new CustomizedOptionsNotAllowedException($options);
        }
    }
}
