#!/bin/bash

sleep 1
filename=$(basename $1)
dir=$(dirname $1)

extension="${filename##*.}"
fileNoExt="${filename%.*}"

echo     === Running $filename \(`date`\)

ulimit -S -v 1000000 
ulimit -S -t 10

if [ "$extension" = "c" ]; then
	gcc ${dir}/${filename} -o ${dir}/.${fileNoExt} -lm
	${dir}/.${fileNoExt}
elif [ "$extension" = "cpp" ]; then
	g++ ${dir}/${filename} -o ${dir}/.${fileNoExt} -lm
	${dir}/.${fileNoExt}
elif [ "$extension" = "cxx" ]; then
	g++ ${dir}/${filename} -o ${dir}/.${fileNoExt} -lm
	${dir}/.${fileNoExt}
elif [ "$extension" = "c++" ]; then
	g++ ${dir}/${filename} -o ${dir}/.${fileNoExt} -lm
	${dir}/.${fileNoExt}
elif [ "$extension" = "py" ]; then
	python3 ${dir}/${filename}
elif [ "$extension" = "php" ]; then
	php ${dir}/${filename}
elif [ "$extension" = "php3" ]; then
	php ${dir}/${filename}
elif [ "$extension" = "js" ]; then
	nodejs ${dir}/${filename}
elif [ "$extension" = "pl" ]; then
	perl ${dir}/${filename}
elif [ "$extension" = "sh" ]; then
	bash ${dir}/${filename}
elif [ "$extension" = "java" ]; then
	cd ${dir}
	javac ${dir}/${filename}
	java ${fileNoExt}
else
	echo Sorry, don\'t know how to run this file
fi

echo
echo === Program finished with code $?
