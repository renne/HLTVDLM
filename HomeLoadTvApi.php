<?php

/**
 * Class for communication with the HomeloadTV-API.
 *
 * The class provides methods to communicate with the HomeloadTV-API.
 *
 * @package     HomeLoadTV
 * @subpackage  HomeLoadTV
 * @name        HomeLoadTvApi
 * @description Class for communication with the HomeloadTV-API.
 * @author      Rene Bartsch <rene@bartschnet.de>
 * @copyright   Rene Bartsch 2013
 * @license     GNU GPL v.3
 * @link        https://github.com/renneb/HLTVDLM
 * @link        http://www.otrforum.com/showthread.php?62869-Api
 * @version     $Id$
 */
class HomeLoadTvApi {

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
     * Constructor.
     *
     * The constructor assigns the object variables.
     *
     * @param   string      $email      User email address.
     * @param   string      $password   User password.
     * @pre     None.
     * @post    None.
     * @throws  Exception               Wrong parameter type or value.
     * @todo    Check email string with regular expression.
     */
    public function __construct($email, $password) {

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
        $this->email    = $email;
        $this->password = $password;
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
     *                                  [LINKS]     => [n] => Array(
     *                                                              [url]      => Value String, full URL.
     *                                                              [filename] => Value String, filename.
     *                                                              [id]       => Value Integer, link ID.
     *                                  [LIST]      => value    Integer, list ID.
     *                                  [LINKCOUNT] => value    Integer, links waiting for download.
     *                                  [HHSTART]   => value    Integer, begin of Happy Hour CET/CEST.
     *                                  [HHEND]     => value    Integer, end   of Happy Hour CET/CEST.
     *                                                      )
     *                              )
     * @pre     None.
     * @post    None.
     * @throws  Exception           Wrong parameter type or value or error message from HomeloadTV.
     */
    public function getLinks($limit = 100, $active2new = true, $happyHour = true){

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
            'uid='      . $this->email,
            'password=' . $this->password,
            'limit='    . $limit
        );
        $uri[] = ($active2new === true) ? 'protocnew=true' : 'protocnew=false';
        $uri[] = ($happyHour === true)  ? 'onlyhh=true' : 'onlyhh=false';

        // Concatenate URI array and add URI-prefix
	$uri = self::$APIuriPrefix . implode('&', $uri);

	// Do GET-Request and unset uri variable
        $content = file_get_contents($uri);
        unset($uri);

        // Check if GET request has result
        if (!is_string($content) || empty($content)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': No result from GET request!');
	}

	// Check if GET request returns one string
	if (!strpos($content, ';')) {

	    // Return false for "NO_NEW_LINKS" or throw exception for anything unknown
            if (0 === strcmp("NO_NEW_LINKS", $content)) {
                return false;
            } else {
                throw new Exception(get_class() . '::' . __FUNCTION__ . ': ' . $content);
            }
        }

	// Add filename to list of links
	$content = preg_replace('/(.*\/[0-9]*_)(.*);([0-9]*);/m', '$1$2;$2;$3', $content);

	// Create result array
	$result = array();

	// Parse parameters from content
        preg_match_all('#([A-Z]+)=(\d+);#m', $content, $result);

	// Convert values of parameters to integer
        $result[2] = array_map('intval', $result[2]);

	// Combine keys and values of parameters
        $result = array_combine($result[1], $result[2]);

	// Create array of links
	$result['LINKS'] = array();

	// Parse links from content
	preg_match_all('#(?P<url>http://.+);(?P<filename>.+);(?P<id>\d+)#m', $content, $result['LINKS'], PREG_SET_ORDER);

	// Loop through array of links
	foreach ($result['LINKS'] as &$link) {

	    // Remove superflouos fields
            unset($link[0], $link[1], $link[2], $link[3]);

	    // Convert values of IDs to integer
            $link['id'] = intval($link['id']);
        }

	// Return result
	return($result);
    }

    /**
     * Sets the state of a download link.
     *
     * @param   integer $id         ID of download link.
     * @param   string  $state      Either "new" or "finished".
     * @param   integer $filesize   Filesize in KiloByte
     * @param   integer $speed      Download speed in KiloBit/s.
     * @param   string  $error      Empty string for success, "endHH" for end of Happy Hour or any other error message. Not used with state "new"
     * @param   string  $filename   Name of file.
     * @return  boolean             "true" on success or "false" on error.
     * @pre     None.
     * @post    None.
     */
    public function setState($id, $state = 'new', $filesize = 0, $speed = 0, $error = '', $filename = ''){
	echo "\n";
    }

} // End off class

?>
