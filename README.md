This repository is to run benchmark with Couchbase servers.

# How Is the Benchmark Performed

* The benchmarks are done using the Apache ab tool.
* The web server creates a few persistent Couchbase connections in advance. The # of persistent Couchbase connections is the same as the # of CPU core.
* The 1st benchmark makes 500 HTTP requests in total, with 100 concurrent threads.
* The 2nd benchmark makes 500 HTTP requests in total. The # of concurrent threads is the same as the # of persistent Couchbase connections.
* Each HTTP request performs 24 Couchbase operations, including _get()_, _insert()_, _upsert()_, _replace()_, _counter()_, and _remove()_.

The benchmark tool works with Couchbase server 6.5 to 7.2. Other versions of Couchbase server are not tested.

# How to Run a Benchmark

Run following command to benchmark with a specific Couchbase server:

```bash
docker run --rm \
    -e COUCHBASE_HOST= \
    -e COUCHBASE_USER= \
    -e COUCHBASE_PASS= \
    -e COUCHBASE_BUCKET= \
    -ti deminy/couchbase-benchmark
```

You need to update Docker environment variable _COUCHBASE_HOST_, _COUCHBASE_USER_, _COUCHBASE_PASS_, and
_COUCHBASE_BUCKET_ first before running above command.

Assuming that we have a Couchbase certificate file _./couchbase.pem_ and we want to use it to run benchmarks:

```bash
docker run --rm \
    -v "$(pwd)/couchbase.pem:/couchbase.pem" \
    -e COUCHBASE_HOST= \
    -e COUCHBASE_USER= \
    -e COUCHBASE_PASS= \
    -e COUCHBASE_BUCKET= \
    -e COUCHBASE_OPTIONS="truststorepath=/couchbase.pem&wait_for_config=true" \
    -ti deminy/couchbase-benchmark

# or, if we don't want to validate the SSL certificate while benchmarking. This should be used for debugging purposes only.
docker run --rm \
    -v "$(pwd)/couchbase.pem:/couchbase.pem" \
    -e COUCHBASE_HOST= \
    -e COUCHBASE_USER= \
    -e COUCHBASE_PASS= \
    -e COUCHBASE_BUCKET= \
    -e COUCHBASE_OPTIONS="truststorepath=/couchbase.pem&ssl=no_verify&wait_for_config=true" \
    -ti deminy/couchbase-benchmark
```

# Commands for Local Development

```bash
# Coding style fixes.
docker run --rm -v "$(pwd):/var/www" -w /var/www -i jakzal/phpqa:php8.1 php-cs-fixer fix

# To build the Docker image. We build AMR64 images only because there is no download link for ARM64.
docker build --platform linux/amd64 --build-arg PHP_VERSION=8.0 -t deminy/couchbase-benchmark .

# To read the manual of command "ab".
docker run --rm --entrypoint "/bin/sh" -ti deminy/couchbase-benchmark -c "ab -h"
# To install PHP packages.
docker run --rm -v "$(pwd):/var/www" --entrypoint "/bin/sh" -ti deminy/couchbase-benchmark -c "composer install -n"
```

If you want to test the benchmark script locally, you can start a Couchbase container first, then use it to run the
benchmark script, like following:

```bash
# Start a Couchbase container.
docker run --rm -d --name couchbase -e CB_ADMIN=username -e CB_ADMIN_PASSWORD=password -e CB_BUCKET=test -t deminy/couchbase:7.2.2

# Run a benchmark with the Couchbase container.
docker run --rm --platform=linux/amd64 \
    -e COUCHBASE_HOST=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' couchbase) \
    -ti deminy/couchbase-benchmark

# Stop the Couchbase container.
docker stop couchbase
```
