<?php

namespace syar;

use swoole_server;
use swoole_http_server;
use syar\event\InterfaceEventDispatcher;
use syar\event\InterfaceListen;

/**
 * Class Server
 * @package syar
 */
class Server {
    use log\TraitLog;

	protected $host = '0.0.0.0';
	protected $port;

    /**
     * @var Protocol
     */
    public $protocol;

	/**
	 * @var TaskManager
	 */
	public $taskManager;

    public $timeout;
    static $sw_mode = SWOOLE_PROCESS;

    /**
     * @var swoole_server
     */
    protected $sw;
	protected $setting = array(
        // worker process num,
	    // 注意设置linux 开文件数量(open files)
        //  ulimit -n
		'max_connection' => 10240,
		'worker_num' => 8,       //worker process num
		'max_request' => 10000,
		'task_worker_num' => 10,
		'task_max_request' => 10000,
		'backlog' => 128,        //listen backlog
		'open_tcp_keepalive' => 1,
		'heartbeat_check_interval' => 5,
		'heartbeat_idle_time' => 10,
		'http_parse_post' => false,
	);

    function __construct($host, $port) {
        $this->host = $host;
        $this->port = $port;
	    $this->sw = $this->createServer();
    }

	protected function createServer(){
		return new swoole_http_server($this->host, $this->port);
	}

	/**
	 * @param $key
	 * @param null $value
	 * @return $this
	 */
	function setOption($key, $value = null){
        if(is_array($key)){
	        $this->setting = array_merge($this->setting, $key);
        } else {
	        $this->setting[$key] = $value;
        }
		return $this;
    }


    function daemonize() {
        $this->setting['daemonize'] = 1;
    }

    /**
     * @param $protocol
     * @return $this
     * @throws \Exception
     */
    function setProtocol($protocol){
	    $this->protocol = $protocol;
	    return $this;
    }

    /**
     * @return Protocol
     * @throws \Exception
     */
    public function getProtocol() {
	    if(!isset($this->protocol)){
		    $this->protocol = new Protocol();
	    }
	    return $this->protocol;
    }

	/**
	 * @return TaskManager
	 */
	public function getTaskManager(){
		if(!isset($this->taskManager)){
			$this->taskManager = new TaskManager($this->sw);
		}
		return $this->taskManager;
	}

	/**
	 * @return swoole_http_server|swoole_server
	 */
	function getSwooleServer(){
		return $this->sw;
	}

    /**
     * @param array $setting
     */
    function run($setting = array()) {
        register_shutdown_function(array($this, 'handleFatal'));

        // set options
        $this->setOption($setting);
        $this->sw->set($this->setting);

        // bind method for swoole server
	    $this->chkConfig();
        $this->bind();

        // start server
        $this->sw->start();
    }

    protected function chkConfig(){
	    $protocol = $this->getProtocol();
        $protocol->server = $this;
        $protocol->chkConfig();
    }

    protected function bind(){
        $binds = [
            'onServerStart' => 'ManagerStart',
            'onServerStop' => 'ManagerStop',
            ];
        foreach($binds as $method => $evt){
            $this->sw->on($evt, array($this, $method));
        }

        $protocol = $this->getProtocol();
        $binds = [
            'onServerStart' => 'ManagerStart',
            'onServerStop' => 'ManagerStop',

            'onWorkerStart' => 'WorkerStart',
            'onWorkerStop' => 'WorkerStop',

            'onConnect' => 'Connect',
            'onReceive' => 'Receive',
            'onClose' => 'Close',
            'onRequest' => 'request',
            ];
        foreach($binds as $method => $evt){
            if(method_exists($protocol, $method)){
                $this->sw->on($evt, array($protocol, $method));
            }
        }
    }

    function onServerStart($serv){
        $this->log("Server start on {$this->host}:{$this->port}, pid {$serv->master_pid}");
        if (!empty($this->setting['pid_file'])){
            file_put_contents($this->setting['pid_file'], $serv->master_pid);
        }
    }

    function onServerStop(){
        $this->log("Server stop");
        if (!empty($this->setting['pid_file'])) {
            unlink($this->setting['pid_file']);
        }
    }


    private $currentRequest = [];

    /**
     * @param $req
     * @param Response $response
     */
    public function setCurrentRequest($req, $response){
        $this->currentRequest = [
            'request' => $req,
            'response' => $response,
        ];
    }

	/**
	 * catch error
	 */
	function handleFatal(){
		if($log = Debug::traceError()) {
			$this->log($log, "error");
		}

		if(isset($this->currentRequest['response'])){
            $this->currentRequest['response']->end("PHP Parse error:" . $log);
        }
	}

	/**
	 * @param $callback
	 */
	function setDispatcher($callback){
		$this->getProtocol()->setProcess($callback);
	}

	/**
	 * @param $plug
	 * @param $forProtocol
	 * @return $this
	 */
	public function addPlug($plug, $forProtocol = true){
		if($forProtocol){
            $dispatcher = $this->getProtocol();
		} else {
            $dispatcher = $this->getProtocol()->getProcessor();
		}

        if($dispatcher instanceof InterfaceEventDispatcher){
            $dispatcher->attaches($plug);
        }
		return $this;
	}
}