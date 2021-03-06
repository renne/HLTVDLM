<?php

/**
 * The class CURL downloads a given HTTP-URL into given directory.
 *
 * The class supports
 * - HTTP-503-queues
 * - resuming downloads
 * - locking files while writing
 * - setting file mode (Unix-chmod-style)
 * - setting filename or deriving filename from URL
 *
 * @package     HomeLoadTV
 * @subpackage  cURL
 * @name        CURL
 * @description Class for downloading files via HTTP.
 * @author      Rene Bartsch <rene@bartschnet.de>
 * @copyright   Rene Bartsch 2013
 * @license     GNU GPL v.3
 * @link        https://github.com/renneb/HLTVDLM
 * @version     $Id$
 */
class CURL {

    /**
     * Information from HTTP-header.
     * @var string $header
     */
    private $header = '';

    /**
     * File descriptor for writing to file.
     * @var Filedescriptor $fd
     */
    private $fd = null;

    /**
     * The destructor makes sure the file descriptor is closed and unlocked.
     * @pre     None.
     * @post    None.
     */
    public function __destruct() {
	@flock($this->fd, LOCK_UN);
        @fclose($this->fd);
    }

    /**
     * Callback function to get HTTP-header information.
     *
     * @param   resource    $con    cURL connection resource.
     * @param   string      $header String with header line.
     * @return  integer             Length of header in byte.
     * @pre     None.
     * @post    None.
     */
    private function getHeader($con, $header) {

        // Check if content length does not exceed maximum file size
        $content_length = preg_replace('/.*Content-Length: *(\d+).*/s', '$1', $this->header);
        if($content_length > PHP_INT_MAX) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': '.$content_length.' bytes file size is too big for '.(PHP_INT_SIZE*8).' bit-PHP !!!');
        }
        unset($content_length);

        // Add header line to object variable
        $this->header .= $header;

        // !!! Necessary for cURL to work !!!
        return mb_strlen($header);
    }

    /**
     * Downloads a file.
     *
     * The filepath is created by appending the filename from $url to $directory.
     *
     * @param   string  $url        Download URL
     * @param   string  $directory  Target directory.
     * @param   string  $filename   User provided filename or empty for automatic.
     * @param   integer $mode       File attributes (UNIX-chmod-style).
     * @return  array of strings    "FALSE" on local errors or Array(
     *                                  [filepath]                  => string   Path to downloaded file.
     *                                  [errno]                     => integer  CURL error number (zero on success).
     *                                  [error]                     => string   CURL error description.
     *                                  [url]                       => string   Last effective URL.
     *                                  [content_type]              => string   Content-Type: of the requested document, NULL indicates server did not send valid Content-Type: header.
     *                                  [http_code]                 => integer  Last received HTTP code.
     *                                  [header_size]               => integer  Total size of all headers received.
     *                                  [request_size]              => integer  Total size of issued requests, currently only for HTTP requests.
     *                                  [filetime]                  => integer  Remote time of the retrieved document, if -1 is returned the time of the document is unknown.
     *                                  [ssl_verify_result]         => integer  Result of SSL certification verification requested by setting CURLOPT_SSL_VERIFYPEER.
     *                                  [redirect_count]            => integer  Number of redirects.
     *                                  [total_time]                => float    Total transaction time in seconds for last transfer.
     *                                  [namelookup_time]           => float    Time in seconds until name resolving was complete.
     *                                  [connect_time]              => float    Time in seconds it took to establish the connection.
     *                                  [pretransfer_time]          => float    Time in seconds from start until just before file transfer begins.
     *                                  [size_upload]               => integer  Total number of bytes uploaded.
     *                                  [size_download]             => integer  Total number of bytes downloaded.
     *                                  [speed_download]            => integer  Average download speed.
     *                                  [speed_upload]              => integer  Average upload speed.
     *                                  [download_content_length]   => integer  content-length of download, read from Content-Length: field.
     *                                  [upload_content_length]     => integer  Specified size of upload.
     *                                  [starttransfer_time]        => float    Time in seconds until the first byte is about to be transferred.
     *                                  [redirect_time]             => float    Time in seconds of all redirection steps before final transaction was started.
     *                              )
     * @pre     None.
     * @post    None.
     * @throws  Exception
     * @todo    Escape/normalize filename.
     */
    public function downloadFile($url, $directory, $filename = '', $mode = 0644) {

        // Check parameters
        if (!is_string($url)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $url must be string type!');
        }
        if (empty($url)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $url must not be empty!');
        }
        if (!is_string($directory)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $directory must be string type!');
        }
        if (empty($directory)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $directory must not be empty!');
        }
        if (!is_writable($directory)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $directory must be writable!');
        }
        if (!is_string($filename)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $filename must be string type!');
        }
        if (!is_integer($mode)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Parameter $mode must be integer type!');
        }
        if ($mode < 0) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': $mode must be greater than zero!');
        }

        // Array with information about transfer
        $result = array(
            // Create filepath from URL or given filename
            'filepath' => (!empty($filename)) ? $directory . '/' . $filename : $directory . '/' . basename($url)
        );

        // Open target file by appending or new file
        if (($this->fd = fopen($result['filepath'], "a")) === FALSE) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Cannot open file descriptor of file ' . $result['filepath'] . '!');
            return FALSE;
        }

        // Set lock on file
        if (!flock($this->fd, LOCK_EX | LOCK_NB)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Cannot set lock on file descriptor of file ' . $result['filepath'] . '!');
            @fclose($this->fd);
            return FALSE;
        }

        // Initialize cURL
        if (($con = curl_init($url)) === FALSE) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Initalizing cURL with URL ' . $url . 'failed!');
	    flock($this->fd, LOCK_UN);
            @fclose($this->fd);
            return FALSE;
        }

	// Clear statcache for filesize()
	clearstatcache();

        // Set cURL options
        if (!curl_setopt_array($con, array(
                    // Select writing to file
                    CURLOPT_FILE => $this->fd,
                    // Do not write to file if HTTP status code >= 400
                    CURLOPT_FAILONERROR => true,
                    // Resume position of existing file
                    CURLOPT_RESUME_FROM => filesize($result['filepath']),
                    // Follow redirects
                    CURLOPT_FOLLOWLOCATION => true,
                    // Set callback function for parsing headers
                    CURLOPT_HEADERFUNCTION => array($this, 'getHeader'),
		    // Set user agent
		    CURLOPT_USERAGENT => 'HLTVDLM 1.0 https://github.com/renneb/HLTVDLM',
	))) {
            throw new Exception(__FILE__ . ':' . __LINE__ . ' ' . get_class() . '::' . __FUNCTION__ . ' ' . curl_error($con));
	    @flock($this->fd, LOCK_UN);
            @fclose($this->fd);
            return FALSE;
        }

        // Download URL and loop for HTTP code 503 "Temporarily unavailable"
        while(true) {

            // Reset HTTP header information
            $this->header = '';

            // Execute query
	    $execres = @curl_exec($con);

            // Get info about transfer
            $result['errno'] = curl_errno($con);
            $result['error'] = curl_error($con);
            $result = @array_merge($result, curl_getinfo($con));

	    // Leave download loop on success
	    if($execres) {
		break;
	    }

	    // Handle HTTP 4xx client errors, leave download loop
	    if(intval($result['http_code']) >= 400 && intval($result['http_code']) <= 499) {
		break;
	    }

	    // Handle HTTP code 503 "Temporarily unavailable"
	    if(503 === intval($result['http_code'])) {

		// Get retry time and wait
                sleep(intval(preg_replace('/.*Retry-After: *(\d+).*/s', '$1', $this->header)));
		continue;
	    }

	    // Handle exceptions
            throw new Exception(__FILE__ . ':' . __LINE__ . ' ' . get_class() . '::' . __FUNCTION__ . ' ' . curl_error($con));
	    @flock($this->fd, LOCK_UN);
	    @fclose($this->fd);
	    return FALSE;

        } // While loop

        // Close cURL object
        @curl_close($con);

        // Close/unlock file descriptor
	@flock($this->fd, LOCK_UN);
        @fclose($this->fd);

	// Get current size of file
	clearstatcache();
	$result['size_download'] = filesize($result['filepath']);

        // Get filename from HTTP-header if $filename not set
        $matches = array();
        preg_match('#.*Content-Disposition: *attachment; *filename="(?P<filename>[[:print:]]+)".*#s', $this->header, $matches);

        // Check if no filename is given AND filename is provided by HTTP-header
        if (empty($filename) && !empty($matches['filename'])) {

            // Rename file
            $oldpath = $result['filepath'];
            $result['filepath'] = $directory . '/' . $matches['filename'];
            if (!@rename($oldpath, $result['filepath'])) {
                throw new Exception(get_class() . '::' . __FUNCTION__ . ': Renaming file ' . $oldpath . ' to ' . $result['filepath'] . ' failed!');
                return FALSE;
            }
        }
        unset($matches);

        // Set file attributes
        if (!@chmod($result['filepath'], $mode)) {
            throw new Exception(get_class() . '::' . __FUNCTION__ . ': Setting attributes for file ' . $result['filepath'] . ' failed!');
            return FALSE;
        }

        // Return transfer information
        return $result;
    }

}

?>
