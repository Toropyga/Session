<?php
declare(strict_types=1);

/**
 * A class for working with sessions in PHP
 * @author Yuri Frantsevich
 * Date: 24/05/2005
 * @version 3.1.1
 * @copyright 2005-2026
 *
 * Changelog 3.1.0:
 * - Session ID is now generated using `random_bytes()` (CSPRNG) instead of `time()+rand()`
 * - Cookie session ID validation has been tightened (hex only, fixed length)
 * - SQL queries are no longer built via simple raw string concatenation without format checking
 * - Session data `unserialize()` is now executed with `allowed_classes = false` (protection against PHP Object Injection)
 * - Session directory permissions have been restricted from 0777 to 0750
 * - Removed duplicate `Set-Cookie` headers (`session_start()` and `setMyCookie()` were duplicating each other)
 * - Added type hints and return types
 * - `db_type` can now be overridden via the `SE_DB_TYPE` constant
 * - "no-cache" headers can now be disabled via the `SE_NO_CACHE_HEADERS` constant
 */

namespace Toropyga;

use Toropyga\Base;
use Toropyga\DB;

class Session {

    /**
     * Session identifier
     * @var string
     */
    private string $sid = '';

    /**
     * Session name
     * @var string
     */
    private string $session_name = 'cms';

    /**
     * Lifetime of a regular (guest) session (sec.)
     * @var int
     */
    private int $session_live_time = 3600;

    /**
     * Lifetime of a "remembered" session (sec.)
     * @var int
     */
    private int $session_live_time_rem = 2592000;

    /**
     * Whether to use the DB
     * @var bool
     */
    private bool $usedb = true;

    /**
     * DB type ('mysql' or 'postgre'; postgre support is experimental)
     * @var string
     */
    private string $db_type = 'mysql';

    /**
     * Name of the DB table used to store sessions
     * @var string
     */
    private string $table_name = 'sessions';

    /**
     * SQL queries for creating the table
     * @var array
     */
    private array $tables = [];

    /**
     * DB connection
     * @var object|null
     */
    private $DB = null;

    /**
     * Name of the directory used to store session files
     * @var string
     */
    private string $tmp_dir = 'cookie';

    /**
     * Whether to use the server name
     * @var bool
     */
    private bool $use_server_name = true;

    /**
     * Whether to use the default folder for storing sessions
     * @var bool
     */
    private bool $use_session_dir = false;

    /**
     * Whether to create a dedicated folder for storing session files
     * @var bool
     */
    private bool $use_tmpl = false;

    /**
     * Flag indicating whether the session has been initiated
     * @var bool
     */
    private bool $se_init = false;

    /**
     * Logs
     * @var array
     */
    private array $logs = [];

    /**
     * Debug logs
     * @var bool
     */
    private bool $debug = false;

    /**
     * Name of the file the log is saved to
     * @var string
     */
    private string $log_file = 'session.log';

    /**
     * Security parameter for the COOKIE
     * @var bool
     */
    private bool $secure = true;

    /**
     * Security parameter for the COOKIE
     * @var bool
     */
    private bool $http_only = true;

    /**
     * Cross-domain cookie transfer policy
     * @var string
     */
    private string $samesite = 'lax';

    /**
     * If true, session is invalidated (session_data reset) when the
     * client IP changes mid-session. Off by default for backward
     * compatibility with proxies / mobile networks that rotate IPs.
     * Enable via constant SE_STRICT_IP.
     * @var bool
     */
    private bool $strict_ip = false;

    /**
     * Whether to send Cache-Control / Expires / Pragma "no-cache" headers.
     * Can be turned off via constant SE_NO_CACHE_HEADERS = false.
     * @var bool
     */
    private bool $send_no_cache_headers = true;

    /**
     * Regex that a valid session ID must match: hex string, 32-128 chars.
     */
    private const SID_PATTERN = '/^[a-f0-9]{32,128}$/i';

