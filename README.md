# bfg-stats

Simple stats parsing from bfgminer rpc API. Uses PHP for reading from miner API and AngularJS for front-end.

## Requirements

* Any HTTP server
* PHP 5.2+ with fsockopen function enabled
* Up-to-date browser

## How to install

* Download or clone repository into directory accessible from the web server
* Edit php/config.php and customize your miner address and port
* Point your browser to index.html

## bfgminer config file

Currently bfgminer saves its config file to default path `$HOME/.bfgminer/bfgminer.conf` and not to the one you specified with `-c` command line option - so it is recommended to use config file at default path and not use -c flag if you want to use pool management.

**Warning** some parameters are not saved - namely `scan-serial`. Keep this in mind when using this tool.

## License
&copy; 2013 dzindra. [Licensed under the Apache License, Version 2.0](http://www.apache.org/licenses/LICENSE-2.0)
