<?php

// Include CURL downloader class
require_once('CURL.php');

/**
 * Download-manager-class for HomeloadTV.
 *
 * The class queries the HomeloadTV-API for files to download,
 * uses the class "CURL" to download the file and marks the file
 * as finished via the HomeloadTV-API.
 * In case of OnlineTVRecorder-recordings a thumbnail of the recording is downloaded, too.
 *
 * For use with cronjobs a locking mechanism is implemented.
 *
 * !!! Important: Objects MUST be destroyed on exceptions or you can loose data !!!
 *
 * @package     HomeLoadTV
 * @subpackage  HomeLoadTV
 * @name        HomeLoadTV
 * @description Download-manager-class for HomeloadTV.
 * @author      Rene Bartsch <rene@bartschnet.de>
 * @copyright   Rene Bartsch 2013
 * @license     GNU GPL v.3
 * @link        https://github.com/renneb/HLTVDLM
 * @link        http://www.otrforum.com/showthread.php?62869-Api
 * @version     $Id$
 */
class HomeLoadTV {

    /**
     * Name of locking-file.
     * @var string  $lockfile
     */
    private static $lockfile = 'HomeLoadTV.lock';

    /**
     * File pointer resource of lockfile
     * @var ressource $fd
     */
    private $fd = null;

    /**
     * HomeLoadTV user id (email address).
     * @var  string  $email
     */
    private $email = null;

    /**
     * HomeLoadTV user password.
     * @var  string  $password
     */
    private $password = null;

    /**
     * Prefix of HomeLoadTV API-URL.
     * @var string  $APIuriPrefix
     */
    private static $APIuriPrefix = 'http://www.homeloadtv.com/api/?';

    /**
     * Prefix of thumbnail-URLs.
     * @var string  $ThumbUriPrefix
     */
    private static $ThumbUriPrefix = 'http://thumbs.onlinetvrecorder.com/';

    /**
     * Associative array with suffixes of thumbnail-URLs.
     * @var array   $ThumbUriSuffix
     */
    private static $ThumbUriSuffix = array(
        'A' => '____A.jpg',
        'B' => '____B.jpg',
        '0' => '____0.jpg',
        '1' => '____1.jpg',
        '2' => '____2.jpg',
        '3' => '____3.jpg',
        '4' => '____4.jpg',
        '5' => '____5.jpg',
        '6' => '____6.jpg',
        '7' => '____7.jpg',
        '8' => '____8.jpg',
        '9' => '____9.jpg',
    );

    /**
     * PHP-cURL-object.
     * @var object $curl
     */
    private $curl = null;

    /**
     * Constructor.
     *
     * The constructor creates a lockfile and assigns the object variables.
     *
     * @param   string      $email      User email address.
     * @param   string      $password   User password.
     * @pre     None.
     * @post    None.
     * @throws  Exception               Wrong parameter type or value.
     * @todo    Check email string with regular expression.
     */
    public function __construct($email, $password) {

        // Open lockfile
        $this->fd = fopen(sys_get_temp_dir() . '/' . self::$lockfile, 'w');
        if (FALSE === $this->fd) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Cannot open lockfile ' . sys_get_temp_dir() . '/' . self::$lockfile . '!');
        }