    /**
     * Session constructor.
     */
    public function __construct() {
        if (defined('SE_LIVETIME')) $this->session_live_time = (int) SE_LIVETIME;
        if (defined('SE_LIVETIME_REM')) $this->session_live_time_rem = (int) SE_LIVETIME_REM;
        if (defined('SE_NAME') && SE_NAME) $this->session_name = (string) SE_NAME;
        if (defined('SE_USEDB')) $this->usedb = (bool) SE_USEDB;
        if (defined('SE_DB_TYPE')) $this->db_type = (string) SE_DB_TYPE;
        if (defined('SE_LOG_NAME')) $this->log_file = (string) SE_LOG_NAME;
        if (defined('SE_SECURE')) $this->secure = (bool) SE_SECURE;
        if (defined('SE_HTTPONLY')) $this->http_only = (bool) SE_HTTPONLY;
        if (defined('SE_SAMESITE')) $this->samesite = (string) SE_SAMESITE;
        if (defined('SE_USE_TMPL')) $this->use_tmpl = (bool) SE_USE_TMPL;
        if (defined('SE_DEBUG')) $this->debug = (bool) SE_DEBUG;
        if (defined('SE_STRICT_IP')) $this->strict_ip = (bool) SE_STRICT_IP;
        if (defined('SE_NO_CACHE_HEADERS')) $this->send_no_cache_headers = (bool) SE_NO_CACHE_HEADERS;

        if (!defined('SEPARATOR')) {
            $separator = getenv('COMSPEC') ? '\\' : '/';
            define('SEPARATOR', $separator);
        }

        if ($this->debug) $this->logs[] = "Session's Class constructed";

        if ($this->usedb) {
            if ($this->debug) $this->logs[] = 'The Session uses Database';
            if (!defined('TB_SESSION')) define('TB_SESSION', $this->table_name);
            $this->tables = [
                'mysql'   => "CREATE TABLE `" . TB_SESSION . "` ( `sid` varchar(100) NOT NULL default '', `user_id` varchar(40) NOT NULL default '', `user_ip` char(20) NOT NULL default '0', `session_start` int(11) NOT NULL default '0', `session_end` int(11) NOT NULL default '0', `session_last` int(11) NOT NULL default '0', `session_data` longtext NOT NULL, PRIMARY KEY  (`sid`)) ENGINE=InnoDB CHARACTER SET `utf8` COLLATE `utf8_general_ci`;",
                'postgre' => "CREATE TABLE " . TB_SESSION . " ( sid varchar(100) NOT NULL, user_id varchar(40) DEFAULT 0 NOT NULL, user_ip varchar(20) DEFAULT 0 NOT NULL, session_start numeric(11,0) DEFAULT 0 NOT NULL, session_end numeric(11,0) DEFAULT 0 NOT NULL, session_last numeric(11,0) DEFAULT 0 NOT NULL, session_data text NOT NULL ) WITH OIDS;",
            ];
        } 
        elseif ($this->debug) {
            $this->logs[] = 'The Session does not use Database';
        }

        if ($this->use_tmpl) {
            if (defined('SE_TMPL_NAME')) $this->tmp_dir = (string) SE_TMPL_NAME;
            if (defined('SE_USE_SERVER_NAME')) $this->use_server_name = (bool) SE_USE_SERVER_NAME;
            if (defined('SE_USE_SDIR')) $this->use_session_dir = (bool) SE_USE_SDIR;

            if (!$this->tmp_dir) {
                $this->tmp_dir = sys_get_temp_dir();
            } 
            else {
                $this->ensureDir($this->tmp_dir);
            }
            if ($this->use_server_name) {
                $server_name = $this->safeServerName();
                $this->tmp_dir = $this->tmp_dir . SEPARATOR . $server_name;
                $this->ensureDir($this->tmp_dir);
            }
            if ($this->use_session_dir) {
                $session_save_path = $this->tmp_dir . SEPARATOR . 'sessions';
                $this->ensureDir($session_save_path);
            }
            else {
                $session_save_path = $this->tmp_dir;
            }
        } 
        else {
            $session_save_path = session_save_path();
        }

        if ($this->debug) $this->logs[] = "Session's save path: " . $session_save_path;
        if (!defined('SESSION_PATH')) define('SESSION_PATH', $session_save_path);

        if (defined('USE_PROTOCOL') && preg_match('/^https/', USE_PROTOCOL)) {
            $this->secure = true;
            $this->http_only = true;
        }
    }

