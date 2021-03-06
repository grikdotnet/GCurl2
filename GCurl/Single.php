<?php
/**
 * This file contains main GCurl class executing a single HTTP request.
 * It uses Request and Response classes.
 * 
 * @package GCurl
 * @author Grigori Kochanov http://www.grik.net/
 * @version 2.7
 */

namespace GCurl;

/**
 * A class to simplify complex tasks for performing and processing HTTP requests with CURL
 *
 * @package GCurl
 * @version 2
 * @author Grigori Kochanov
 *
 * @property IRequest $Request
 * @property Response $Response
 * @property Options $Options
 * @property URI $URI
 * @property resource $ch
 * @property int $request_counter
 */
class Single
{
    /**
     * Instance of the gCurlRequest object
     * see gCurlRequest.class.php
     *
     * @var IRequest
     * 
     */
    protected $Request;
    /**
     * Response object reference
     * see gCurlResponse.class.php
     *
     * @var Response
     */
    protected $Response;

    /**
     * @var Options
     */
    protected $Options;

    /**
     * instance of the URI class
     *
     * @var URI
     */
    protected $URI;

    /**
     * CURL resource handler
     *
     * @var resource
     */
    protected $ch;

    /**
     * Counter of the requests
     *
     * @var int
     */
    protected $request_counter;

    
    /**
     * The flag shows the request is ready to be sent
     * Set by prepare() method after setting all headers
     *
     * @var bool
     */
    private $is_prepared = false;

    /**
     * A shortcut to make a GET request
     * Usage:
     * $Response = \GCurl\Single::GET($url);
     * echo $Response;
     *
     * @param $uri
     * @param array $params
     * @return \GCurl\Response
     */
    public static function GET($uri,$params = [])
    {
        $Request = new GetRequest($uri);
        $GCurl = new Single($Request);
        if ($params) {
            foreach ($params as $k => $v) {
                $Request->addGetVar($k, $v);
            }
        }
        return $GCurl->exec();
    }

    /**
     * A shortcut to make a POST request
     * Usage: $Response = \GCurl\Single::POST($url,['a'=>1,'b'=>2]);
     * echo $Response;
     *
     * @param $uri
     * @param array $params
     * @return \GCurl\Response
     */
    public static function POST($uri,$params)
    {
        $Request = new PostUrlencodedRequest($uri);
        $GCurl = new Single($Request);
        if ($params) {
            foreach ($params as $k => $v) {
                $Request->addPostVar($k, $v);
            }
        }
        return $GCurl->exec();
    }

    /**
     * A shortcut to make a PUT request
     * Usage: $Response = \GCurl\Single::PUT($url,$file_path);
     * echo $Response;
     *
     * @param $uri
     * @param string $file_path
     * @return \GCurl\Response
     */
    public function PUT($uri,$file_path) {
        $Request = new PutFileRequest($uri,$file_path);
        $GCurl = new Single($Request);
        return $GCurl->exec();
    }

    /**
     * Constructor of the class
     *
     * @param IRequest $Request
     * @throws Exception
     * @internal param $url
     * @internal param string $method
     * @return \GCurl\Single
     */
    public function __construct(IRequest $Request)
    {
        if (!defined('CURLE_OK')) {
            throw new \GCurl\Exception(10);
        }

        $this->ch = curl_init();
        if (!$this->ch || Exception::catchError($this->ch)) {
            throw new \GCurl\Exception(15);
        }

        $this->Request = $Request;
        $this->URI = $this->Request->getURI();
        $this->Response = new Response($this->ch, $this->URI);

        $this->Options = new Options($this->ch);
        $this->Options->setBasicParams();
        //set the response headers handler
        $this->Options->setHeadersHandler(array($this->Response,'headersHandler'));
    }

    /**
     * signal a redirect URL
     *
     * @param string $new_uri
     */
    public function redirect($new_uri)
    {
        $this->URI->redirect($new_uri);

        //create request and response objects
        $this->Request = new GetRequest($this->URI);
        $this->Response = new Response($this->ch,$this->URI);
        $this->is_prepared = false;
    }

    /**
     * Run the CURL engine
     *
     * @throws Exception
     * @return Response
     */
    public function exec()
    {
        if (!$this->is_prepared){
            $this->Request->prepare($this->Options);
            $this->is_prepared = true;
        }
        //run the request
        ++$this->request_counter;

        $result = curl_exec($this->ch);

        if ($this->Options->returnTransfer() && !$result && !$this->Response->headers['len']){
            throw new Exception(115);
        }

        $this->Request->onRequestEnd();

        //return the response data if required
        if ($this->Options->returnTransfer() && is_string($result)){
            $this->Response->body = $result;
        }
        $this->is_prepared = false;

        return $this->Response;
    }

    
    /**
     * close connection to the remote host
     *
     */
    public function disconnect()
    {
        if (is_resource($this->ch)){
            curl_close($this->ch);
        }
        $this->ch = NULL;
    }

    /**
     * Closing the curl handler is required for repetitive requests
     * to release memory used by cURL
     */
    public function __destruct()
    {
        unset($this->Request,$this->Response,$this->URI);
        $this->disconnect();
    }

    /**
     * Provides a read-only access
     * @param $key
     */
    public function __get($key)
    {
        $read_only_properties = ['Request','Response','Options','URI','ch','request_counter'];
        if (in_array($key,$read_only_properties)){
            return $this->$key;
        }
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $key .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
    }
}
