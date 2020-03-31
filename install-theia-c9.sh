#!/bin/bash


# =========================================
# INSTALL-THEIA-C9.SH
# C9@ETF project (c) 2020
#
# Software installation subcomponent for Theia webide
# that is executed as c9 user
# =========================================

# nvm complains if none of .bashrc .bash_profile .zshrc .profile exists
touch ~/.bashrc

# Steps from https://theia-ide.org/docs/composing_applications

echo
echo -e "\033[31mInstalling nvm & nodejs 10\033[0m"
curl -o- https://raw.githubusercontent.com/creationix/nvm/v0.33.5/install.sh | bash
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm
nvm install 10

echo
echo -e "\033[31mInstalling yarn\033[0m"
npm install -g yarn

echo
echo -e "\033[31mInstalling dependecies\033[0m"
cp ../c9util/theia.package.json ./package.json
yarn

echo
echo -e "\033[31mBuilding theia\033[0m"
export NODE_OPTIONS=--max_old_space_size=8192
yarn theia build

# Start theia once so that some default files will be created
# The process will be killed later today so we don't care about stopping it
yarn theia start &