    /**
     * Save data when the script finishes running
     */
    public function __destruct() {
        $this->setSession();
        if ($this->debug) $this->logs[] = "Session's Class destructed";
    }

    /**
     * Create a directory with restrictive permissions if it doesn't exist yet.
     * (Previously 0777 world-writable; now 0750.)
     */
    private function ensureDir(string $path): void {
        if (!is_dir($path)) {
            if (!@mkdir($path, 0750, true) && !is_dir($path)) {
                if ($this->debug) $this->logs[] = "Failed to create directory: $path";
                return;
            }
        }
        @chmod($path, 0750);
    }

    /**
     * Returns a sanitized version of $_SERVER['SERVER_NAME'], safe to use
     * as part of a filesystem path and free of whitespace.
     */
    private function safeServerName(): string {
        $name = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $name = preg_replace('/[^a-zA-Z0-9.\-]/', '', $name);
        return $name !== '' ? $name : 'localhost';
    }

    /**
     * Returns the domain to use for the session cookie.
     */
    private function cookieDomain(): string {
        $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $domain = ($server_name !== 'localhost' && preg_match('/\./', $server_name)) ? $server_name : 'localhost';
        $domain = preg_replace('/\s/', '', $domain);
        return $domain;
    }

    /**
     * Validate that a string is an acceptable session ID: hex characters
     * only, within a sane length range. This is the safeguard that makes
     * building SQL with $this->sid via concatenation safe - a value that
     * doesn't match this pattern is never used in a query.
     */
    private function isValidSid(string $sid): bool {
        return (bool) preg_match(self::SID_PATTERN, $sid);
    }

    /**
     * Session initialization
     */
    public function sessionInit(): void {
        if ($this->debug) $this->logs[] = 'Session INIT';
        if ($this->usedb && $this->db_type) {
            if ($this->db_type === 'mysql') $this->DB = new DB\MySQL();
            elseif ($this->db_type === 'postgre') $this->DB = new DB\PDO_LIB('pgsql'); // PostGre (experimental)

            if ($this->DB !== null) {
                $tables = $this->DB->getTableList();
                if (!in_array(TB_SESSION, $tables, true)) {
                    if (!$this->DB->query($this->tables[$this->db_type])) {
                        $this->logs[] = 'Session INIT Error: No session table!';
                        $this->usedb = false;
                    }
                } 
                else {
                    $this->logs[] = 'Session table is available!';
                }
            }
        }
        $this->se_init = true;
        $this->getSession();
    }

    /**
     * Start the session
     */
    private function getSession(): void {
        if ($this->debug) $this->logs[] = "Get Session's Data: Start";

        $domain = $this->cookieDomain();
        if ($this->debug) $this->logs[] = 'Session domain: ' . $domain;

        session_save_path(SESSION_PATH);
        if ($this->debug) $this->logs[] = 'Session path: ' . SESSION_PATH;

        session_name($this->session_name);
        if ($this->debug) $this->logs[] = 'Session name: ' . $this->session_name;
        if ($this->debug) $this->logs[] = 'Data from cookie: ' . preg_replace('/\n/', '', print_r($_COOKIE, true));

        if (!session_id()) {
            $candidate = '';
            if (isset($_COOKIE[$this->session_name]) && $_COOKIE[$this->session_name]) {
                $candidate = (string) $_COOKIE[$this->session_name];
                if ($this->debug) $this->logs[] = 'Session ID candidate from $_COOKIE: ' . $candidate;
            }

            // Only accept the client-supplied ID if it strictly matches our
            // expected format. Anything else (including empty) gets a
            // freshly generated, CSPRNG-based ID.
            $this->sid = $this->isValidSid($candidate) ? $candidate : $this->getSessionID();

            session_id($this->sid);

            $session = [];
            if ($this->usedb) $session = $this->getSessionData();

            $live_time = (!empty($session['remember'])) ? $this->session_live_time_rem : $this->session_live_time;

            $options = [
                'lifetime' => time() + $live_time,
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => $this->secure,
                'httponly' => $this->http_only,
                'samesite' => $this->samesite,
            ];
            session_set_cookie_params($options);
            session_start();
            // Note: session_start() above already sends the Set-Cookie header
            // for us using the params set above, so we do NOT call
            // setMyCookie() here as well (the previous version sent two
            // separate Set-Cookie headers for the same cookie name).

            if ($this->usedb) $_SESSION = $session;
        } 
        else {
            $this->sid = (string) session_id();
        }

        if ($this->debug) $this->logs[] = 'Session ID: ' . $this->sid;

        if ($this->send_no_cache_headers) {
            header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
        }

        if ($this->debug) $this->logs[] = "Get Session's Data: Stop";
    }

