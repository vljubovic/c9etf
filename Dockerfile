FROM ubuntu

# This ensures the build does not freeze at some point
ENV TZ=Europe/Sarajevo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Update indices
RUN apt-get update -y

# Install dependencies
RUN apt-get install -y \
                    npm nodejs build-essential nginx \
                    php-fpm php-cli php-ldap php-mbstring php-xml \
                    inotify-tools subversion git apache2-utils \
                    gdb gdbserver astyle sqlite3 valgrind zip ntp python2 clangd
RUN alias python=python2

# Check if the version is ok
RUN ls /etc/init.d/
# Add php-fpm to startup
RUN update-rc.d php7.4-fpm defaults
# Start it immediately
RUN service php7.4-fpm start

# Add nginx to startup
RUN update-rc.d nginx defaults

# Install php-svn from repository
WORKDIR "/usr/local"
RUN apt install -y libsvn-dev php-dev
RUN svn checkout http://svn.php.net/repository/pecl/svn/trunk php-svn
WORKDIR "/usr/local/php-svn"
RUN phpize
RUN ./configure
RUN make
RUN make install

# Copy this repository to the container
COPY ./ /usr/local/webide
# config.php MUST BE PRESENT IN THE REPOSITORY (it is ignored by git, so copy config.php.default to config.php)

# Begin installation
WORKDIR "/usr/local/webide"
RUN service php7.4-fpm start && php install.php

# Add local admin
RUN bin/webidectl add-local-user admin password

# Now save the state of the ignored files and folders to another folder
WORKDIR "/usr/local"
RUN mkdir _webide && \
    mkdir _webide/static && \
    mkdir _webide/static/lib && \
    mkdir _webide/web && \
    cp -RP webide/c9fork _webide/ && \
    cp -RP webide/data _webide/ && \
    cp -RP webide/htpasswd _webide/ && \
    cp -RP webide/localusers _webide/ && \
    cp -RP webide/log _webide/ && \
    cp -RP webide/register _webide/ && \
    cp -RP webide/server_stats.log _webide/ && \
    cp -RP webide/users _webide/ && \
    cp -RP webide/watch _webide/ && \
    cp -RP webide/web/buildservice _webide/web/ && \
    cp -RP webide/web/news.php _webide/web/news.php && \
    cp -RP webide/nginx.skeleton.conf _webide/nginx.skeleton.conf

# Delete the webide directory
RUN rm -rf webide

# MAGIC
CMD ./webide/docker_cmd_script.sh

