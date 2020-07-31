#!/bin/bash

# Checking if we already created symlinks
if [ ! -d /usr/local/webide/_bin ]; then
  mv /usr/local/webide/bin /usr/local/webide/_bin
  mv /usr/local/webide/lib /usr/local/webide/_lib
  mv /usr/local/webide/web /usr/local/webide/_web
  ln -s "/development/bin/" /usr/local/webide/bin
  ln -s "/development/lib/" /usr/local/webide/lib
  ln -s "/development/web/" /usr/local/webide/web
  ln -s "/usr/local/webide/c9fork/plugins" /usr/local/webide/web/static/plugins
  ln -s "/usr/local/webide/c9fork/node_modules/architect-build/build_support/mini_require.js" /usr/local/webide/web/static/mini_require.js
fi

ls
service php7.4-fpm start
service nginx restart
php /usr/local/webide/lib/ensure_running.php
/bin/bash