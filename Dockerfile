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
RUN php install.php

