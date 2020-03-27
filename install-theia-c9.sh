#!/bin/bash

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
cp ../c9util/theia.package.json ./package.json

echo
echo -e "\033[31mInstalling dependecies\033[0m"
yarn

echo
echo -e "\033[31mBuilding theia\033[0m"
yarn theia build

# nvm bugs later if we don't do this
nvm use --delete-prefix v10.19.0 --silent
