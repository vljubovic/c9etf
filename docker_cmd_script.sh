ls &&\
(cp -RPn _webide/c9fork webide/                                   || true) && \
(cp -RPn _webide/data webide/                                     || true) && \
(cp -RPn _webide/htpasswd webide/                                 || true) && \
(cp -RPn _webide/localusers webide/                               || true) && \
(cp -RPn _webide/log webide/                                      || true) && \
(cp -RPn _webide/register webide/                                 || true) && \
(cp -RPn _webide/server_stats.log webide/                         || true) && \
(cp -RPn _webide/users webide/                                   || true) && \
(cp -RPn _webide/watch webide/                                   || true) && \
(ln -s webide/c9fork/node_modules/architect-build/build_support/mini_require.js webide/web/static/ || true) &&\
(ln -s webide/c9fork/plugins/c9.nodeapi/events.js webide/web/static/lib/events.js || true) && \
(ln -s webide/c9fork/node_modules/architect webide/web/static/lib/architect || true) && \
(ln -s webide/c9fork/plugins webide/web/static/plugins || true) && \
# (ln -s webide/c9fork/node_modules/treehugger webide/web/static/plugins/node_modules/treehugger || true) &&\
# (ln -s webide/c9fork/node_modules/tern webide/web/static/plugins/node_modules/tern || true) && \
# (ln -s webide/c9fork/node_modules/c9 webide/web/static/plugins/node_modules/c9 || true) && \
# (ln -s webide/c9fork/node_modules/c9/assert.js webide/web/static/plugins/node_modules/assert.js || true) &&\
(ln -s webide/web/static webide/web/static/static || true) &&\
(cp -RPn _webide/web/buildservice webide/web/ || true) && \
(cp -RPn _webide/web/news.php webide/web/ || true) &&\
(cp -RPn _webide/nginx.skeleton.conf webide/ || true) &&
service php7.4-fpm start && service nginx restart && php /usr/local/webide/lib/ensure_running.php && /bin/bash