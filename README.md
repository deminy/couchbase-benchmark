This repository is to run benchmark with Couchbase servers.

# How to Run a Benchmark

First, please manually create an output file. Here we use a text file _./output.txt_ to store the output.

```bash
touch ./output.txt
```

Secondly, you can run following command to benchmark with a specific Couchbase server:

```bash
docker run --rm \
    -e COUCHBASE_HOST= \
    -e COUCHBASE_USER= \
    -e COUCHBASE_PASS= \
    -e COUCHBASE_BUCKET= \
    -v $(pwd)/output.txt:/var/www/output.txt \
    -t deminy/couchbase-benchmark
```

You need to update Docker enviornment variable _COUCHBASE_HOST_, _COUCHBASE_USER_, _COUCHBASE_PASS_, and
_COUCHBASE_BUCKET_ first before running above command. Once it's done, it will run two benchmarks and save the results
to the output file. In this example, it's the text file _./output.txt_.

# Commands for Local Development

```bash
# Coding style fixes.
docker run --rm -v "$(pwd):/var/www" -w /var/www -i jakzal/phpqa:php7.4 php-cs-fixer fix

# To build the Docker image.
docker build -t deminy/couchbase-benchmark .
# To read the manual of command "ab".
docker run --rm --entrypoint "/bin/sh" -ti deminy/couchbase-benchmark -c "ab -h"
# To install PHP packages.
docker run --rm -v "$(pwd):/var/www" --entrypoint "/bin/sh" -ti deminy/couchbase-benchmark -c "composer install -n"
```

If you want to test the benchmark script locally, you can start a Couchbase container first, then use it to run the
benchmark script, like following:

```bash
touch ./output.txt
docker run --rm -d --name couchbase -e CB_ADMIN=username -e CB_ADMIN_PASSWORD=password -e CB_BUCKET=test -t deminy/couchbase
docker run --rm \
    -e COUCHBASE_HOST=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' couchbase) \
    -v $(pwd)/output.txt:/var/www/output.txt \
    -t deminy/couchbase-benchmark
docker stop couchbase
```
