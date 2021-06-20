<?php

namespace AwtTech\Framework;

use AwtTech\Framework\AppConfig;
use AwtTech\Framework\SmartyTemplate;

abstract class Runner
{
    /**
     * @var object $dispatcher Fast Route Dispatcher
     */
    private $dispatcher = null;
    
    /**
     * @var array $routes List of Routes
     */
    private $routes = null;
    
    /**
     * @var obj $template Template Object
     */
    protected $template;
    
    /**
     * @var string $pageTemplate Path to the Page Template
     */
    protected $pageTemplate;
    
    /**
     * @var string $pageTemplate Path to the Layout Template
     */
    protected $layoutTemplate;
    
    /**
     * @var array $routeParams Parsed URL Arguments
     */
    protected $routeParams = [];
    
    /**
     * @var array $objectsForController Objects to Pass to Controllers
     */
    protected $objectsForController = [];
    
    /**
     * @var array HTTP response codes and messages
     */
    protected static $httpCodes = [
        //Informational 1xx
        100 => '100 Continue',
        101 => '101 Switching Protocols',
        //Successful 2xx
        200 => '200 OK',
        201 => '201 Created',
        202 => '202 Accepted',
        203 => '203 Non-Authoritative Information',
        204 => '204 No Content',
        205 => '205 Reset Content',
        206 => '206 Partial Content',
        226 => '226 IM Used',
        //Redirection 3xx
        300 => '300 Multiple Choices',
        301 => '301 Moved Permanently',
        302 => '302 Found',
        303 => '303 See Other',
        304 => '304 Not Modified',
        305 => '305 Use Proxy',
        306 => '306 (Unused)',
        307 => '307 Temporary Redirect',
        //Client Error 4xx
        400 => '400 Bad Request',
        401 => '401 Unauthorized',
        402 => '402 Payment Required',
        403 => '403 Forbidden',
        404 => '404 Not Found',
        405 => '405 Method Not Allowed',
        406 => '406 Not Acceptable',
        407 => '407 Proxy Authentication Required',
        408 => '408 Request Timeout',
        409 => '409 Conflict',
        410 => '410 Gone',
        411 => '411 Length Required',
        412 => '412 Precondition Failed',
        413 => '413 Request Entity Too Large',
        414 => '414 Request-URI Too Long',
        415 => '415 Unsupported Media Type',
        416 => '416 Requested Range Not Satisfiable',
        417 => '417 Expectation Failed',
        418 => '418 I\'m a teapot',
        422 => '422 Unprocessable Entity',
        423 => '423 Locked',
        426 => '426 Upgrade Required',
        428 => '428 Precondition Required',
        429 => '429 Too Many Requests',
        431 => '431 Request Header Fields Too Large',
        //Server Error 5xx
        500 => '500 Internal Server Error',
        501 => '501 Not Implemented',
        502 => '502 Bad Gateway',
        503 => '503 Service Unavailable',
        504 => '504 Gateway Timeout',
        505 => '505 HTTP Version Not Supported',
        506 => '506 Variant Also Negotiates',
        510 => '510 Not Extended',
        511 => '511 Network Authentication Required'
    ];
    
    /**
     * Constructor
     *
     * @param string $configFile Path to .ini config file
     * @param string $writableFolder Path to folder with write permissions
     */
    public function __construct(string $configFile, string $writableFolder)
    {
        AppConfig::load($configFile);
        
        if (!is_writable($writableFolder)) {
            throw new \Exception('Folder is not writable');
        }
        
        date_default_timezone_set(AppConfig::get('datetime', 'timezone', 'GMT'));
        
        $this->template = new SmartyTemplate($writableFolder);
        
        $this->setControllerObject('template', $this->template);
        
        $this->init();
    }
    
    /**
     * This function needs to be overridden as a constructor and to create the routes
     */
    abstract protected function init();
    
