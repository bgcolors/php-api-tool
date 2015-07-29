<?php error_reporting(E_ERROR) ?>
<?php 

list($url, $method, $names, $values) = array_map(function($v){
  return empty($_GET[$v]) ? '' : $_GET[$v];
},array('url', 'method', 'names', 'values'));

// echo $url,$method,$names,$values;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>APITool - made by bg</title>
    <link rel="stylesheet" href="//cdn.bootcss.com/bootstrap/3.3.5/css/bootstrap.min.css">
</head>
<body>

<div class="container">
  <h3>API TOOL</h3>
  <form method="get">
    <div class="form-group">
      <label for="url">URL(192.168.83.60:8084/app-ws/)</label>
      <input type="text" class="form-control" id="url" placeholder="URL" name="url" value="<?php echo empty($url) ? '' : $url; ?>">
    </div> 
    <div class="form-group">
      <label for="method">Method</label>
      <select name="method" class="form-control" id="method">
        <option value="1" <?php echo empty($_GET['method']) || $_GET['method'] != 2 ? 'selected="true"' : '' ?>>GET</option>
        <option value="2" <?php echo !empty($_GET['method']) && $_GET['method'] == 2 ? 'selected="true"' : '' ?>>POST</option>
      </select>
    </div>
    <div class="form-group">
      <label for="names">Parameter Names</label>
      <textarea class="form-control" rows="5" name="names" id="names"><?php echo empty($names) ? '' : $names; ?></textarea>
    </div>
    <div class="form-group">
      <label for="values">Parameter Values</label>
      <textarea class="form-control" rows="5" name="values" id="values"><?php echo empty($values) ? '' : $values; ?></textarea>
    </div>
    <div class="form-group">
      <button type="submit" class="btn btn-default">Send</button>
    </div>
<?php 
$res = '';
if ($url) {
  $namesArr = explode("\r\n", $names);
  $valuesArr = explode("\r\n", $values);
  $params = [];
  $l = count($namesArr);

  for ($i = 0;$i < $l;$i++) {
    if (empty($namesArr[$i])) continue;
    $params[$namesArr[$i]] = empty($valuesArr[$i]) ? '' : $valuesArr[$i];
  }

  // print_r($params);
  $pest = new Pest;

  switch ($method) {
    case '':
      break;
    case 1;
      try {
        $res = $pest->get($url, $params);
      } catch (Exception $e) {
        $fail = true;
      }
      break;
    case 2;
      try {
        $res = $pest->post($url, $params);
      } catch (Exception $e) {
        $fail = true;
      }
      break;
    default:
      # code...
      break;
  }
}
 ?>
    <div class="form-group">
      <label for="res">Result</label>
      <textarea class="form-control" rows="15" id="res"><?php echo empty($fail) ? $res : ''?></textarea>
    </div>
    <div class="form-group<?php echo empty($url) || !empty($fail) ? ' has-error' : '';?>">
      <label for="res">error</label>
      <textarea class="form-control" rows="3" id="res"><?php echo empty($url) ? "url can not be empty.\n" : ''; echo empty($fail) ? '' : "fail to get contents.\n"?></textarea>
    </div>
  </form>
</div>
    
</body>
</html>
<?php
class Pest
{
    /**
     * @var array Default CURL options
     */
    public $curl_opts = array(
        CURLOPT_RETURNTRANSFER => true, // return result instead of echoing
        CURLOPT_SSL_VERIFYPEER => false, // stop cURL from verifying the peer's certificate
        CURLOPT_FOLLOWLOCATION => false, // follow redirects, Location: headers
        CURLOPT_MAXREDIRS => 10, // but dont redirect more than 10 times
        CURLOPT_HTTPHEADER => array()
    );

    /**
     * @var string Base URL
     */
    public $base_url;

    /**
     * @var array Last response
     */
    public $last_response;

    /**
     * @var array Last request
     */
    public $last_request;

    /**
     * @var array Last headers
     */
    public $last_headers;

    /**
     * @var bool Throw exceptions on HTTP error codes
     */
    public $throw_exceptions = true;


