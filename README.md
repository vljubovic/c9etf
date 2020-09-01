# c9etf

## Docker

To build a docker image of c9etf and run a container, you have to do the following steps:

1. Clone this repository git clone https://github.com/vljubovic/c9etf webide
1. Copy **`lib/config.php.default`** to **`lib/config.php`** 
1. Edit it to your needs (default is fine)
1. Run: **`cd docker; docker build . -t c9etf`** to build your image
1. Run: **`docker run -it -v /path/to/webide:/development -p HOST_PORT:80 --name container_name c9etfc`**
1. This container leaves some trash behind. If you want to clean up the mess, simply run **`./clean_docker_debris.sh`** 
     
Tested on Ubuntu 20.04.

## Theia

To enable Theia webide, do the following:

1. Run: **`php update-theia-inplace.php`**
1. Edit user so that his default webide is theia: **`bin/webidectl change-user username webide theia`**
1. For security reasons, it is recommended to always use theia in chroot environment. Edit lib/config.php and set `$conf_chroot` to true

## Troubleshooting

If stuff doesn't work from UI, try from command line!

* Login from CLI as user: **`bin/webidectl login username password`**
* Look at the log file: **`tail log/username.log`**
* Try installing Cloud9 from command line (for some reasons, not all error messages will be shown during c9etf installation): **`
```bash
su c9 -s /bin/bash
/usr/local/webide/c9fork/scripts/install-sdk.sh
```
# Web service endpoints

## Assignments endpoint
Designed to work with files and assignments of a specific course.

#### URL: /services/assignments.php
##### global request parameters
 - course_id : ```number``` *required*
 - external : ```boolean``` *optional* | default is false
 - year : ```number``` *optional* | default is current
 
##### getAssignments endpoint
 - action=getAssignments 
##### createAssignment endpoint
 - action=createAssignment
 
 Body
 ```
 {
    "path":         <string>    ex. /T1         - required
    "name":         <string>    ex. Z1          - required
    "displayName":  <string>    ex. Zadatak 1   - required
    "hidden":       <boolean>   ex. false       - required
    "type":         <string>    ex. zadatak     - required
    "homeworkId:    <number>    ex. 123         - optional
 } 
```
##### editAssignment endpoint
 - action=editAssignment
  
 Body
 ```
 {
    "path":         <string>    ex. /T1/Z1      - required
    "displayName":  <string>    ex. Zadatak 1   - optional
    "hidden":       <boolean>   ex. false       - optional
    "type":         <string>    ex. zadatak     - optional
    "homeworkId:    <number>    ex. 123         - optional
 } 
``` 
##### deleteAssignment endpoint
 - action=deleteAssignment
   
 Body
 ```
 {
    "path":         <string>    ex. /T1/Z1         - required
 } 
```  
##### getFileContent endpoint
 - action=getFileContent
    
 Body
 ```
 {
    "path":         <string>    ex. /T1/Z1/main.c   - required
 } 
``` 
##### createFile endpoint
 - action=createFile
   
 Body
 ```
 {
    "folderPath":   <string>    ex. /T1/Z1      - required
    "name":         <string>    ex. main.c      - required
    "binary":       <boolean>   ex. false       - optional
    "show":         <boolean>   ex. true        - optional
    "content:       <string>    ex. 123         - optional
 } 
```  
##### editFile endpoint
 - action=editFile 
   
 Body
 ```
 {
    "path":         <string>    ex. /T1/Z1/main.c      - required
    "binary":       <boolean>   ex. false              - optional
    "show":         <boolean>   ex. true               - optional
    "content:       <string>    ex. 123                - optional
 } 
``` 
##### deleteFile endpoint
 - action=deleteFile
     
 Body
 ```
 {
    "path":         <string>    ex. /T1/Z1/main.c   - required
 } 
``` 