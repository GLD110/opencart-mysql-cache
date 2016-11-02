
<?php

/**
 * OpenCart Ukrainian Community
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License, Version 3
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/copyleft/gpl.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email

 *
 * @category   OpenCart
 * @package    OCU MySQL Caching
 * @copyright  Copyright (c) 2011 created by UncleAndy, maintained by Eugene Lifescale for OpenCart Ukrainian Community (http://opencart-ukraine.tumblr.com)
 * @license    http://www.gnu.org/copyleft/gpl.html     GNU General Public License, Version 3
 */
namespace DB;
use Cache;
final class MySQL_Cached {
    private $connection;
    private $cache;
    private $cachedquery;

    public function __construct($hostname, $username, $password, $database) {
        $this->cache = new Cache(DB_CACHED_EXPIRE);

        if (!$this->connection = mysql_pconnect($hostname, $username, $password)) {
            exit('Error: Could not make a database connection using ' . $username . '@' . $hostname);

        }

        if (!mysql_select_db($database, $this->connection)) {
            exit('Error: Could not connect to database ' . $database);
        }

        mysql_query("SET NAMES 'utf8'", $this->connection);
        mysql_query("SET CHARACTER SET utf8", $this->connection);
        mysql_query("SET CHARACTER_SET_CONNECTION=utf8", $this->connection);
        mysql_query("SET SQL_MODE = ''", $this->connection);
    }

    public function query($sql) {
        // Кэшируем только SELECT запросы (ключь = md5hash всего запроса, в переменной сапоминаем точный текст запроса для точного сравнения)
        // При кэшировании результат последнего запроса запоминаем в $this->cachedquery (для функции countAffected)
        // При кэшировании запроса в запросе указываем время кэширования
        // В специальной глобальной переменной держим дату последнего сброса кэша. При этом если у извлеченного значения время записи меньше указанного времени зброса - кэшь считается неактуальным
        $isselect = 0;
        $md5query = '';
        $pos = stripos($sql, 'select ');
        if ($pos == 0)
        {
            $isselect = 1;
            // Это select
            $md5query = md5($sql);
            if ($query = $this->cache->get('sql_' . $md5query))
            {
                if ($query->sql == $sql)
                {
                    // Проверяем флаг сброса
                    if ($resetflag = $this->cache->get('sql_globalresetcache'))
                    {
                        // Если время сброса раньше чем время текущего запроса - все нормально
                        if ($resetflag <= $query->time)
                        {
                            $this->cachedquery = $query;
                            return($query);
                        };
                    }
                    else
                    {
                        $this->cachedquery = $query;
                        return($query);
                    };
                };
            };
        };



        $resource = mysql_query($sql, $this->connection);

        if ($resource) {
            if (is_resource($resource)) {
                $i = 0;

                $data = array();

                while ($result = mysql_fetch_assoc($resource)) {
                    $data[$i] = $result;

                    $i++;
                }

                mysql_free_result($resource);

                $query = new \stdClass();
                $query->row = isset($data[0]) ? $data[0] : array();
                $query->rows = $data;
                $query->num_rows = $i;

                unset($data);

                if ($isselect == 1)
                {
                    $query->sql = $sql;
                    $query->time = time();

                    $this->cache->set('sql_' . $md5query, $query);
                };
                unset($this->cachedquery);

                return $query;
            } else {
                return TRUE;
            }
        } else {
            exit('Error: ' . mysql_error($this->connection) . '<br />Error No: ' . mysql_errno($this->connection) . '<br />' . $sql);
        }
    }

    public function escape($value) {
        return mysql_real_escape_string($value, $this->connection);
    }

    public function countAffected() {
        if (isset($this->cachedquery) && $this->cachedquery)
        {
            return $this->cachedquery->num_rows;
        }
        else
        {
            return mysql_affected_rows($this->connection);
        }
    }

    public function getLastId() {
        return mysql_insert_id($this->connection);
    }

    public function __destruct() {
        mysql_close($this->connection);
    }
}
