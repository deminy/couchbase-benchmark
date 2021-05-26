# Commands

## Start the Docker Container

```bash
docker run --rm -v $(pwd):/var/www -ti phpswoole/swoole:4.6-php7.4-dev composer install -n --ignore-platform-reqs

docker run -d --rm --name test-cb \
  -p 8080:80 \
  -v $(pwd)/.env.local:/var/www/.env \
  -t deminy/couchbase-benchmark
```

## The Endpoints

```bash
curl -i http://127.0.0.1:8080/
curl -i http://127.0.0.1:8080/test

docker exec -ti test-cb cat .env   # To check Couchbase connection information.
```
