#!/bin/sh

while true
do
        time=$(printf %.0f $(echo `cat /proc/uptime | cut -d " " -f 1`*100 | bc))
        for pid in `grep -h \(\\.main\) /proc/*/stat | cut -d " " -f 1`
        do
                ptime=$(cat /proc/$pid/stat | cut -d " " -f 22)
                dist=$(expr $time - $ptime )
                if [ "$dist" -gt "3600" ]
                then
                        echo Killing $pid
                        kill $pid
                fi
        done
        sleep 300
done
