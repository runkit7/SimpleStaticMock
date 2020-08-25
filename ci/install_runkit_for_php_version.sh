#!/bin/bash -xeu
PHP_MAJOR_VERSION=$(php -r "echo PHP_MAJOR_VERSION;");

pecl install runkit7-3.1.0a1
echo 'extension = runkit7.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
# Configuration settings needed for running tests.
echo 'runkit.internal_override = On' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
# Optional, set error reporting
echo 'error_reporting = E_ALL' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
