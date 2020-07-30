# c9etf

## Docker

To build a docker image of c9etf and run a container, you have to do the following steps:

1. Clone this repository git clone https://github.com/vljubovic/c9etf webide
1. Copy **`lib/config.php.default`** to **`lib/config.php`** 
1. Edit it to your needs (default is fine)
1. Run: **`cd docker; docker build . -t c9etf`** to build your image
1. Run: **`docker run -it -p HOST_PORT:80 --name container_name c9etf`**
1. This container leaves some trash behind. If you want to clean up the mess, simply run **`./clean_docker_debris.sh`** 
     
Tested on Ubuntu 20.04.