    /**
     * Class constructor
     * @param string $base_url
     * @throws Exception
     */
    public function __construct($base_url)
    {
        if (!function_exists('curl_init')) {
            throw new Exception('CURL module not available! Pest requires CURL. See http://php.net/manual/en/book.curl.php');
        }

        /*
         * Only enable CURLOPT_FOLLOWLOCATION if safe_mode and open_base_dir are
         * not in use
         */
        if (ini_get('open_basedir') == '' && strtolower(ini_get('safe_mode')) == 'off') {
            $this->curl_opts['CURLOPT_FOLLOWLOCATION'] = true;
        }

        $this->base_url = $base_url;

        // The callback to handle return headers
        // Using PHP 5.2, it cannot be initialised in the static context
        $this->curl_opts[CURLOPT_HEADERFUNCTION] = array($this, 'handle_header');
    }

    /**
     * Setup authentication
     *
     * @param string $user
     * @param string $pass
     * @param string $auth  Can be 'basic' or 'digest'
     */
    public function setupAuth($user, $pass, $auth = 'basic')
    {
        $this->curl_opts[CURLOPT_HTTPAUTH] = constant('CURLAUTH_' . strtoupper($auth));
        $this->curl_opts[CURLOPT_USERPWD] = $user . ":" . $pass;
    }

    /**
     * 
     * @param array $cookies
     *
     * @see http://curl.haxx.se/docs/manpage.html
     * @see http://www.nczonline.net/blog/2009/05/05/http-cookies-explained/
     */
    public function setupCookies($cookies)
    {
        if (empty($cookies)) {
            return;
        }
        $cookie_list = array();
        foreach ($cookies as $cookie_name => $cookie_value)
        {
            $cookie = urlencode($cookie_name);
            if (isset($cookie_value))
            {
                $cookie .= '=';
                $cookie .= urlencode($cookie_value);
            }
            $cookie_list[] = $cookie;
        }
        $this->curl_opts[CURLOPT_COOKIE] = implode(';', $cookie_list);
    }

    /**
     * Setup proxy
     * @param string $host
     * @param int $port
     * @param string $user Optional.
     * @param string $pass Optional.
     */
    public function setupProxy($host, $port, $user = NULL, $pass = NULL)
    {
        $this->curl_opts[CURLOPT_PROXYTYPE] = 'HTTP';
        $this->curl_opts[CURLOPT_PROXY] = $host;
        $this->curl_opts[CURLOPT_PROXYPORT] = $port;
        if ($user && $pass) {
            $this->curl_opts[CURLOPT_PROXYUSERPWD] = $user . ":" . $pass;
        }
    }

    /**
     * Perform HTTP GET request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return string
     */
    public function get($url, $data = array(), $headers=array())
    {
        if (!empty($data)) {
            $pos = strpos($url, '?');
            if ($pos !== false) {
                $url = substr($url, 0, $pos);
            }
            $url .= '?' . http_build_query($data);  // 将data数组变成a=1&b=2...
        }

        $curl_opts = $this->curl_opts;
        
        $curl_opts[CURLOPT_HTTPHEADER] = $this->prepHeaders($headers);

        $curl = $this->prepRequest($curl_opts, $url);
        $body = $this->doRequest($curl);
        $body = $this->processBody($body);

        return $body;
    }

    /**
     * Prepare request
     *
     * @param array $opts
     * @param string $url
     * @return resource
     * @throws Pest_Curl_Init
     */
    protected function prepRequest($opts, $url)
    {
        if (strncmp($url, $this->base_url, strlen($this->base_url)) != 0) {
            $url = rtrim($this->base_url, '/') . '/' . ltrim($url, '/');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new Pest_Curl_Init($this->processError(curl_error($curl), 'curl'));
        }

        foreach ($opts as $opt => $val)
            curl_setopt($curl, $opt, $val);

        $this->last_request = array(
            'url' => $url
        );

        if (isset($opts[CURLOPT_CUSTOMREQUEST]))
            $this->last_request['method'] = $opts[CURLOPT_CUSTOMREQUEST];
        else
            $this->last_request['method'] = 'GET';

        if (isset($opts[CURLOPT_POSTFIELDS]))
            $this->last_request['data'] = $opts[CURLOPT_POSTFIELDS];

        return $curl;
    }
    
    /**
     * Determines if a given array is numerically indexed or not
     *
     * @param array $array
     * @return boolean
     */
    protected function _isNumericallyIndexedArray($array)
    {
        return !(bool)count(array_filter(array_keys($array), 'is_string'));
    }
    
