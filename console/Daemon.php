<?php
/**
 * 监控守护程序
 * @category   H2O
 * @package    console
 * @author     Xujinzhang <xjz1688@163.com>
 * @version    0.1.0
 * 配置说明:
    $config['daemon'] = [
        'phpbin'   =>  '/usr/bin/php',
        'route'    =>  [
            'Dtest.index', //进程测试
            'Ddock.index'
        ]
    ];
 */
namespace H2O\console;
use H2O,H2O\helpers\File;
class Daemon
{
    /**
     * @var string 日志路径
     */
    private $_logpath = '';
    /**
     * 子进程命令行前缀
     */
    private $_daemonson = '';
    /**
     * 构造函数 初始化
     */
    public function __construct()
    {
        $this->_logpath = APP_RUNTIME.DS.'console'.DS; //日志目录
        $this->_daemonson = self::CmdBin(); //返回标准备的命令行
    }
    /**
     * 读取当前运行环境的命令行信息
     */
    public static function CmdBin()
    {
        $adaemons = \H2O::getAppConfigs('daemon');
        if(isset($adaemons['phpbin'])){//设置php环境变量路径
            $phpbin = trim($adaemons['phpbin']);
        }else{
            $handle = popen('which php', 'r');
            $bin = fread($handle, 2096);
            pclose($handle);
            $phpbin = empty($bin)?'/usr/bin/php':trim($bin);
        }
        return $phpbin.' '.realpath($GLOBALS['argv'][0]).' ';
    }

    /**
     * 主进程监控部分
     */
    public function actRun()
    {
        // 监控自身进程同时只允许运行一个实例
        $this->_checkSelfProc();
        //启动记录
        File::write(
            $this->_logpath.'daemon'.DS.'daemon.log',//记录日志信息 按天记录
            'time:'.date('Y-m-d H:i:s').',pid'.intval(getmypid()).PHP_EOL //写入信息
        );//写入日志信息
        while(true){
            //根据队列配置启动队列
            $this->_startProc();
            sleep(1);
        }
    }
    /**
     * 根据队列配置启动队列 只支持单个子进程任务
     */
    private function _startProc()
    {
        $adaemons = \H2O::getAppConfigs('daemon');
        $tasks = $this->_getTasksName();
        foreach($adaemons['route'] as $route)
        {
            $tmpdir = $this->_logpath.'daemon'.DS.strtolower($route); //当前任务日志目录
            file::createDirectory($tmpdir); //如果目录不存在,则创建
            $_logFile = $tmpdir.DS.date('Ymd').'.log'; //根据路由规则按天记录
            if(!in_array($route,$tasks)){//每个任务只充许一个进程在执行
                $_cmd = "{$this->_daemonson} @service.daemon --c={$route} >>{$_logFile} 2>&1 &".PHP_EOL;
                $_pp = popen($_cmd, 'r');
                pclose($_pp);
            }
        }
    }
    /**
     * @return array 返回所有正在执行的任务列表
     */
    private function _getTasksName()
    {
        $_cmd = "ps -ef | grep -v 'grep' | grep '@service.daemon' | awk '{print $11}'\n";
        $_pp = popen($_cmd, 'r');
        $res = []; //返回所有正在执行的任务名
        if($_pp){//查看命令行是否有结果
            while(!feof($_pp)) {
                $_line = trim(fgets($_pp));
                $_line = substr($_line,4); //过滤--c=参数
                if(empty($_line)) continue;
                $res[] = $_line;
            }
        }
        pclose($_pp);
        return $res;
    }
    /**
     * 检测自身进程，同时只允许运行一个主进程
     * @return	NULL
     */
    private function _checkSelfProc()
    {
        $_cmd = "ps aux | grep -v 'grep' | grep '{$GLOBALS['argv'][0]} {$GLOBALS['argv'][1]}' | awk '{print $12,$13}'\n"; //查找守护进程执行状态和进程ID
        $_pp = popen($_cmd, 'r');
        $_ptotal = 0; //启动的进程数
        if($_pp){//查看命令行是否有结果
            while(!feof($_pp)) {
                $_line = trim(fgets($_pp));
                if(empty($_line)) continue;
                list($_bin, $_cmd) = explode(' ', $_line);
                if($_bin == $GLOBALS['argv'][0] && $_cmd == $GLOBALS['argv'][1]){
                    $_ptotal++; //当前运行进程加1
                }
            }
        }
        pclose($_pp);
        if($_ptotal>1) exit(); //当前已有守护进程存在,不需要再启动
        return;
    }
    /**
     * 查看当前任务列表
     */
    public function actGetTask()
    {
        $_cmd = "ps -ef | grep -v 'grep' | grep '@service.daemon' | awk '{print $2,$5,$11}'\n";
        $_pp = popen($_cmd, 'r');
        if($_pp){//查看命令行是否有结果
            while(!feof($_pp)) {
                $_line = trim(fgets($_pp));
                if(empty($_line)) continue;
                $exw = explode(' ',$_line);
                echo 'route:'.substr($exw[2],4).'  pid:'.$exw[0].'  starttime:'.$exw[1].' '.PHP_EOL;
            }
        }
        pclose($_pp);
    }
}
?>