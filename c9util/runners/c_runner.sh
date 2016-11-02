#!/bin/bash

exe_file="$1/runme"
gcc_file="$1/.gcc.out"
timebin=0
if [ -e "$exe_file" ]
then
	timebin=$(stat -c %Y "$exe_file")
fi

build=false
for src_file in $(ls "$1"/*.c)
do
	timesrc=$(stat -c %Y "$src_file")
	if [ "$timebin" -lt "$timesrc" ]; then build=true; fi
done

if [ "$build" = true ]
then
	/usr/bin/gcc -O1 -Wall -Wuninitialized -Winit-self -Wno-unused-result -Wfloat-equal -Wno-sign-compare -Werror=implicit-function-declaration -Werror=vla -pedantic -pass-exit-codes "$1"/*.c -lm -o "$exe_file" 2>&1 | tee "$3" 
	if [ ${PIPESTATUS[0]} != 0 ] 
	then 
		exit ${PIPESTATUS[0]}
	fi
fi

chmod 755 "$exe_file"
ulimit -S -v 100000 
ulimit -S -t 10
nice -n 10 "$exe_file"