    /**
     * Flatten headers from an associative array to a numerically indexed array of "Name: Value"
     * style entries like CURLOPT_HTTPHEADER expects. Numerically indexed arrays are not modified.
     *
     * @param array $headers
     * @return array
     */
    protected function prepHeaders($headers)
    {
        if ($this->_isNumericallyIndexedArray($headers)) {
            return $headers;
        }
        
        $flattened = array();
        foreach ($headers as $name => $value) {
             $flattened[] = $name . ': ' . $value;
        }
        
        return $flattened;
    }

    /**
     * Process error
     * @param string $body
     * @return string
     */
    protected function processError($body)
    {
        // Override this in classes that extend Pest.
        // The body of every erroneous (non-2xx/3xx) GET/POST/PUT/DELETE
        // response goes through here prior to being used as the 'message'
        // of the resulting Pest_Exception
        return $body;
    }

    /**
     * Do CURL request
     * @param resource $curl
     * @return mixed
     * @throws Pest_Curl_Exec
     * @throws Pest_Curl_Meta
     */
    private function doRequest($curl)
    {
        $this->last_headers = array();
        $this->last_response = array();
        // curl_error() needs to be tested right after function failure
        $this->last_response["body"] = curl_exec($curl);
        if ($this->last_response["body"] === false && $this->throw_exceptions) {
           // throw new Pest_Curl_Exec(curl_error($curl));
        }

        $this->last_response["meta"] = curl_getinfo($curl);
        if ($this->last_response["meta"] === false && $this->throw_exceptions) {
            throw new Pest_Curl_Meta(curl_error($curl));
        }

        curl_close($curl);

        $this->checkLastResponseForError();

        return $this->last_response["body"];
    }

    /**
     * Check last response for error
     *
     * @throws Pest_Conflict
     * @throws Pest_Gone
     * @throws Pest_Unauthorized
     * @throws Pest_ClientError
     * @throws Pest_MethodNotAllowed
     * @throws Pest_NotFound
     * @throws Pest_BadRequest
     * @throws Pest_UnknownResponse
     * @throws Pest_InvalidRecord
     * @throws Pest_ServerError
     * @throws Pest_Forbidden
     */
    protected function checkLastResponseForError()
    {
        if (!$this->throw_exceptions)
            return;

        $meta = $this->last_response['meta'];
        $body = $this->last_response['body'];

        if ($meta === false)
            return;
//print_r($meta);exit;
        switch ($meta['http_code']) {
            
            // case 400:
            //     throw new Pest_BadRequest($this->processError($body));
            //     break;
            // case 401:
            //     throw new Pest_Unauthorized($this->processError($body));
            //     break;
            // case 403:
            //     throw new Pest_Forbidden($this->processError($body));
            //     break;
            // case 404:
            //     throw new Pest_NotFound($this->processError($body));
            //     break;
            // case 405:
            //     throw new Pest_MethodNotAllowed($this->processError($body));
            //     break;
            // case 409:
            //     throw new Pest_Conflict($this->processError($body));
            //     break;
            // case 410:
            //     throw new Pest_Gone($this->processError($body));
            //     break;
            // case 422:
            //     // Unprocessable Entity -- see http://www.iana.org/assignments/http-status-codes
            //     // This is now commonly used (in Rails, at least) to indicate
            //     // a response to a request that is syntactically correct,
            //     // but semantically invalid (for example, when trying to
            //     // create a resource with some required fields missing)
            //     throw new Pest_InvalidRecord($this->processError($body));
            //     break;
            // default:
            //     if ($meta['http_code'] >= 400 && $meta['http_code'] <= 499)
            //         throw new Pest_ClientError($this->processError($body));
            //     elseif ($meta['http_code'] >= 500 && $meta['http_code'] <= 599)
            //         throw new Pest_ServerError($this->processError($body)); elseif (!isset($meta['http_code']) || $meta['http_code'] >= 600) {
            //         throw new Pest_UnknownResponse($this->processError($body));
            //     }
        }
    }

    /**
     * Process body
     * @param string $body
     * @return string
     */
    protected function processBody($body)
    {
        // Override this in classes that extend Pest.
        // The body of every GET/POST/PUT/DELETE response goes through
        // here prior to being returned.
        return $body;
    }

    /**
     * Perform HTTP HEAD request
     * @param string $url
     * @return string
     */
    public function head($url)
    {
        $curl_opts = $this->curl_opts;
        $curl_opts[CURLOPT_NOBODY] = true;

        $curl = $this->prepRequest($curl_opts, $url);
        $body = $this->doRequest($curl);

        $body = $this->processBody($body);

        return $body;
    }

