#!/bin/bash

if [ ! -d "/var/www/$1/" ]; then
cd /var/www/
git clone ${2}
exit
fi
cd /var/www/$1/
git pull
