This repository is to run benchmark with Couchbase servers.

# How Is the Benchmark Performed

* Benchmarks are conducted using the Apache _ab_ tool.
* The web server pre-establishes several persistent Couchbase connections. The number of these connections can be adjusted using the _COUCHBASE_CONN_MULTIPLIER_ environment variable.
* By default, the first benchmark makes a total of 500 HTTP requests with 100 concurrent threads.
* The second benchmark, also by default, makes 500 HTTP requests, with the number of concurrent threads equal to the number of persistent Couchbase connections.
* Each HTTP request performs 24 Couchbase operations, including _get()_, _insert()_, _upsert()_, _replace()_, _counter()_, and _remove()_.

The benchmark tool works with Couchbase server 6.5 to 7.2. Other versions of Couchbase server are not tested.

# Running a Benchmark

Run following command to benchmark with a specific Couchbase server:

```bash
docker run --rm --platform=linux/amd64 \
    -e COUCHBASE_HOST=<your_host> \
    -e COUCHBASE_USER=<your_username> \
    -e COUCHBASE_PASS=<your_password> \
    -e COUCHBASE_BUCKET=<your_bucket> \
    -ti deminy/couchbase-benchmark
```

You need to replace `<your_host>`, `<your_username>`, `<your_password>`, and `<your_bucket>` with your specific Couchbase server details first before running above command.

## Increasing Couchbase Connections

To adjust the number of Couchbase connections, use the _COUCHBASE_CONN_MULTIPLIER_ environment variable. This variable multiplies the number of CPU cores to determine the number of Couchbase connections. By default, the multiplier is set to 1, which creates approximately the same number of persistent Couchbase connections as there are CPU cores.

To roughly triple the number of connections, use the following command:

```bash
docker run --rm --platform=linux/amd64 \
    -e COUCHBASE_HOST= \
    -e COUCHBASE_USER= \
    -e COUCHBASE_PASS= \
    -e COUCHBASE_BUCKET= \
    -e COUCHBASE_CONN_MULTIPLIER=3 \
    -ti deminy/couchbase-benchmark
```

## Secure Connections with TLS

To use secure connections with TLS, we need to set environment variable _COUCHBASE_PROTOCOL_ to _couchbases_:

```bash
docker run --rm --platform=linux/amd64 \
    -e COUCHBASE_PROTOCOL=couchbases \
    -e COUCHBASE_HOST= \
    -e COUCHBASE_USER= \
    -e COUCHBASE_PASS= \
    -e COUCHBASE_BUCKET= \
    -e COUCHBASE_OPTIONS="wait_for_config=true" \
    -ti deminy/couchbase-benchmark
```

The PHP SDK bundles Couchbase Capella’s standard root certificate by default. This means we don’t need any additional
configuration to enable TLS - simply set environment variable _COUCHBASE_PROTOCOL_ to _couchbases_ allowing us to use
_couchbases://_ in the connection string.

Note that Couchbase Capella’s root certificate is not signed by a well known CA (Certificate Authority). However, as the
certificate is bundled with the SDK, it is trusted by default. However, if we have a Couchbase certificate file
_./couchbase.pem_ and want to use it to run benchmarks:

```bash
docker run --rm --platform=linux/amd64 \
    -v "$(pwd)/couchbase.pem:/couchbase.pem" \
    -e COUCHBASE_PROTOCOL=couchbases \
    -e COUCHBASE_HOST= \
    -e COUCHBASE_USER= \
    -e COUCHBASE_PASS= \
    -e COUCHBASE_BUCKET= \
    -e COUCHBASE_OPTIONS="truststorepath=/couchbase.pem&wait_for_config=true" \
    -ti deminy/couchbase-benchmark

# or, if we don't want to validate the SSL certificate while benchmarking. This should be used for debugging purposes only.
docker run --rm --platform=linux/amd64 \
    -v "$(pwd)/couchbase.pem:/couchbase.pem" \
    -e COUCHBASE_PROTOCOL=couchbases \
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
docker build --platform linux/amd64 --build-arg PHP_VERSION=8.1 -t deminy/couchbase-benchmark .

# To check runtime environment.
docker run --rm --platform=linux/amd64 --entrypoint "/bin/sh" -ti deminy/couchbase-benchmark -c "php --version"
docker run --rm --platform=linux/amd64 --entrypoint "/bin/sh" -ti deminy/couchbase-benchmark -c "php --ri couchbase"

# To read the manual of command "ab".
docker run --rm --platform=linux/amd64 --entrypoint "/bin/sh" -ti deminy/couchbase-benchmark -c "ab -h"
# To install PHP packages.
docker run --rm --platform=linux/amd64 -v "$(pwd):/var/www" --entrypoint "/bin/sh" -ti deminy/couchbase-benchmark -c "composer install -n"
```

If we want to test the benchmark script locally, we can start a Couchbase container first, then use it to run the
benchmark script, like following:

```bash
# Start a Couchbase container.
docker run --rm -d --name couchbase -e CB_ADMIN=username -e CB_ADMIN_PASSWORD=password -e CB_BUCKET=test -t deminy/couchbase:7.2.2

# Run a benchmark with the Couchbase container.
docker run --rm --platform=linux/amd64 \
    -e COUCHBASE_HOST=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' couchbase) \
    -ti deminy/couchbase-benchmark

# Or, if we want to run a benchmark with the Couchbase container using secure connections with TLS.
docker run --rm --platform=linux/amd64 \
    -e COUCHBASE_PROTOCOL=couchbases \
    -e COUCHBASE_HOST=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' couchbase) \
    -ti deminy/couchbase-benchmark

# Stop the Couchbase container.
docker stop couchbase
```

Environment variable _LOG_LEVEL_ can be set to one of the [PSR-3 log levels]. By default, it is set to `error`. You can
set it to `warning` or `debug` for more detailed logs. For examples,

```bash
# To run a benchmark with the Couchbase container using the debug log level.
docker run --rm --platform=linux/amd64 \
    -e LOG_LEVEL=debug \
    -e COUCHBASE_HOST=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' couchbase) \
    -ti deminy/couchbase-benchmark

# To run a benchmark with the Couchbase container using the warning log level.
docker run --rm --platform=linux/amd64 \
    -e LOG_LEVEL=warning \
    -e COUCHBASE_HOST=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' couchbase) \
    -ti deminy/couchbase-benchmark
```

[PSR-3 log levels]: https://github.com/php-fig/log/blob/3.0.2/src/LogLevel.php
