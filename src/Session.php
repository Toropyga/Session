<?php
/**
 * Класс для работы с сессиями в PHP
 * @author Yuri Frantsevich (FYN)
 * Date: 24/05/2005
 * @version 2.0.3
 * @copyright 2005-2021
 */

namespace FYN;

use FYN\Base;

class Session {

    /**
     * Идентификатор сессии
     * @var mixed
     */
    private $sid;

    /**
     * Имя сессии
     * @var string
     */
    private $session_name = 'cms';

    /**
     * Время "жизни" простой (гостевой) сессии (сек.)
     * @var int
     */
    private $session_live_time = 3600;

    /**
     * Время "жизни" сохранённой сессии (сек.)
     * @var int
     */
    private $session_live_time_rem = 2592000;

    /**
     * Использовать БД
     *
     * @var bool
     */
    private $usedb = true;

    /**
     * Тип БД
     * (временно поддерживает только mysql)
     * @var int
     */
    private $db_type = 'mysql';

    /**
     * Имя таблицы в БД для хранения сессии
     * @var string
     */
    private $table_name = 'sessions';

    /**
     * SQL запросы на создание таблицы
     * @var array
     */
    private $tables = array();

    /**
     * Подключение к БД
     * @var object
     */
    private $DB;

    /**
     * Имя директории для хранения сессии
     * @var string
     */
    private $tmp_dir = 'cookie';

    /**
     * Использовать имя сервера
     * @var bool
     */
    private $use_server_name  = true;

    /**
     * Использовать стандартную папку для хранения сессий
     * @var bool
     */
    private $use_session_dir = false;

    /**
     * Создать специальную папку для хранения сессионных файлов
     * @var bool
     */
    private $use_tmpl = false;

    /**
     * Признак инициации сессии
     * @var bool
     */
    private $se_init = false;

    /**
     * Логи
     * @var array
     */
    private $logs = array();

    /**
     * Отладочные логи
     */
    private $debug = false;

    /**
     * Имя файла в который сохраняется лог
     * @var string
     */
    private $log_file = 'session.log';

    /**
     * Параметр безопасности для COOKIE
     * @var bool
     */
    private $secure = true;

    /**
     * Параметр безопасности для COOKIE
     * @var bool
     */
    private $http_only = true;

    /**
     * Порядок кроссдоменной передачи куки
     *
     * @var string
     */
    private $samesite = 'lax';

    /**
     * Session constructor.
     */
    public function __construct() {
        if (defined("SE_LIVETIME")) $this->session_live_time = SE_LIVETIME;
        if (defined("SE_LIVETIME_REM")) $this->session_live_time_rem = SE_LIVETIME_REM;
        if (defined("SE_NAME") && SE_NAME) $this->session_name = SE_NAME;
        if (defined("SE_USEDB")) $this->usedb = SE_USEDB;
        if (defined('SE_LOG_NAME')) $this->log_file = SE_LOG_NAME;
        if (defined("SE_SECURE")) $this->secure = SE_SECURE;
        if (defined("SE_HTTPONLY")) $this->http_only = SE_HTTPONLY;
        if (defined('SE_SAMESITE')) $this->samesite = SE_SAMESITE;
        if (defined("SE_USE_TMPL")) $this->use_tmpl = SE_USE_TMPL;
        if (!defined("SEPARATOR")) {
            $separator = getenv("COMSPEC")? '\\' : '/';
            define("SEPARATOR", $separator);
        }
        if ($this->debug) $this->logs[] = "Session's Class constructed";
        if ($this->usedb) {
            if ($this->debug) $this->logs[] = 'The Session uses Database';
            if (defined('DefMySQL') && DefMySQL) $this->db_type = 'mysql';
            elseif (defined('DefPostGre') && DefPostGre) $this->db_type = 'postgre';
            else $this->usedb = false;
            if (!defined("TB_SESSION")) define("TB_SESSION", $this->table_name);
            $this->tables = array(
                'mysql'     => "CREATE TABLE `".TB_SESSION."` ( `sid` varchar(100) NOT NULL default '', `user_id` varchar(40) NOT NULL default '', `user_ip` char(20) NOT NULL default '0', `session_start` int(11) NOT NULL default '0', `session_end` int(11) NOT NULL default '0', `session_last` int(11) NOT NULL default '0', `session_data` longtext NOT NULL, PRIMARY KEY  (`sid`)) ENGINE=InnoDB CHARACTER SET `utf8` COLLATE `utf8_general_ci`;",
                'postgre'   => "CREATE TABLE ".TB_SESSION." ( sid varchar(100) NOT NULL, user_id varchar(40) DEFAULT 0 NOT NULL, user_ip varchar(20) DEFAULT 0 NOT NULL, session_start numeric(11,0) DEFAULT 0 NOT NULL, session_end numeric(11,0) DEFAULT 0 NOT NULL, session_last numeric(11,0) DEFAULT 0 NOT NULL, session_data text NOT NULL ) WITH OIDS;"
            );
        }
        elseif ($this->debug) $this->logs[] = 'The Session does not use Database';
        if ($this->use_tmpl) {
            if (defined("SE_TMPL_NAME")) $this->tmp_dir = SE_TMPL_NAME;
            if (defined("SE_USE_SERVER_NAME")) $this->use_server_name = SE_USE_SERVER_NAME;
            if (defined("SE_USE_SDIR")) $this->use_session_dir = SE_USE_SDIR;

            if (!$this->tmp_dir) $this->tmp_dir = sys_get_temp_dir();
            else {
                if (!is_dir($this->tmp_dir)) {
                    @mkdir($this->tmp_dir, 0777);
                }
                @chmod($this->tmp_dir, 0777);
            }
            if ($this->use_server_name) {
                $this->tmp_dir = $this->tmp_dir.SEPARATOR.$_SERVER['SERVER_NAME'];
                if (!is_dir($this->tmp_dir)) {
                    @mkdir($this->tmp_dir, 0777);
                    @chmod($this->tmp_dir, 0777);
                }
            }
            if ($this->use_session_dir) {
                $session_save_path = $this->tmp_dir.SEPARATOR.'sessions';
                if (!is_dir($session_save_path)) {
                    @mkdir($session_save_path, 0777);
                    @chmod($session_save_path, 0777);
                }
            }
            else $session_save_path = $this->tmp_dir;
        }
        else $session_save_path = session_save_path();
        if ($this->debug) $this->logs[] = "Session's save path: ".$session_save_path;
        if (!defined('SESSION_PATH')) define('SESSION_PATH', $session_save_path);
        if (defined("USE_PROTOCOL") && preg_match("/^https/", USE_PROTOCOL)) {
            $this->secure = true;
            $this->http_only = true;
        }
        return true;
    }