        // Set lock on lockfile and Check if program is running
        if (!flock($this->fd, LOCK_EX | LOCK_NB)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': !!! Cannot set lock on lockfile ' . sys_get_temp_dir() . '/' . self::$lockfile . '!!!');
        }

        // Check parameters
        if (!is_string($email))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $email must be string type!');
        if (!is_string($password))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $password must be string type!');
        if (empty($email))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $email must not be empty!');
        if (empty($password))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $password must not be empty!');

        // Assign object variables
        $this->email = $email;
        $this->password = $password;
    }

    /**
     * Destructor deletes cURL object and closes file-descriptor.
     *
     * The destructor deletes the cURL-object
     * and closes the file-descriptor of the lockfile.
     * The operating system unsets the lock on the file when the file-descriptor is closed.
     * @pre     None.
     * @post    None.
     */
    public function __destruct() {

        // Delete curl object
        unset($this->curl);

        // Close file descriptor of lock file
        @fclose($this->fd);
    }

    /**
     * Gets an array with download links of HomeLoadTV.
     *
     * @param   string  $limit      Maximum number download links to get.
     * @param   string  $active2new Set state of all donwload links from "active" to "new".
     * @param   string  $happyHour  Get download links only if it is Happy Hour.
     * @return  mixed               "false" if no links or
     *                              Array(
     *                                  [INTERVAL]  => value    Integer, timeout until next API-request.
     *                                  [LINKS]     => value    Integer, number of links in this result.
     *                                  [LIST]      => value    Integer, list ID.
     *                                  [LINKCOUNT] => value    Integer, links waiting for download.
     *                                  [HHSTART]   => value    Integer, begin of Happy Hour CET/CEST.
     *                                  [HHEND]     => value    Integer, end   of Happy Hour CET/CEST.
     *                                  [links]     => [n] => Array(
     *                                                              [baseurl]  => Value String, URL without filename.
     *                                                              [filename] => Value String, filename.
     *                                                              [id]       => Value Integer, link ID.
     *                                                      )
     *                              )
     * @pre     None.
     * @post    None.
     * @throws  Exception           Wrong parameter type or value or error message from HomeloadTV.
     */
    public function getLinks($limit = 100, $active2new = true, $happyHour = true) {

        // Check parameters
        if (!is_int($limit))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $limit must be integer type!');
        if (($limit < 1))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $limit must be greater than zero!');
        if (!is_bool($active2new))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $active2new must be boolean type!');
        if (!is_bool($happyHour))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $happyHour must be boolean type!');

        // Create Request-URI
        $uri = array(
            'do=getlinks',
            'uid=' . $this->email,
            'password=' . $this->password,
            'limit=' . $limit
        );
        $uri[] = ($active2new === true) ? 'protocnew=true' : 'protocnew=false';
        $uri[] = ($happyHour === true) ? 'onlyhh=true' : 'onlyhh=false';

        // Concatenate URI array, add URI-prefix and do GET-Request
        $result = file_get_contents(self::$APIuriPrefix . implode('&', $uri));
        unset($uri);

        // Error handling
        if (!is_string($result) || empty($result)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': No result from GET request!');
        }
        if (!strpos($result, ';')) {
            if (0 === strcmp("NO_NEW_LINKS", $result)) {
                return false;
            } else {
                throw new Exception(get_class() . '::' . __FUNCTION__ . ': ' . $result);
            }
        }

        // Get links, remove numeric keys and convert link IDs from string to integer
        $links = array();
        preg_match_all('#.*(?P<url>http://.+);(?P<id>\d+);#m', $result, $links, PREG_SET_ORDER);
        foreach ($links as &$link) {
            unset($link[0], $link[1], $link[2], $link[3]);
            $link['id'] = intval($link['id']);
        }

        // Get parameters, convert values from string to integer
        // and combine keys and values
        $params = array();
        preg_match_all('#([A-Z]+)=(\d+);#m', $result, $params);
        $params[2] = array_map('intval', $params[2]);
        $result = array_combine($params[1], $params[2]);
        unset($params);

        // Add links to result array
        $result['links'] = &$links;

        // Return results
        return($result);
    }

    /**
     * Sets the state of a list to "processing".
     *
     * @param   integer     $list   ID of list.
     * @return  boolean             "true" on success or "false" on error.
     * @pre     None.
     * @post    None.
     */
    public function setProcessing($list) {

        // Check parameters
        if (!is_integer($list))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $limit must be integer type!');
        if ($list < 0)
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $limit must be greater than zero!');

        // Create Request-URI
        $uri = array(
            'do=setstate',
            'state=processing',
            'uid=' . $this->email,
            'list=' . $list
        );

        // Concatenate URI array, add URI-prefix, do GET-Request and check if result is OK
        return(0 === strcmp("OK", file_get_contents(self::$APIuriPrefix . implode('&', $uri))));
    }

    /**
     * Sets the state of a download link.
     *
     * @param   integer $id         ID of download link.
     * @param   string  $state      Either "new", "finished" or "damaged".
     * @param   integer $filesize   Filesize in KiloByte
     * @param   integer $speed      Download speed in KiloBit/s.
     * @param   string  $error      Empty string for success, "endHH" for end of Happy Hour or any other error message. Not used with state "new"
     * @param   string  $filename   Name of file.
     * @return  boolean             "true" on success or "false" on error.
     * @pre     None.
     * @post    None.
     */
    public function setState($id, $state, $filesize = 0, $speed = 0, $error = '', $filename = '') {

        // Check parameters
        if (!is_integer($id))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $id must be integer type!');
        if ($id < 0)
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $id must be greater than zero!');
        if (!is_string($state))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $state must be string type!');
        if (!((0 === strcmp($state, "new")) || (0 === strcmp($state, "finished")) || (0 === strcmp($state, "damaged"))))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $state must not be string type!');
        if (!is_integer($filesize))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $filesize must be integer type!');
        if ($filesize < 0)
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $filesize must be greater than zero!');
        if (!is_integer($speed))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $speed must be integer type!');
        if ($speed < 0)
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $speed must be greater than zero!');
        if (!is_string($error))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $error must be string type!');
        if (!is_string($filename))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $filename must be string type!');

        // Create Request-URI
        $uri = array(
            'do=setstate',
            'uid=' . $this->email,
            'id=' . $id,
            'state=' . $state,
            'error=' . urlencode($error),
            'filesize=' . $filesize,
            'speed=' . $speed,
            'file=' . base64_encode($filename),
        );

        // Concatenate URI array, add URI-prefix, do GET-Request and check if result is OK
        return(0 === strcmp("OK", file_get_contents(self::$APIuriPrefix . implode('&', $uri))));
    }

    /**
     * Download links at HomeloadTV.
     *
     * @param   string      $directory  Download directory.
     * @param   boolean     $happyHour  Only download in Happy Hour
     * @param   string      $limit      Maximum number download links to get.
     * @param   string      $emailFrom  Sender address of emails with error messages.
     * @param   boolean     $thumbnails Download thumbnails true/false.
     * @param   boolean     $verbose    Verbose messages true/false.
     * @return  integer                 0: Ok, 1: no new links, 2: link limit
     * @pre     None.
     * @post    None.
     * @throws  Exception
     */
    public function download($directory, $happyHour = true, $limit = 100, $emailFrom = "", $thumbnails = true, $verbose = false) {

        // Check parameters
        if (!is_string($directory))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $directory must be string type!');
        if (empty($directory))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $directory must not be empty!');
        if (!is_writable($directory))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $directory must be writable!');
        if (!is_bool($happyHour))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $happyHour must be boolean type!');
        if (!is_bool($verbose))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $verbose must be boolean type!');
        if (!is_integer($limit))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $limit must be integer type!');
        if ($limit <= 0)
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $limit must be greater than zero!');
        if (!is_string($emailFrom))
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $emailFrom must be string type!');

        // Get list of links
        if ($verbose) echo "Downloading link list\n";
        $list = $this->getLinks($limit, true, $happyHour);

        // Check for links
        if (!empty($list['links'])) {

            // Create CURL object
            $this->curl = new CURL();
            if (!is_object($this->curl)) {
                throw new Exception(get_class() . '::' . __FUNCTION__ . ': Cannot create cURL object!');
            }

            if ($verbose) echo "Starting download\n";
            // Loop through links
            foreach ($list['links'] as $link) {

                // Get filename, name of recording and format if OTR recording
                $rec = array();
                preg_match('#.*/\d*_?(?P<filename>(?P<name>.+_TVOON_DE)\.(?P<format>.*))#', $link['url'], $rec);
                $filename = (isset($rec['filename'])) ? $rec['filename'] : '';

		// Exception Handling
		try{
		    // Download link
		    if ($verbose) echo $link['url'] . "\n";
		    $video = $this->curl->downloadFile($link['url'], $directory, $filename, 0660);
		} catch (Exception $e) {

		    // Call HomeloadTV-API and set state 'finished' with error message
		    $this->setState($link['id'], 'finished', 0, 0, 'BrokenLink');

		    // Throw exception for logging only
		    throw new Exception($e->getMessage(), 1, $e);

		    // Continue loop
		    continue;
		}

                // Handle errors
                $errors = array();
                if (0 != $video['errno']) {
                    $errors['video'] = $video;
                    print_r($video);
                }
                if ($verbose) echo "Downloaded: " . intval($video['size_download'] / 1024 / 1024) . "Mb with " . intval($video['speed_download'] / 1024) . "Kb/s\n";

                // Call HomeloadTV-API and set state
                $state = (0 == $video['errno']) ? 'finished' : 'new';

		// HTTP 4xx client errors: state=damaged
		if(intval($video['http_code']) >= 400 && intval($video['http_code']) <= 499) {
		    $state='damaged';
		}
		
                if ($verbose) echo "Changing state of link id " . $link['id'] . " to " . $state . "\n";
                $this->setState($link['id'], $state, intval($video['size_download'] / 1024), intval($video['speed_download'] / 1024), $video['error'], basename($video['filepath']));

                // Download thumbnails
                if ($thumbnails && (0 == $video['errno']) && !empty($filename) && isset($rec['name'])) {

                    // Loop through thumbnails
                    foreach (self::$ThumbUriSuffix as $key => $suffix) {

                        // Download thumbnail
                        if ($verbose) echo self::$ThumbUriPrefix . $rec['name'] . $suffix . "\n";
                        $thumb = $this->curl->downloadFile(self::$ThumbUriPrefix . $rec['name'] . $suffix, $directory, $filename . $suffix, 0660);

                        // Handle errors
                        if (0 != $thumb['errno']) {
                            $errors['thumb'][$key] = $thumb;
                            print_r($thumb);
                        }
                    }
                }

                // Send mail with error messages
                if (!empty($errors) && !empty($emailFrom)) {
                    mail($this->email, 'HomeLoadTV error message', print_r($video, true), 'From: ' . $emailFrom);
                }
            }

            // Destroy CURL object
            unset($this->curl);
        }
        switch (count($list['links'])) {
            case 0:
                if ($verbose) echo "No new links found!\n";
                return 1;
                break;
            case $limit:
                if ($verbose) echo "Link limit of $limit reached! Remaining entries will be downloaded next time.\n";
                return 2;
                break;
            default:
                return 0;
                break;
        }
    }

}

?>
