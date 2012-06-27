#!/usr/bin/php5-cli
<?php
/**
 * Example executable for cronjobs.
 *
 * @package     HomeLoadTV
 * @name        Executable
 * @description Executable for class HomeLoadTV.
 * @author      Rene Bartsch <rene@bartschnet.de>
 * @copyright   Rene Bartsch 2012
 * @license     GNU GPL v.3
 * @link        http://gitorious.org/hltvdlm
 * @version     $Id$
 */

// Configuration
$email = 'tic@tac.toe';             // HomeLoadTV username (email-address)
$password = 'your_password';       // HomeLoadTV password
$directory = 'target_directory';    // Local absolute target directory for downloads
$happyhour = true;                  // Download only when happy hour if 'true'
$limit = 10;                        // Maximum number of downloads per call
$emailFrom = 'sender@your.domain';  // Sender email address for error messages

// Includes
require_once('HomeLoadTV.php');

// Code
try {
    $hltv = new HomeLoadTV($email, $password);
    $hltv->download($directory, $happyhour, $limit, $emailFrom);
    unset($hltv);
} catch (Exception $e) {
    echo "\t\t\t!!! Caught exception: ", $e->getMessage(), "\n";
    exit(1);
}
echo "OK\n";
exit(0);
?>
