#!/bin/sh

# This script creates a distribution tarball of the module.

VERSION=`cat VERSION`
FILE="/tmp/htmltmpl-php-$VERSION.tar.gz"
rm -rf dist
cd ..
DIR=`pwd`
cp -a htmltmpl-php /tmp/htmltmpl-php-$VERSION
cd /tmp
find htmltmpl-php-$VERSION -name CVS -or -name cproc | xargs rm -rf
tar -c htmltmpl-php-$VERSION | gzip -c > $FILE
rm -rf /tmp/htmltmpl-php-$VERSION
cd $DIR
mkdir -p htmltmpl-php/dist
mv $FILE htmltmpl-php/dist