    /**
     * Perform HTTP POST request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return string
     */
    public function post($url, $data, $headers = array())
    {
//        print_r($url);
//        print_r($data);//exit;
//        $data;
//        exit;
        $data = $this->prepData($data);
        $curl_opts = $this->curl_opts;
        $curl_opts[CURLOPT_CUSTOMREQUEST] = 'POST';
        if (!is_array($data)) $headers[] = 'Content-Length: ' . strlen($data);
        $curl_opts[CURLOPT_HTTPHEADER] = $this->prepHeaders($headers);
        $curl_opts[CURLOPT_POSTFIELDS] = $data;
//    print_r($this->prepHeaders($headers)); die();
        $curl = $this->prepRequest($curl_opts, $url);
        $body = $this->doRequest($curl);
        $body = $this->processBody($body);
//    print_r($data);die();
        return $body;
    }

    /**
     * Prepare data
     * @param array $data
     * @return array|string
     */
    public function prepData($data)
    {
        if (is_array($data)) {
            $multipart = false;

            foreach ($data as $item) {
                if (is_string($item) && strncmp($item, "@", 1) == 0 && is_file(substr($item, 1))) {
                    $multipart = true;
                    break;
                }
            }

            return ($multipart) ? $data : http_build_query($data);
        } else {
            return $data;
        }
    }

    /**
     * Perform HTTP PUT request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return string
     */
    public function put($url, $data, $headers = array())
    {
        $data = $this->prepData($data);

        $curl_opts = $this->curl_opts;
        $curl_opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        if (!is_array($data)) $headers[] = 'Content-Length: ' . strlen($data);
        $curl_opts[CURLOPT_HTTPHEADER] = $this->prepHeaders($headers);
        $curl_opts[CURLOPT_POSTFIELDS] = $data;

        $curl = $this->prepRequest($curl_opts, $url);
        $body = $this->doRequest($curl);

        $body = $this->processBody($body);

        return $body;
    }

    /**
     * Perform HTTP PATCH request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return string
     */
    public function patch($url, $data, $headers = array())
    {
        $data = (is_array($data)) ? http_build_query($data) : $data;

        $curl_opts = $this->curl_opts;
        $curl_opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        $headers[] = 'Content-Length: ' . strlen($data);
        $curl_opts[CURLOPT_HTTPHEADER] = $this->prepHeaders($headers);
        $curl_opts[CURLOPT_POSTFIELDS] = $data;

        $curl = $this->prepRequest($curl_opts, $url);
        $body = $this->doRequest($curl);

        $body = $this->processBody($body);

        return $body;
    }

    /**
     * Perform HTTP DELETE request
     *
     * @param string $url
     * @param array $headers
     * @return string
     */
    public function delete($url, $headers=array())
    {
        $curl_opts = $this->curl_opts;
        $curl_opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        $curl_opts[CURLOPT_HTTPHEADER] = $this->prepHeaders($headers);

        $curl = $this->prepRequest($curl_opts, $url);
        $body = $this->doRequest($curl);

        $body = $this->processBody($body);

        return $body;
    }

    /**
     * Get last response body
     *
     * @return string
     */
    public function lastBody()
    {
        return $this->last_response['body'];
    }

    /**
     * Get last response status
     *
     * @return int
     */
    public function lastStatus()
    {
        return $this->last_response['meta']['http_code'];
    }

    /**
     * Return the last response header (case insensitive) or NULL if not present.
     * HTTP allows empty headers (e.g. RFC 2616, Section 14.23), thus is_null()
     * and not negation or empty() should be used.
     *
     * @param string $header
     * @return string
     */
    public function lastHeader($header)
    {
        if (empty($this->last_headers[strtolower($header)])) {
            return NULL;
        }
        return $this->last_headers[strtolower($header)];
    }

    /**
     * Handle header
     * @param $ch
     * @param $str
     * @return int
     */
    private function handle_header($ch, $str)
    {
        if (preg_match('/([^:]+):\s(.+)/m', $str, $match)) {
            $this->last_headers[strtolower($match[1])] = trim($match[2]);
        }
        return strlen($str);
    }
  public function url(){
    echo $this->base_url;
  }
}


?>