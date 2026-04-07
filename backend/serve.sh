#!/bin/bash
#
# Start PHP built-in server
# In combination with the Angular proxy configuration (proxy.conf.json),
# this allows API requests to be proxied to the PHP server.
# The Angular app will make requests to /api, which will be forwarded to the PHP server.
#
# Note: File "angular.json" contains the proxy configuration (see proxyConfig). 
# Thus, "ng serve" does automatically consider that configuration.
# Otherwise, it could also be specified manually using the --proxy-config option (as done in package.json along with the "start" script)

# Start PHP built-in server and serve backend folder at localhost:4280
php -S localhost:4280 -t .
