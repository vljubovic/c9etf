#!/bin/bash


# =========================================
# CREATE_CHROOT.SH
# C9@ETF project (c) 2020
#
# Convert users home into chroot
# =========================================


# Parameter $1 is username, $2 is group, $3 is home (i.e. /home/a/abcdef)

# Create chroot home (root)
rm -fr $3/root
cp -R /usr/local/webide/defaults/home $3/root
chown -R $1:$2 $3/root

# Make "home" a symlink back to /
mkdir -p $3/$3
rmdir $3/$3
ln -s / $3/$3

# Create system folders
cp -alf /bin $3/bin
cp -alf /lib $3/lib
cp -alf /lib64 $3/lib64
cp -alf /usr $3/usr
cp -alf /var $3/var

mkdir $3/proc
mount --bind /proc $3/proc
mkdir $3/sys
mount --bind /sys $3/sys
mkdir $3/dev
mount --bind /dev $3/dev
# null random urandom
mount --bind /dev/pts $3/dev/pts

mkdir $3/etc
chmod 755 $3/etc
ln /etc/hosts $3/etc
ln /etc/passwd $3/etc
ln /etc/group $3/etc
ln /etc/resolv.conf $3/etc
ln /etc/ld.so.conf $3/etc
ln /etc/ld.so.cache $3/etc
ln -s /proc/mounts $3/etc/mtab
cp -alf /etc/alternatives $3/etc

# Needed for theia...
ln /etc/manpath.config $3/etc

# Copy (w/hardlinks) Theia configuration files into chroot home, delete symlinks
rm $3/root/.cache
cp -alf /home/c9/.cache $3/root
rm $3/root/.config
cp -alf /home/c9/.config $3/root
rm $3/root/.npm
cp -alf /home/c9/.npm $3/root
rm $3/root/.nvm
cp -alf /home/c9/.nvm $3/root
rm $3/root/.yarn
cp -alf /home/c9/.yarn $3/root

# Theia configuration directory will be deployed into $3 by reset-config
ln -s ../.theia $3/root/.theia


# Copy c9 configuration into chroot home, fix symlinks
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
ln -s /workspace/user.settings $3/root/.c9/user.settings

# These files will be deployed into $3/.c9 by reset-config, so we replace them with symlinks
rm $3/root/.c9/project.settings
ln -s ../.c9/ $3/root/.c9/project.settings
rm $3/root/.c9/state.settings


