ls &&\
service php7.4-fpm start &&\
service nginx restart &&\
php /usr/local/webide/lib/ensure_running.php &&\
/bin/bash