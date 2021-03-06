<?php

namespace MySQLReplication\BinLog;

/**
 * Class BinLogServerInfo
 * @package MySQLReplication\BinLog
 */
class BinLogServerInfo
{
    const MYSQL_VERSION_MARIADB = 'MariaDB';
    const MYSQL_VERSION_PERCONA = 'Percona';
    const MYSQL_VERSION_GENERIC = 'MySQL';
    /**
     * @var array
     */
    private static $serverInfo = [];

    /**
     * @param string $data
     * @param string $version
     */
    public static function parsePackage($data, $version)
    {
        $i = 0;
        $length = strlen($data);
        self::$serverInfo['protocol_version'] = ord($data[$i]);
        $i++;

        //version
        self::$serverInfo['server_version'] = '';
        $start = $i;
        for ($i = $start; $i < $length; $i++) {
            if ($data[$i] === chr(0)) {
                $i++;
                break;
            }
            self::$serverInfo['server_version'] .= $data[$i];
        }

        //connection_id 4 bytes
        self::$serverInfo['connection_id'] = unpack('I', $data[$i] . $data[++$i] . $data[++$i] . $data[++$i])[1];
        $i++;

        //auth_plugin_data_part_1
        //[len=8] first 8 bytes of the auth-plugin data
        self::$serverInfo['salt'] = '';
        for ($j = $i; $j < $i + 8; $j++) {
            self::$serverInfo['salt'] .= $data[$j];
        }
        $i += 8;

        //filler_1 (1) -- 0x00
        $i++;

        //capability_flag_1 (2) -- lower 2 bytes of the Protocol::CapabilityFlags (optional)
        $i += 2;

        //character_set (1) -- default server character-set, only the lower 8-bits Protocol::CharacterSet (optional)
        self::$serverInfo['character_set'] = $data[$i];
        $i++;

        //status_flags (2) -- Protocol::StatusFlags (optional)
        $i += 2;

        //capability_flags_2 (2) -- upper 2 bytes of the Protocol::CapabilityFlags
        $i += 2;

        //auth_plugin_data_len (1) -- length of the combined auth_plugin_data, if auth_plugin_data_len is > 0
        $salt_len = ord($data[$i]);
        $i++;

        $salt_len = max(12, $salt_len - 9);

        $i += 10;

        //next salt
        if ($length >= $i + $salt_len) {
            for ($j = $i; $j < $i + $salt_len; $j++) {
                self::$serverInfo['salt'] .= $data[$j];
            }

        }
        self::$serverInfo['auth_plugin_name'] = '';
        $i += $salt_len + 1;
        for ($j = $i; $j < $length - 1; $j++) {
            self::$serverInfo['auth_plugin_name'] .= $data[$j];
        }

        self::$serverInfo['version_name'] = self::parseVersion($version);
    }

    /**
     * @return string
     */
    public static function getSalt()
    {
        return self::$serverInfo['salt'];
    }

    /**
     * @see http://stackoverflow.com/questions/37317869/determine-if-mysql-or-percona-or-mariadb
     * @param string $version
     * @return string
     */
    private static function parseVersion($version)
    {
        if ('' !== $version) {
            if (false !== strpos($version, self::MYSQL_VERSION_MARIADB)) {
                return self::MYSQL_VERSION_MARIADB;
            }
            if (false !== strpos($version, self::MYSQL_VERSION_PERCONA)) {
                return self::MYSQL_VERSION_PERCONA;
            }
        }

        return self::MYSQL_VERSION_GENERIC;
    }

    /**
     * @return string
     */
    public static function getVersion()
    {
        return self::$serverInfo['version_name'];
    }

    /**
     * @return bool
     */
    public static function isMariaDb()
    {
        return self::MYSQL_VERSION_MARIADB === self::getVersion();
    }

    /**
     * @return bool
     */
    public static function isPercona()
    {
        return self::MYSQL_VERSION_PERCONA === self::getVersion();
    }

    /**
     * @return bool
     */
    public static function isGeneric()
    {
        return self::MYSQL_VERSION_GENERIC === self::getVersion();
    }
}