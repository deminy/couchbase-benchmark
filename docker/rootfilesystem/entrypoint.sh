#!/usr/bin/env sh

set -e

php ./hyperf.php start &

while ! curl -sf --output /dev/null http://127.0.0.1 ; do
  sleep 3
done
sleep 2 # Give enough time for other worker processes to start and warm up.

NUM_OF_PROCESSES=$(curl -s http://127.0.0.1/stats | jq .task_worker_num | xargs)
if [[ -z "${NUM_OF_PROCESSES}" ]] ; then
    echo "ERROR: Unable to get # of task-worker processes."
    exit 1
fi

printf "Benchmarks start at $(php -r 'echo date("Y-m-d H:i:s");').\n\n\n\n"  >  ./output.txt
ab -n 500 -c 100                 http://127.0.0.1/test                       >> ./output.txt 2>&1
printf "\n\n\n\n\n\n\n\n\n\n"                                                >> ./output.txt
ab -n 500 -c ${NUM_OF_PROCESSES} http://127.0.0.1/test                       >> ./output.txt 2>&1
printf "\n\n\n\nBenchmarks done at $(php -r 'echo date("Y-m-d H:i:s");').\n" >> ./output.txt

curl -sf --output /dev/null http://127.0.0.1/shutdown
sleep 5 # Give enough time for the server to shutdown properly.

echo
echo Benchmark is done. Please check the output file for details. Thanks.
echo
