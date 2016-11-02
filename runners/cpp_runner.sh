#!/bin/bash

path="$1"
srcfile="$2"
exefile="$1/.runme"
debugport="$3"

gccout="$1/.gcc.out"
valgrindout="$1/.valgrind.out"
gdbout="$1/.gdb.out"
corefile="$valgrindout.core."

timesrc=$(stat -c %Y "$srcfile")
timebin=0
if [ -e "$exefile" ]
then
	timebin=$(stat -c %Y "$exefile")
fi

if [ "$timebin" -lt "$timesrc" ]
then
	/usr/bin/g++ -g -std=c++11 -Wall -Wuninitialized -Winit-self -Wno-unused-result -Wfloat-equal -Wno-sign-compare -Werror=implicit-function-declaration -Werror=vla -pedantic -pass-exit-codes "$srcfile" -lm -o "$exefile" 2>&1 | tee "$gccout" 
	if [ ${PIPESTATUS[0]} != 0 ] 
	then 
		exit ${PIPESTATUS[0]}
	fi
fi

chmod 755 "$exefile"
ulimit -S -v 1000000 
ulimit -S -t 10

if [ $debugport ]
then
	me=`whoami`
	oldnode=`ps ax | grep $me | grep node | grep GDB`
	if [[ $oldnode ]]
	then
		echo $oldnode | cut -c 1-5 | xargs kill
	fi
	nice -n 10 /usr/local/bin/gdbserver --once :$debugport "$exefile"
else
	ulimit -c unlimited
	nice -n 10 valgrind --leak-check=full --log-file="$valgrindout" "$exefile"
	#nice -n 10 $exefile
	#corefile="core"
	for file in `ls "$corefile"* 2>/dev/null`
	do
		#echo Debugging $file
		gdb --batch -ex "bt 100" --core=$file "$exefile" 2>&1 >"$gdbout"
		rm "$file"
	done
fi
