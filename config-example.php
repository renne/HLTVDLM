<?php
/**
 * Example configuration file.
 *
 * Copy this file to 'config.php' and adjust the parameters to your needs.
 *
 * @package     HomeLoadTV
 * @name        Configuration
 * @description Configuration file for class HomeLoadTV.
 * @author      Rene Bartsch <rene@bartschnet.de>
 * @copyright   Rene Bartsch 2013
 * @license     GNU GPL v.3
 * @link        https://github.com/renneb/HLTVDLM
 * @version     $Id$
 */

// HomeLoadTV username (email-address)
$email = 'tic@tac.toe';

// HomeLoadTV password
$password = 'your_homeloadtv_password';

// Local absolute target directory for downloads and log-files
$directory = 'target_directory';

// Download only when happy hour is 'true'
$happyhour = true;

// Maximum number of downloads per call
$limit = 100;

// Sender email address for error messages
$emailFrom = 'sender@your.domain';

// Download thumbnails
$thumbnails = true;

// Show status messages
$verbose = false;

?>
