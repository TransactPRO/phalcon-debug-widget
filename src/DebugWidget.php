<?php

namespace PDW;

use Phalcon\Db\Adapter\Pdo;
use Phalcon\Db\Profiler as Profiler;
use Phalcon\Di;
use Phalcon\DiInterface;
use Phalcon\Escaper as Escaper;
use Phalcon\Events\Event;
use Phalcon\Mvc\Url as URL;
use Phalcon\Mvc\View as View;

class DebugWidget implements \Phalcon\DI\InjectionAwareInterface
{

    protected $_di;
    private   $startTime;
    private   $endTime;
    private   $apiRequestStartTime;
    private   $apiRequestEndTime;
    private   $queryCount      = 0;
    private   $apiCallCount    = 0;
    protected $_profiler;
    protected $_viewsRendered  = array();
    protected $_apiCalls       = array();
    protected $_serviceNames   = array();
    private   $_panels         = array();
    private   $_curlOptionList = array(
        113   => 'CURLOPT_IPRESOLVE',
        91    => 'CURLOPT_DNS_USE_GLOBAL_CACHE',
        92    => 'CURLOPT_DNS_CACHE_TIMEOUT',
        3     => 'CURLOPT_PORT',
        10001 => 'CURLOPT_FILE',
        10009 => 'CURLOPT_READDATA',
        14    => 'CURLOPT_INFILESIZE',
        10002 => 'CURLOPT_URL',
        10004 => 'CURLOPT_PROXY',
        41    => 'CURLOPT_VERBOSE',
        42    => 'CURLOPT_HEADER',
        10023 => 'CURLOPT_HTTPHEADER',
        44    => 'CURLOPT_NOBODY',
        46    => 'CURLOPT_UPLOAD',
        47    => 'CURLOPT_POST',
        1     => 'CURLOPT_CERTINFO',
        52    => 'CURLOPT_FOLLOWLOCATION',
        54    => 'CURLOPT_PUT',
        10005 => 'CURLOPT_USERPWD',
        10006 => 'CURLOPT_PROXYUSERPWD',
        13    => 'CURLOPT_TIMEOUT',
        10015 => 'CURLOPT_POSTFIELDS',
        10016 => 'CURLOPT_REFERER',
        10018 => 'CURLOPT_USERAGENT',
        10022 => 'CURLOPT_COOKIE',
        96    => 'CURLOPT_COOKIESESSION',
        10025 => 'CURLOPT_SSLCERT',
        10026 => 'CURLOPT_SSLCERTPASSWD',
        81    => 'CURLOPT_SSL_VERIFYHOST',
        10031 => 'CURLOPT_COOKIEFILE',
        32    => 'CURLOPT_SSLVERSION',
        19913 => 'CURLOPT_RETURNTRANSFER',
        10028 => 'CURLOPT_QUOTE',
        10039 => 'CURLOPT_POSTQUOTE',
        61    => 'CURLOPT_HTTPPROXYTUNNEL',
        78    => 'CURLOPT_CONNECTTIMEOUT',
        64    => 'CURLOPT_SSL_VERIFYPEER',
        10065 => 'CURLOPT_CAINFO',
        10097 => 'CURLOPT_CAPATH',
        10082 => 'CURLOPT_COOKIEJAR',
        10083 => 'CURLOPT_SSL_CIPHER_LIST',
        19914 => 'CURLOPT_BINARYTRANSFER',
        99    => 'CURLOPT_NOSIGNAL',
        101   => 'CURLOPT_PROXYTYPE',
        27    => 'CURLOPT_CRLF',
        10102 => 'CURLOPT_ENCODING',
        59    => 'CURLOPT_PROXYPORT',
        107   => 'CURLOPT_HTTPAUTH',
        111   => 'CURLOPT_PROXYAUTH',
    );
    private   $apiCallTime     = 0;

    /**
     * DebugWidget constructor.
     * @param DiInterface $di
     * @param array       $serviceNames
     * @param array       $panels
     */
    public function __construct(
        $di,
        $serviceNames =
        array(
            'db'       => array('db'),
            'dispatch' => array('dispatcher'),
            'view'     => array('view')
        ),
        $panels =
        array(
            'server',
            'request',
            'views',
            'db'
        )
    )
    {
        $this->_di = $di;
        $this->startTime = microtime(true);
        $this->_profiler = new Profiler();

        $eventsManager = $di->get('eventsManager');

        foreach ($di->getServices() as $service) {
            /** @var Di\Service $service */
            $name = $service->getName();
            foreach ($serviceNames as $eventName => $services) {
                if (in_array($name, $services)) {
                    $service->setShared(true);
                    $di->get($name)->setEventsManager($eventsManager);
                    break;
                }
            }
        }
        foreach (array_keys($serviceNames) as $eventName) {
            $eventsManager->attach($eventName, $this);
        }
        $this->_serviceNames = $serviceNames;
        $this->_panels = $panels;
    }

    public function setDI(DiInterface $di)
    {
        $this->_di = $di;
    }

    public function getDI()
    {
        return $this->_di;
    }

    public function getServices($event)
    {
        return $this->_serviceNames[$event];
    }

    /**
     * @param Event $event
     * @param Pdo   $connection
     */
    public function beforeQuery($event, $connection)
    {
        $this->_profiler->startProfile(
            $connection->getRealSQLStatement(),
            $connection->getSQLVariables(),
            $connection->getSQLBindTypes()
        );
    }

    /**
     * @param Event $event
     * @param Pdo   $connection
     */
    public function afterQuery($event, $connection)
    {
        $this->_profiler->stopProfile();
        $this->queryCount++;
    }