    /**
     * Generate a new, cryptographically random session identifier.
     * @return string
     */
    private function getSessionID(): string {
        try {
            $entropy = bin2hex(random_bytes(32));
        } 
        catch (\Throwable $e) {
            // Should not happen on any modern PHP build, but keep a fallback
            // so session creation never hard-fails.
            $entropy = hash('sha256', uniqid((string) mt_rand(), true));
        }

        $sid = (class_exists(Base::class) && method_exists(Base::class, 'getKeyHash'))
            ? Base::getKeyHash($entropy, 'hash')
            : $entropy;

        // Guard against a hashing helper that returns something outside our
        // expected format (e.g. containing non-hex characters); fall back to
        // the raw entropy in that case.
        if (!$this->isValidSid($sid)) $sid = $entropy;

        if ($this->debug) $this->logs[] = 'Generate new Session ID: ' . $sid;
        return $sid;
    }

    /**
     * Retrieve session data from the DB
     * @return array
     */
    private function getSessionData(): array {
        if ($this->debug) $this->logs[] = "Load Session's Data from Database";

        // Housekeeping: drop expired sessions.
        $this->DB->query('DELETE FROM ' . TB_SESSION . ' WHERE session_end < UNIX_TIMESTAMP()');

        // $this->sid is only ever set from getSessionID() (our own CSPRNG
        // output) or from a cookie value that passed isValidSid() (hex only,
        // fixed length range) - so it cannot contain quotes or SQL
        // metacharacters. Still, if your DB wrapper supports bound
        // parameters, prefer that over concatenation everywhere below.
        if (!$this->isValidSid($this->sid)) {
            if ($this->debug) $this->logs[] = 'getSessionData aborted: invalid sid format';
            return $this->emptySessionData();
        }

        $sql = "SELECT * FROM " . TB_SESSION . " WHERE sid = '" . $this->sid . "'";
        $ses = $this->DB->getResults($sql, 2);

        if ($this->debug) $this->logs[] = 'Data from Database: ' . preg_replace('/\n/', '', print_r($ses, true));

        if (!is_array($ses)) $ses = [];

        if (!empty($ses['session_data'])) {
            $raw = stripcslashes($ses['session_data']);
            $raw = stripslashes($raw);
            // allowed_classes = false prevents PHP Object Injection: any
            // serialized object in the payload is deserialized as
            // __PHP_Incomplete_Class instead of being instantiated.
            $res = @unserialize($raw, ['allowed_classes' => false]);
            if (!is_array($res)) $res = [];
        } 
        else {
            $res = [];
        }

        $res['user_id'] = (!empty($ses['user_id'])) ? $ses['user_id'] : '';

        $ip = Base::getIP();

        if (isset($res['proxy']) && $res['proxy'] !== $ip['proxy']) {
            $res['proxy_old'] = $res['proxy'];
            $res['proxy'] = $ip['proxy'];
        } 
        elseif (!isset($res['proxy'])) {
            $res['proxy'] = $ip['proxy'];
        }

        if (isset($res['ip']) && $res['ip'] !== $ip['ip']) {
            if ($this->strict_ip) {
                // Optional hardening: treat an IP change mid-session as
                // suspicious and reset session state rather than silently
                // continuing with it (default OFF - many legitimate users
                // change IP due to mobile networks / proxies / CDNs).
                if ($this->debug) $this->logs[] = 'Strict IP check failed - resetting session data';
                $res = $this->emptySessionData();
                $res['ip'] = $ip['ip'];
                $res['proxy'] = $ip['proxy'];
                return $res;
            }
            $res['ip_old'] = $res['ip'];
            $res['ip'] = $ip['ip'];
        } 
        elseif (!isset($res['ip'])) {
            $res['ip'] = $ip['ip'];
        }

        if ($this->debug) $this->logs[] = "Session's Data from Database: " . preg_replace('/\n/', '', print_r($res, true));
        return $res;
    }

