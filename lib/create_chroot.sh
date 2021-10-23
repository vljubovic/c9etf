#!/bin/bash


# =========================================
# CREATE_CHROOT.SH
# C9@ETF project (c) 2020
#
# Prepare users home for chroot environment
# =========================================


# Parameter $1 is username, $2 is group, $3 is home (i.e. /home/a/abcdef)

# Create users home inside chroot (/root)
rm -fr $3/root
cp -R /usr/local/webide/defaults/home $3/root
chown -R $1:$2 $3/root

# Make "home" a symlink back to /
mkdir -p $3/$3
rmdir $3/$3
ln -s / $3/$3

# Create c9 home (will be mounted --bind)
mkdir -p $3/home/c9
chmod 755 $3/home

# Create system folders that startup script will mount --bind
mkdir $3/bin
mkdir $3/lib
mkdir $3/lib64
mkdir $3/usr
mkdir $3/var
mkdir $3/proc
mkdir $3/sys
mkdir $3/dev

# Create system folders manually with hardlinks
#cp -alf /bin $3/bin
#cp -alf /lib $3/lib
#cp -alf /lib64 $3/lib64
#cp -alf /usr $3/usr
#cp -alf /var $3/var

mkdir $3/etc
chmod 755 $3/etc
cp /etc/hosts $3/etc
cp /etc/passwd $3/etc
cp /etc/group $3/etc
cp /etc/resolv.conf $3/etc
cp /etc/ld.so.conf $3/etc
cp /etc/ld.so.cache $3/etc
ln -s /proc/mounts $3/etc/mtab
cp -a /etc/alternatives $3/etc

# Needed for theia...
cp /etc/manpath.config $3/etc

# Theia configuration directory will be deployed into $3 by reset-config
ln -s ../.theia $3/root/.theia

# Copy c9 configuration into chroot home

# This version of .c9 is different from the one in $3 as it uses 
# hardlinks instead of symlinks wherever possible

ln -s /usr/local/webide/c9fork $3/root/fork
cp -R /usr/local/webide/defaults/c9 $3/root/.c9
chown -R $1:$2 $3/root/.c9
rm $3/root/.c9/bin
cp -alf /home/c9/.c9/bin $3/root/.c9/
rm $3/root/.c9/bin/sqlite3
ln /home/c9/.c9/lib/sqlite3/sqlite3 $3/root/.c9/bin/sqlite3
rm $3/root/.c9/bin/tmux
ln /home/c9/.c9/local/bin/tmux $3/root/.c9/bin/tmux

rm $3/root/.c9/lib
cp -alf /home/c9/.c9/lib $3/root/.c9/
rm $3/root/.c9/node
cp -alf /home/c9/.c9/node $3/root/.c9/
rm $3/root/.c9/node/bin/node-gyp
ln /home/c9/.c9/node/lib/node_modules/npm/node_modules/node-gyp/bin/node-gyp.js $3/root/.c9/node/bin/node-gyp
rm $3/root/.c9/node_modules
cp -alf /home/c9/.c9/node_modules $3/root/.c9/
rm $3/root/.c9/user.settings
ln -s ../../workspace/.c9/user.settings $3/root/.c9/user.settings

# These files will be deployed into $3/.c9 by reset-config, so we replace them with symlinks
rm $3/root/.c9/project.settings
ln -s ../../.c9/project.settings $3/root/.c9/project.settings
rm $3/root/.c9/state.settings
ln -s ../../.c9/state.settings $3/root/.c9/state.settings