    /**
     * Method to register Application Routes
     * Uses GET by default
     * 
     * @param array $route List of Routes containing: route, accessChecks, function, HTTP method
     * 
     * @example
     * $this->registerRoutes([
     *     ['/',                                NULL,                           'home'],
     *     ['/openclipart/{term}',              'loginRequired:1|anotherCheck', 'openclipart', 'post']
     *     ['/openclipart/{term}[/{page:\d+}]', 'loginRequired:1|anotherCheck', 'openclipart', 'get']
     * ]);
     *
     */
    protected function registerRoutes(array $routes)
    {
        if ($this->routes !== null) {
            throw new \Exception('Routes have already been registered');
        }
        
        foreach ($routes as $route) {
            list($route_pattern, $accessChecks, $callback) = $route;
            
            // Defaults to get
            $method_list = isset($route[3]) ? $route[3] : 'get';
            
            $methods = explode('|', $method_list);
            
            foreach ($methods as $method) {
                $this->routes[] = ['method' => strtoupper($method), 'route_pattern'=>$route_pattern, 'handler'=>['callback'=>$callback, 'accesschecks'=>$accessChecks]];
            }
        }
        
        $this->dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r)  {
            foreach ($this->routes as $route) {
                $r->addRoute($route['method'], $route['route_pattern'], $route['handler']);
            }
        });
    }
    
    /**
     * Invoke the FastRoute Dispatch and invoke callback
     */
    public function run()
    {
        if (empty($this->routes)) {
            throw new \Exception('No Routes setup');
        }
        
        // Fetch method and URI from somewhere
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        
        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);
        
        // Fetch Route Info
        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);
        list($dispatchResult, $handler, $routeParams) = array_pad($routeInfo, 3, NULL);
        
        switch ($dispatchResult) {
            case \FastRoute\Dispatcher::FOUND:
                $this->routeParams = $routeParams;
                // First Run Access Checks
                if (!empty($handler['accesschecks'])) {
                    $this->runAccessChecks($handler['accesschecks']);
                }
                $this->runCallback($httpMethod, $handler['callback'], $routeParams);
                break;
            case \FastRoute\Dispatcher::NOT_FOUND:
                return $this->showPageNotFound();
                break;
            default:
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return $this->showMethodNotFound();
                break;
        }
    }
    
    /**
     * Run the Callback method for the Route
     * @string $httpMethod HTTP Method
     * @string $callback function or controller to call
     * @array $method_args Arguments to pass
     */
    private function runCallback($httpMethod, $callback, $method_args)
    {        
        // Check whether route is using a controller or the current class
        if (preg_match('/\A(?:[A-Z]\w+::[A-Z|a-z|_]\w+)\Z/', $callback)) {
            $parts = explode('::', $callback);
            
            $object = $this->loadController($parts[0]);
            $callback = $parts[1];
        } else {
            $object =& $this;
        }
        
        // Get Response
        $content = call_user_func_array([$object, $callback.'_'.strtolower($httpMethod)], array_values($method_args));
        
        return $this->renderContent($content, $object->layoutTemplate, $object->pageTemplate);
    }
    
    /**
     * Method to Render Content with Layout and Page Template
     * @param string $content Content
     * @param string $layoutTemplate Layout Template
     * @param string $pageTemplate Page Template
     */
    protected function renderContent($content, $layoutTemplate=NULL, $pageTemplate=NULL)
    {
        // Place in Layout Template
        if (!empty($layoutTemplate)) {
            $content = $this->template->loadTemplate($layoutTemplate, ['content'=>$content]);
        }
        
        // Place in Page Template
        if (!empty($pageTemplate)) {
            $content = $this->template->loadTemplate($pageTemplate, ['content'=>$content]);
        }
        
        echo $content;
    }
    
    /**
     * Method to run all the accessChecks for Routes
     * This method has to be public for closure, but the access check methods should be protected
     *
     * Multiple accessChecks are separated by pipes '|', parameters are separated by colons ':'
     * Example loginRequired:1|adminAccess
     *
     * For boolean parameters, use '1' instead of 'true'.
     *
     * Lastly the accesscheck methods in the class should be prepended by accesscheck_
     */
    private function runAccessChecks($accessChecks)
    {
        $accessChecks = explode('|', $accessChecks);
        
        foreach ($accessChecks as $accessCheck)
        {
            $params = explode(':', $accessCheck);
            $method = $params[0]; unset($params[0]);
            
            if (!empty($method)) {
                call_user_func_array([$this,'accesscheck_'.$method], $params);
            }
        }
        
        return;
    }
    
    /**
     * Method to load a controller
     * @param string $controller Controller Name
     * @return object Controller Object
     */
    protected function loadController($controller)
    {
        $className = $controller.'Controller';
        return new $className($this);
    }
    
    /**
     * Method to expose the list of objects that need to be loaded in the controller
     */
    final public function getObjectsForController()
    {
        return $this->objectsForController;
    }
    
    /**
     * Method to set an object which can be passed to the controller
     *
     * @param string $type
     * @param obj $object
     */
    final protected function setControllerObject($type, $object)
    {
        $this->objectsForController[$type] =& $object;
    }
    
    /**
     * Show Page Not Found
     */
    protected function showPageNotFound()
    {
        die('Page Not Found');
    }
    
    /**
     * Show Method Not Found
     */
    protected function showMethodNotFound()
    {
        return $this->showPageNotFound();
    }
    
    /**
     * Method to Redirect to Another Route
     *
     * @param string $redirect Route to Redirect To
     * @param int $statusCode HTTP Status Code
     */
    protected function redirect($redirect, $statusCode=302)
    {
        $this->setStatusCode($statusCode);
        header(sprintf("Location: %s", $redirect));
    }

    
    /**
     * Set HTTP Status Code
     *
     * @param int    $statusCode   200, 500, 403, etc
     */
    protected function setStatusCode($statusCode)
    {
        if (isset(self::$httpCodes[$statusCode])) {
            header(sprintf("HTTP/1.0 %s", self::$httpCodes[$statusCode]));
        }
    }
    
    /**
     * Method to set the page template
     * @param string $template Path to the page template
     */
    protected function setPageTemplate($template)
    {
        $this->pageTemplate = $template;
        $this->setControllerObject('pageTemplate', $template);
    }
    
    /**
     * Method to set the page template
     * @param string $template Path to the page template
     */
    protected function setLayoutTemplate($template)
    {
        $this->layoutTemplate = $template;
        $this->setControllerObject('layoutTemplate', $template);
    }
    
    /**
     * Method that can be used to set Ajax Response
     * Effectively, just turns off the page and layout template
     */
    protected function setIsAjaxResponse()
    {
        $this->setLayoutTemplate(NULL);
        $this->setPageTemplate(NULL);
    }
    
    /**
     * Method to get a GET Value
     * @param string $name Name of the item
     * @param mixed $default Default value to be used if not set
     */
    protected function getValue($name, $default='')
    {
        if (isset($_GET[$name])) {
            return $_GET[$name];
        } else {
            return $default;
        }
    }
    
    /**
     * Method to get a POST Value
     * @param string $name Name of the item
     * @param mixed $default Default value to be used if not set
     */
    protected function postValue($name, $default='')
    {
        if (isset($_POST[$name])) {
            return $_POST[$name];
        } else {
            return $default;
        }
    }
    
    /**
     * Method to get a $_SESSION Value
     * @param string $name Name of the item
     * @param mixed $default Default value to be used if not set
     */
    protected function sessionValue($name, $default='')
    {
        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        } else {
            return $default;
        }
    }
    
    /**
     * Method to set a $_SESSION Value
     * @param string $name Name of the Session Var
     * @param mixed $value Session Var Value
     */
    protected function setSessionValue($name, $value)
    {
        if (!isset($_SESSION)) { session_start(); }
        $_SESSION[$name] = $value;
    }
    
    /**
     * Method to unset a $_SESSION Value
     * @param string $name Name of the Session Var
     */
    protected function unsetSessionValue($name)
    {
        if (!isset($_SESSION)) { session_start(); }
        unset($_SESSION[$name]);
    }
}
