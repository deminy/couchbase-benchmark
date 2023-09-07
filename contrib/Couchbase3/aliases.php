<?php

declare(strict_types=1);

// In this section, we are importing Couchbase 2 class aliases to Couchbase 3.
if (!class_exists(CouchbaseCluster::class)) {
    class_alias(Crowdstar\Couchbase3\Cluster::class, CouchbaseCluster::class);
    class_alias(Crowdstar\Couchbase3\Bucket::class, CouchbaseBucket::class);
    class_alias(Couchbase\Document::class, CouchbaseMetaDoc::class);
    class_alias(Couchbase\Exception::class, CouchbaseException::class);
}
