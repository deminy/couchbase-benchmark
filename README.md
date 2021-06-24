This repository is to run benchmark on Couchbase server.

# How to Run a Benchmark

## Start the Docker Container(s)

**#1** If you only want to test the benchmark script, just run command `docker-compose up -d`. This will starts two Docker containers:

* A web server to run benchmark using [Apache's ab tool](https://en.wikipedia.org/wiki/ApacheBench).
* A Couchbase server.

**#2** If you have your own Couchbase server and want to run the benchmark on it, please have a customized ".env.*" file added
under the root directory of this project, and use it when starting the web-server container.

```bash
cp .env .env.local
# Please modify Couchbase connection information in file ".env.local" before starting the container.
docker run -d --rm --name app -v $(pwd)/.env.local:/var/www/.env -t deminy/couchbase-benchmark
```

## The Endpoints

```bash
docker exec -t $(docker ps -qf "name=app") sh -c "curl -s http://127.0.0.1 | jq ."
docker exec -t $(docker ps -qf "name=app") sh -c "curl -s http://127.0.0.1/test | jq ."

docker exec -t $(docker ps -qf "name=app") cat .env   # To check Couchbase connection information.
```

## Run Benchmark

We will run two benchmarks in this step. Usually, the second one is more accurate since it has less concurrent HTTP calls
made.

```bash
export CONTAINER_ID=$(docker ps -qf "name=app")
export CONTAINER_IP=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' ${CONTAINER_ID})
export NUM_OF_PROCESSES=$(docker exec -t ${CONTAINER_ID} sh -c "curl -s http://127.0.0.1 | jq .server.task_worker_num | xargs")
echo "# of persistent concurrent Couchbase connections: ${NUM_OF_PROCESSES}"

# Now run following two benchmark commands:
docker run --rm --network="container:${CONTAINER_ID}" jordi/ab -n 500 -c 100                 http://${CONTAINER_IP}/test
docker run --rm --network="container:${CONTAINER_ID}" jordi/ab -n 500 -c ${NUM_OF_PROCESSES} http://${CONTAINER_IP}/test
```