    /**
     * Сохранение данных по завершению работы скрипта
     */
    public function __destruct(){
        $this->setSession();
        if ($this->debug) $this->logs[] = "Session's Class destructed";
    }

    /**
     * Инициализация сессии
     */
    public function sessionInit () {
        if ($this->debug) $this->logs[] = "Session INIT";
        if ($this->usedb && $this->db_type) {
            if ($this->db_type == 'mysql') $this->DB = new FYN\DB\MySQL();
            elseif ($this->db_type == 'postgre') $this->DB = new FYN\DB\PDO_LIB('pgsql'); // PostGre
            $tables = $this->DB->getTableList();
            if (!in_array(TB_SESSION, $tables)) {
                if (!$this->DB->query($this->tables[$this->db_type])) {
                    $this->logs[] = 'Session INIT Error: No session table!';
                    $this->usedb = false;
                }
            }
        }
        $this->se_init = true;
        $this->getSession();
    }

    /**
     * Старт сессии
     */
    private function getSession () {
        if ($this->debug) $this->logs[] = "Get Session's Data: Start";
        $domain = ($_SERVER['SERVER_NAME'] != 'localhost' && preg_match("/\./", $_SERVER['SERVER_NAME']))?$_SERVER['SERVER_NAME']:"localhost";
        if (preg_match("/\s/", $domain)) $domain = preg_replace("/\s/", '', $domain);
        if ($this->debug) $this->logs[] = "Session domain: ".$domain;
        session_save_path(SESSION_PATH);
        if ($this->debug) $this->logs[] = "Session path: ".SESSION_PATH;
        session_name($this->session_name);
        if ($this->debug) $this->logs[] = "Session name: ".$this->session_name;
        if ($this->debug) $this->logs[] = "Data from cookie: ".preg_replace("/\n/", '', print_r($_COOKIE, true));
        if (!session_id()) {
            if (isset($_COOKIE[$this->session_name]) && $_COOKIE[$this->session_name]) {
                $this->sid = $_COOKIE[$this->session_name];
                if ($this->debug) $this->logs[] = 'Session ID get from $_COOKIE: '.$this->sid;
            }
            else $this->sid = $this->getSessionID();
            if (preg_match("/(\'|\"|=|\(|\)|\s|\W|\.)/", $this->sid) || strlen($this->sid) < 32) $this->sid = $this->getSessionID();
            session_id($this->sid);
            $session = array();
            if ($this->usedb) $session = $this->getSessionData();
            if (isset($session['remember']) && $session['remember']) $live_time = $this->session_live_time_rem;
            else $live_time = $this->session_live_time;
            $options = array('lifetime'=>(time()+$live_time), 'path'=>'/', 'domain'=>$domain, 'secure'=>$this->secure, 'httponly'=>$this->http_only, 'samesite'=>$this->samesite);
            session_set_cookie_params($options);
            session_start();
            $cookie_param = array('expires'=>(time()+$live_time), 'path'=>'/', 'domain'=>$domain, 'secure'=>$this->secure, 'httponly'=>$this->http_only, 'samesite'=>$this->samesite);
            setcookie($this->session_name, $this->sid, $cookie_param);
            //setcookie($this->session_name, $this->sid, (time()+$live_time), '/', $domain, $this->secure, $this->http_only);
            if ($this->usedb) $_SESSION = $session;
        }
        else $this->sid = session_id();
        if ($this->debug) $this->logs[] = 'Session ID: '.$this->sid;

        header("Expires: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        /**
         * Регитрация функции, сохраняющей данные по окончанию работы скрипта
         * (старый подход)
         */
        //register_shutdown_function(array($this, 'setSession'));
        if ($this->debug) $this->logs[] = "Get Session's Data: Stop";
    }

    /**
     * Генерация идентификатора сессии
     * @return string
     */
    private function getSessionID () {
        $id = date('YdmHis');
        list($u_sec, $sec) = explode(" ", microtime());
        unset($sec);
        $u_sec = $u_sec * 10000;
        $u_sec = sprintf ('%04d', $u_sec);
        $id .= $u_sec;
        $rand = rand(0,99999999);
        $rand = sprintf('%08d', $rand);
        $id .= $rand;
        $sid = Base::getKeyHash($id, 'md5');
        if ($this->debug) $this->logs[] = 'Generate new Session ID: '.$sid;
        return $sid;
    }

    /**
     * Получение данных сессии из БД
     * @return mixed
     */
    private function getSessionData () {
        if ($this->debug) $this->logs[] = "Load Session's Data form Database";
        $sql = "DELETE FROM ".TB_SESSION." WHERE session_end < UNIX_TIMESTAMP()";
        $this->DB->query($sql);
        $sql = "SELECT * FROM ".TB_SESSION." WHERE sid = '$this->sid'";
        $ses = $this->DB->getResults($sql, 2);
        if ($this->debug) $this->logs[] = "Data from Database: ".preg_replace("/\n/", '', print_r($ses, true));
        if (isset($ses['session_data']) && $ses['session_data']) $ses['session_data'] = stripslashes($ses['session_data']);
        else $ses['session_data'] = '';
        $res = unserialize($ses['session_data']);
        if (isset($ses['user_id']) && $ses['user_id']) $res['user_id'] = $ses['user_id'];
        else $res['user_id'] = '';
        $ip = Base::getIP();
        if (isset($res['proxy']) && $res['proxy'] != $ip['proxy']) {
            $res['proxy_old'] = $res['proxy'];
            $res['proxy'] = $ip['proxy'];
        }
        elseif (!isset($res['proxy'])) $res['proxy'] = $ip['proxy'];
        if (isset($res['ip']) && $res['ip'] != $ip['ip']) {
            $res['ip_old'] = $res['ip'];
            $res['ip'] = $ip['ip'];
        }
        elseif (!isset($res['ip'])) $res['ip'] = $ip['ip'];
        if ($this->debug) $this->logs[] = "Session's Data from Database: ".preg_replace("/\n/", '', print_r($res, true));
        return $res;
    }

    /**
     * Сохранение данных сессии в БД
     * @return bool
     */
    public function setSession () {
        if (!$this->se_init) {
            if ($this->debug) $this->logs[] = 'Set Session Error: Session not started!';
            return false;
        }
        $sid = $this->sid;
        if (!$sid) {
            if ($this->debug) $this->logs[] = 'Set Session Error: Session ID not found!';
            return false;
        }
        if ($this->usedb) {
            if ($this->debug) $this->logs[] = "Save Session's Data to Database";
            $sql = "SELECT COUNT(sid) FROM " . TB_SESSION . " WHERE sid = '$sid'";
            $cn = $this->DB->getResults($sql, 1);
            $data = array();
            if (isset($_SESSION['user_id'])) $data['user_id'] = $_SESSION['user_id'];
            else $data['user_id'] = '0';
            $ip = Base::getIP();
            $data['user_ip'] = sprintf("%u", ip2long($ip['ip']));
            if (isset($_SESSION['remember']) && $_SESSION['remember']) $data['session_end'] = time() + $this->session_live_time_rem;
            else $data['session_end'] = time() + $this->session_live_time;
            $data['session_last'] = time();
            $data['session_data'] = addslashes(serialize($_SESSION));
            if ($cn) {
                $index['sid'] = $sid;
                $sql = $this->DB->getUpdateSQL(TB_SESSION, $data, $index);
            }
            else {
                $data['session_start'] = time();
                $data['sid'] = $sid;
                $sql = $this->DB->getInsertSQL(TB_SESSION, $data);
            }
            $this->DB->query($sql);
            if ($this->debug) $this->logs[] = "Saved Session's Data: ".preg_replace("/\n/", '', print_r($data, true));
        }
        return true;
    }

    /**
     * Возвращает логи
     * @return array
     */
    public function getLogs () {
        $return['log'] = $this->logs;
        $return['file'] = $this->log_file;
        return $return;
    }

}