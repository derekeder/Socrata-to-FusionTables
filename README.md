## Socrata to Fusion Tables by Derek Eder

**This project is deprecated:** Google made some [recent changes to the Fusion Tables API](https://developers.google.com/fusiontables/docs/articles/oauthfusiontables) that broke this code, and I'm no longer maintaining it.

This script pulls from Chicago's Socrata instance and inserts it in to Google Fusion Tables. This code is far from generic. To use it, you will need to add your connection info for Google and Socrata. 

Also, you will need to do your own custom datamapping from Socrata to your Fusion Table. I've kept mine in here as an example.
  	
Connecting to Socrata with a library by Chris Metcalf
https://github.com/socrata/socrata-php

Connecting to Fusion Tables with a library by kbris...@google.com
http://code.google.com/p/fusion-tables-client-php/

Source: http://data.cityofchicago.org/Service-Requests/311-Service-Requests-Vacant-and-Abandoned-Building/7nii-7srd
Destination: http://www.google.com/fusiontables/DataSource?dsrcid=1614852 

usage
1. copy source/connectioninfo.php.example to source/connectioninfo.php
2. fill in your Socrata and Google account info in source/connectioninfo.php
2. $ php run_import.php
