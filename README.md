HLTVDLM
=======

<b>HLTVDLM</b> is a download-manager for <a href="http://www.homeloadtv.com/" alt="HomeloadTV"><i>HomeloadTV</i></a> written in <b>PHP</b> to be used on systems which cannot run the official <i>HomeloadTV</i>-client.
It can be used to e.g. download files into a private area on a shared-hosting webserver.
<b>HLTVDLM</b> is no official <i>HomeloadTV</i>-project but a personal project! It is just for educational purposes without any warranty or liability.

##**Requirements**
* Account at [HomeloadTV](http://www.homeloadtv.com/)
* PHP >= 5.3
* PHP cURL extension
* PHP 64-bit support for files > 2 GByte

##**Discussion**
* [OTR-Forum](http://www.otrforum.com/showthread.php?62869-Api&p=348681#post348681) (German)

##**Classes**
* _HomeLoadTV_

          Download-manager using the HomeloadTV-API to get a list of downloadlinks
* _CURL_

          PHP cURL wrapper for downloading files with HTTP-503-queuing and resume-support

##**Executable**
* _HomeLoadTV-cron.php_

          1. Runs HLTVDLM
          2. Adjust the path of the PHP-CLI executable
          3. Set values for the variables ("$emailFrom" can be an empty string if you don't want error messages via email).


##**Source documentation**
  Included in docs-directory of repository (Doxygen-XHTML)

##**Contribute**
<a href="https://flattr.com/submit/auto?user_id=renne&url=http://renneb.github.io/HLTVDLM&title=HLTVDLM&language=PHP&tags=github&category=software"><img src="http://api.flattr.com/button/flattr-badge-large.png" height="24em" width="16%"/></a>
