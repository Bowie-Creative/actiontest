<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Authentication
{
    protected static $instance = null;

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!is_null(static::$instance)) {
            return static::$instance;
        }

        $class_name = AutoUpdater_Loader::loadClass('Authentication');

        static::$instance = new $class_name();

        return static::$instance;
    }

    /**
     * @param array $payload
     *
     * @return bool
     *
     * @throws Exception
     */
    public function validate($payload)
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : null;
        if (empty($_GET['wpe_timestamp']) || $_GET['wpe_timestamp'] < (time() - 30)) {
            AutoUpdater_Log::error(sprintf('Invalid timestamp. Received %s request to %s', strtoupper($method), AutoUpdater_Request::getCurrentUrl()));
            throw new Exception('Invalid timestamp', 403);
        }

        $signature = $this->getSignature($payload);
        if (!$signature || !hash_equals($_GET['wpe_signature'], $signature)) {
            AutoUpdater_Log::error(sprintf('Invalid signature. Received %s request to %s', strtoupper($method), AutoUpdater_Request::getCurrentUrl()));
            throw new Exception('Invalid signature', 403);
        }

        return true;
    }

    /**
     * @param array  $payload
     *
     * @return false|string
     */
    public function getSignature($payload = array())
    {
        $token = AutoUpdater_Config::get('worker_token');

        $message = '';
        foreach ($payload as $key => $value) {
            $message .= $key . $value;
        }

        return hash_hmac('sha256', $message, $token);
    }

    /**
     * @return bool
     */
    public function logInAsAdmin()
    {
        $users = get_users(array('role' => 'administrator', 'number' => 1));
        if (!empty($users[0]->ID)) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
            wp_set_current_user($users[0]->ID);
        }

        return is_user_logged_in();
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($str1, $str2)
    {
        if (strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;
            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }

            return !$ret;
        }
    }
}