    /**
     * Gets/Saves information about views and stores truncated viewParams.
     *
     * @param Event $event
     * @param View  $view
     * @param mixed $file
     */
    public function beforeRenderView($event, $view, $file)
    {
        $params = array();
        $toView = $view->getParamsToView();
        $toView = !$toView ? array() : $toView;
        foreach ($toView as $k => $v) {
            if (is_object($v)) {
                $params[$k] = get_class($v);
            } elseif (is_array($v)) {
                $array = array();
                foreach ($v as $key => $value) {
                    if (is_object($value)) {
                        $array[$key] = get_class($value);
                    } elseif (is_array($value)) {
                        foreach ($value as $k2 => $v2) {
                            if (is_array($v2)) {
                                $array[$key][$k2] = 'Array[...]';
                            } else {
                                $array[$key][$k2] = $v2;
                            }
                        }
                    } else {
                        $array[$key] = $value;
                    }
                }
                $params[$k] = $array;
            } else {
                $params[$k] = (string)$v;
            }
        }

        $this->_viewsRendered[] = array(
            'path'       => $view->getActiveRenderPath(),
            'params'     => $params,
            'controller' => $view->getControllerName(),
            'action'     => $view->getActionName(),
        );
    }

    /**
     * @param Event $event
     * @param mixed $apiProvider
     * @param       $data
     */
    public function beforeRequest($event, $apiProvider, $data)
    {
        $this->apiRequestStartTime = microtime(true);
        $this->_apiCalls[] = $data;
    }

    /**
     * @param Event $event
     * @param mixed $apiProvider
     */
    public function afterRequest($event, $apiProvider, $response)
    {
        $this->apiRequestEndTime = microtime(true);
        $this->_apiCalls[count($this->_apiCalls) - 1]['response'] = $response;
        $apiCallTime = ($this->apiRequestEndTime - $this->apiRequestStartTime);
        $this->_apiCalls[count($this->_apiCalls) - 1]['time'] = $apiCallTime . 's';
        $this->apiCallCount++;
        $this->apiCallTime += $apiCallTime;
    }


    /**
     * @param Event $event
     * @param View  $view
     * @param mixed $viewFile
     */
    public function afterRender($event, $view, $viewFile)
    {
        $this->endTime = microtime(true);
        $content = $view->getContent();
        //		$scripts = $this->getInsertScripts();
        //		$scripts .= "</head>";
        $scripts = "</head>";
        $content = str_replace("</head>", $scripts, $content);
        $rendered = $this->renderToolbar();
        $rendered .= "</body>";
        $content = str_replace("</body>", $rendered, $content);

        $view->setContent($content);
    }

    /**
     * Returns scripts to be inserted before <head>
     * Since setBaseUri may or may not end in a /, double slashes are removed.
     *
     * @return string
     */
    public function getInsertScripts()
    {
        $escaper = new Escaper();
        $url = $this->getDI()->get('url');
        $scripts = "";

        $css = array(
            '/pdw-assets/style.css',
            '/pdw-assets/lib/prism/prism.css'
        );
        foreach ($css as $src) {
            $link = $url->get($src);
            $link = str_replace("//", "/", $link);
            $scripts .= "<link rel='stylesheet' type='text/css' href='" . $escaper->escapeHtmlAttr($link) . "' />";
        }

        $js = array(
            '/pdw-assets/jquery.min.js',
            '/pdw-assets/lib/prism/prism.js',
            '/pdw-assets/pdw.js'
        );
        foreach ($js as $src) {
            $link = $url->get($src);
            $link = str_replace("//", "/", $link);
            $scripts .= "<script tyle='text/javascript' src='" . $escaper->escapeHtmlAttr($link) . "'></script>";
        }

        return $scripts;
    }

    public function renderToolbar()
    {
        $view = new View();
        $viewDir = dirname(__FILE__) . '/views/';
        $view->setViewsDir($viewDir);

        // set vars
        $view->debugWidget = $this;

        $content = $view->getRender('toolbar', 'index');

        return $content;
    }

    public function getPanels()
    {
        return $this->_panels;
    }

    public function setPanels(array $panels)
    {
        $this->_panels = $panels;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function getEndTime()
    {
        return $this->endTime;
    }

    public function getRenderedViews()
    {
        return $this->_viewsRendered;
    }

    public function getQueryCount()
    {
        return $this->queryCount;
    }

    public function getProfiler()
    {
        return $this->_profiler;
    }

    public function getRequestStartTime()
    {
        return $this->apiRequestStartTime;
    }

    public function getRequestEndTime()
    {
        return $this->apiRequestEndTime;
    }

    public function getApiCalls()
    {
        return $this->_apiCalls;
    }

    public function getApiCallTime()
    {
        return $this->apiCallTime;
    }

    public function getApiCallCount()
    {
        return $this->apiCallCount;
    }

    public function getCurlOption($opt)
    {
        return isset($this->_curlOptionList[$opt]) ? $this->_curlOptionList[$opt] : $opt;
    }

    public function indentJson($json)
    {
        $result = '';
        $pos = 0;
        $strLen = strlen($json);
        $indentStr = '  ';
        $newLine = "\n";
        $prevChar = '';
        $outOfQuotes = true;

        for ($i = 0; $i <= $strLen; $i++) {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

                // If this character is the end of an element,
                // output a new line and indent the next line.
            } else if (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos--;
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            // Add the character to the result string.
            $result .= $char;

            // If the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        return $result;
    }
}
