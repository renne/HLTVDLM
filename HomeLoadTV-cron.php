#!/usr/bin/env php5
<?php
/**
 * Example executable for cronjobs.
 *
 * @package     HomeLoadTV
 * @name        Executable
 * @description Executable for class HomeLoadTV.
 * @author      Rene Bartsch <rene@bartschnet.de>
 * @copyright   Rene Bartsch 2013
 * @license     GNU GPL v.3
 * @link        https://github.com/renneb/HLTVDLM
 * @version     $Id$
 */

// Exception handling
try {

    // Include configuration and Homeload class
    require_once('config.php');
    require_once('HomeLoadTV.php');

    // Create new HomeLoadTV object
    $hltv = new HomeLoadTV($email, $password);

    // Run HomeLoadTV request/download
    $result = $hltv->download($directory, $happyhour, $limit, $emailFrom, $thumbnails, $verbose);

    // Destroy HomeLoadTV object
    unset($hltv);

    // Display success message
    if (verbose) {
	echo "\t\t\tOK!\n";
    }

    // Exit with result of download method
    exit($result);

} catch (Exception $e) {

    // Create error message of exception
    $err_msg = date('Y-m-d H:i:s ') . $e->getMessage();

    // Write exception to log-file
    error_log($err_msg . "\n", 3, $directory . '/HomeLoadTV.log');

    // Send email with exception message
    if(!empty($emailFrom)) {
	mail($email, 'HomeLoadTV error message', $err_msg, 'From: ' . $emailFrom);
    }

    // Display exceptions in verbose mode
    if ($verbose) {
	echo "\t\t\t!!! Caught exception: ", $e->getMessage(), "\n";
    }

    // Exit with error return value.
    if(0 === $e->getCode()) {
	exit(99);
    }
}

?>
