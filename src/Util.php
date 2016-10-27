<?php
/**
 * ark.database
 * @copyright 2014-2016 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace Ark\Database;

class Util {
    const MYSQL_GONE_AWAY = 'MySQL server has gone away';
    const MYSQL_REFUSED = 'Connection refused';
    /**
     * Check whether we need to reconnect database
     * @param  string|null $errorCode
     * @param  array|null $errorInfo
     * @param  \Exception|null $exception
     * @return boolean
     */
    public static function checkReconnectError($errorCode, $errorInfo, $exception) {
        if ($exception) {
            if (stripos($exception->getMessage(), self::MYSQL_GONE_AWAY) !== false || stripos($exception->getMessage(), self::MYSQL_REFUSED) !== false) {
                return true;
            }
        } elseif ($errorInfo) {
            if (stripos($errorInfo[2], self::MYSQL_GONE_AWAY) !== false || stripos($errorInfo[2], self::MYSQL_REFUSED) !== false) {
                return true;
            }
        }

        return false;
    }
}