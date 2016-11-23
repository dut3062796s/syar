## Ϊ����swoole��ʵ�� Yar server
*   ��ʷ����ʹ����yar, ��������޸Ŀͻ��˴���
*   ����Yar�����ִ��Ч��
*   ѧϰswoole, yar(�ڴ˸�лlaruence,rango��swoole�����Ŷ�)

## Require
*   php5.4+
*   ext-swoole 1.8.8+ 
*   ext-msgpack ���yarʹ��msgpack���뷽ʽ


## Example
**�����**
example\server.php
~~~
use syar\Server;
use j\log\File as FileLog;
use j\log\Log;

$vendorPath = __Your vendor path__;
/** @var \Composer\Autoload\ClassLoader $loader */
$loader = include($vendorPath . "/autoload.php");
$loader->addPsr4('syar\\example\\service\\', __DIR__ . '/service');

$server = new Server('0.0.0.0', '5604');
$server->setLogger(new Log());
$service = new \syar\example\service\Test();
$server->setDispatcher(function(\syar\Token $token, $isDocument) use ($service){
    if(!$isDocument){
        $method = $token->getMethod();
        $params = $token->getArgs();
        $value = call_user_func_array(array($service, $method), $params);
    } else {
        $value = "Yar api document";
    }
    return $value;
});

$server->run(['max_request' => 10000]);
~~~

example/service/Test.php

~~~
namespace syar\example\service;

/**
 * Class Test
 * @package syar\example\service
 */
class Test {
	public function getName($userName){
		return $userName . " Hello";
	}

	public function getAge(){
		return 20;
	}
}
~~~

����������server.php 
~~~
#php server.php
~~~

**�ͻ���**
~~~
$url = "http://127.0.0.1:5604/test";
$client = new Yar_client($url);
$name = $client->getName("tester");
$age = $client->getAge();

//
echo "<pre>\n";
var_dump($name);
var_dump($age);
~~~

## ��չ����

### �ӿ���������
*   ��������Ľӿ�,�����ʹ�ö��������̲���ִ��
*   �����ַ http://{your_server_address}/multiple
*   ���÷����� function calls($requests);
    $requests������ʽ [����1����, ����2����, ...], 
    �������ݸ�ʽ��['api' => ApiName, 'method' => MethodName, 'params' => []]
*   �����ӿ�ִ�д���, ����˼�¼������־, ����['code' => CODE, 'error' => ERROR MESSAGE]��ʽ����, �ͻ������д���

�ͻ�������ʾ����
~~~
#example/client_mul.php
$vendorPath = ...;
$loader = include($vendorPath . "/autoload.php");

$url = "http://127.0.0.1:5604/multiple";
$client = new Yar_client($url);

$calls = [
	'age' => [
		'api' => '/test',
		'method' => 'getAge',
		'params' => []
	    ],
	'name' => [
		'api' => '/test',
		'method' => 'getName',
		'params' => [rand(1, 245301)]
	]
];
$rs = $client->calls($calls);

var_dump($rs);
~~~

### Ͷ������task�����첽ִ��

�ο� 
*   TaskMananger->regTask()
*   TaskMananger->doTask()
*   TaskMananger->doTasks()
*   TaskMananger->doTasksAsync()

## ��֪����
1.  δ����ĵ������� ��ʹ���Դ���yar server��ʾ�ĵ�
1.  ���ڴ����Ǵ�˽�п�ܶ������������ܴ���δ֪bug