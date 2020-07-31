FROM ubuntu

# This ensures the build does not freeze at some point
ENV TZ=Europe/Sarajevo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Update indices
RUN apt-get update -y &&\
apt-get install -y --fix-missing \
                    npm nodejs build-essential nginx locales-all nano vim \
                    php-fpm php-cli php-ldap php-mbstring php-xml \
                    inotify-tools subversion git apache2-utils \
                    gdb gdbserver astyle sqlite3 valgrind zip ntp python2 clangd &&\
    alias python=python2 &&\
    apt-get install -y sudo
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
RUN git clone https://github.com/vljubovic/c9etf /usr/local/webide
# config.php MUST BE PRESENT IN THE REPOSITORY (it is ignored by git, so copy config.php.default to config.php)
WORKDIR "/usr/local/webide"

RUN cp ./lib/config.php.default ./lib/config.php

# Begin installation
RUN service php7.4-fpm start && php install.php

# Add local admin
RUN bin/webidectl add-local-user admin password
COPY ./docker_cmd_script.sh /usr/local/docker.sh
# MAGIC
CMD /usr/local/docker.sh

