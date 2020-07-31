<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Swoole extends Command
{
    public $ws;

    protected $signature = 'swoole {action?}';

    protected $description = 'swoole';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $action = $this->argument('action');

        switch ($action)
        {
            case 'restart':
                $this->info('swoole server restart');
                break;
            case 'close':
                $this->info('swoole server stop');
                break;
            default:
                $this->info('swoole server start');
                $this->start();
                break;
        }

        return true;
    }

    public function start()
    {
        //创建websocket服务器对象，监听0.0.0.0:9501端口
        $this->ws = new Swoole\WebSocket\Server('0.0.0.0', 9501);

        //开启ssl模式
        //$this->ws = new \swoole_websocket_server("0.0.0.0", 9501,SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);

        //配置ssl模式
        //$this->ws->set([
        //'ssl_key_file' => storage_path('cert/apiclient_key.pem'),
        //'ssl_cert_file' => storage_path('cert/apiclient_cert.pem'),
        //]);

        //监听WebSocket连接打开事件
        $this->ws->on('open', [$this, 'open']);

        //监听WebSocket消息事件
        $this->ws->on('message', [$this, 'message']);

        //监听WebSocket主动推送消息事件
        $this->ws->on('request', [$this, 'request']);

        //监听WebSocket连接关闭事件
        $this->ws->on('close', [$this, 'close']);

        $this->ws->start();
    }

    /**
     * 建立连接
     * @param $ws
     * @param $request
     */
    public function open($ws, $request)
    {
        var_dump($request->fd . '连接成功');
    }

    /**
     * 接收消息
     * @param $ws
     * @param $frame
     */
    public function message($ws, $frame)
    {
        var_dump('发送成功');
    }

    /**
     * 接收请求
     * @param $request
     * @param $response
     */
    public function request($request, $response)
    {

    }

    /**
     * 关闭连接
     * @param $ws
     * @param $fd
     */
    public function close($ws, $fd)
    {

    }
}