    /**
     * Returns a blank session-data array with just a user_id placeholder.
     */
    private function emptySessionData(): array {
        return ['user_id' => ''];
    }

    /**
     * Save session data to the DB
     * @return bool
     */
    public function setSession(): bool {
        if (!$this->se_init) {
            if ($this->debug) $this->logs[] = 'Set Session Error: Session not started!';
            return false;
        }
        $sid = $this->sid;
        if (!$sid || !$this->isValidSid($sid)) {
            if ($this->debug) $this->logs[] = 'Set Session Error: Session ID not found or invalid!';
            return false;
        }

        if ($this->usedb && $this->DB !== null) {
            if ($this->debug) $this->logs[] = "Save Session's Data to Database";

            $sql = "SELECT COUNT(sid) FROM " . TB_SESSION . " WHERE sid = '" . $sid . "'";
            $cn = $this->DB->getResults($sql, 1);

            $data = [];
            $data['user_id'] = $_SESSION['user_id'] ?? '0';

            $ip = Base::getIP();
            $data['user_ip'] = sprintf('%u', ip2long($ip['ip']));

            $data['session_end'] = time() + ((!empty($_SESSION['remember'])) ? $this->session_live_time_rem : $this->session_live_time);
            $data['session_last'] = time();
            $data['session_data'] = serialize($_SESSION);

            if ($cn) {
                $index = ['sid' => $sid];
                $sql = $this->DB->getUpdateSQL(TB_SESSION, $data, $index);
            } else {
                $data['session_start'] = time();
                $data['sid'] = $sid;
                $sql = $this->DB->getInsertSQL(TB_SESSION, $data);
            }
            $this->DB->query($sql);

            if ($this->debug) $this->logs[] = "Saved Session's Data: " . preg_replace('/\n/', '', print_r($data, true));
        }
        return true;
    }

    /**
     * Set the debug flag and logging
     * @param bool $debug
     */
    public function setDebug(bool $debug = false): void {
        $this->debug = $debug;
    }

    /**
     * Returns the logs
     * @return array
     */
    public function getLogs(): array {
        return [
            'log'  => $this->logs,
            'file' => $this->log_file,
        ];
    }

    /**
     * Set a cookie manually. Note: under normal operation getSession()
     * already sets the session cookie via session_start(); this method is
     * a public utility for cases where you need to (re)issue a cookie
     * explicitly, e.g. extending a "remember me" lifetime outside of the
     * regular session bootstrap.
     *
     * @param string $name - cookie name
     * @param string $value - cookie value
     * @param int $live_time - lifetime in seconds
     * @param string $domain - domain the cookie is set for
     * @param bool|null $secure
     * @param bool|null $http_only
     * @param string|null $samesite
     * @return bool
     */
    public function setMyCookie(
        string $name = '',
        string $value = '',
        int $live_time = 0,
        string $domain = '',
        ?bool $secure = null,
        ?bool $http_only = null,
        ?string $samesite = null
    ): bool {
        if (!$live_time) $live_time = $this->session_live_time;
        $expires = time() + $live_time;

        if (!$domain) $domain = $this->cookieDomain();

        $secure    = $secure    ?? $this->secure;
        $http_only = $http_only ?? $this->http_only;
        $samesite  = $samesite  ?? $this->samesite;

        $cookie_param = [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $http_only,
            'samesite' => $samesite,
        ];

        return setcookie($name, $value !== '' ? $value : $this->sid, $cookie_param);
    }

}
