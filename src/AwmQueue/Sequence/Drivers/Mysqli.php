<?php

/**
 * Copyright 2017 Awm Team
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace AwmQueue\Sequence\Drivers;

final class Mysqli extends \AwmQueue\Sequence\AbstractSequence
{
    
    /**
     * Get number of data in data storage
     *
     * @return int
     */
    public function count()
    {
        $stmt = $this->_query('SELECT COUNT(`id`) FROM `' . $this->_config['table'] . '`');
        if (!$stmt['status']) {
            return 0;
        }
        
        $row = $stmt['stmt']->fetch_row();
        if (!empty($row[0])) {
            $row = (int) $row[0];
            $stmt['stmt']->close();
        } else {
            $row = 0;
        }
        
        return $row;
    }
    
    /**
     * Read all data from data storage
     *
     * @param int $limit - optional
     * 
     * @return mixed
     */
    public function fetchAll($limit = false)
    {
        $limit = (int) $limit;
        $stmt  = $this->_query('SELECT * FROM `' . $this->_config['table'] . '` ORDER BY `priority` DESC, `id` ASC' . ($limit > 0 ? ' LIMIT ' . $limit : ''));
        if (!$stmt['status']) {
            return [];
        }
        
        $rows = [];
        while ($row = $stmt['stmt']->fetch_assoc()) {
            $row['params'] = unserialize($row['params']);
            $rows[] = $row;
        }
        $stmt['stmt']->close();
        
        return $rows;
    }
    
    /**
     * Read data from data storage
     *
     * @param string $key
     * 
     * @return mixed
     */
    public function fetch($key)
    {
        $stmt = $this->_query('SELECT * FROM `' . $this->_config['table'] . '` WHERE `id` = :id LIMIT 1', [':id' => $key]);
        if (!$stmt['status']) {
            return false;
        }
        
        $row = $stmt['stmt']->fetch_assoc();
        if (!empty($row)) {
            $row['params'] = unserialize($row['params']);
            $stmt['stmt']->close();
        }
        
        return $row;
    }
    
    /**
     * Store data from data storage
     *
     * @param array $data
     * @param string $key - optional
     * 
     * @return mixed
     */
    public function store(array $data, $key = null)
    {
        if (empty($data) || empty($data['task']) || empty($data['priority']) || empty($data['params']) || empty($data['source'])) {
            return false;
        }
        
        $data['params'] = serialize($data['params']);
        
        $time  = microtime(true);
        $micro = sprintf('%06d', ($time - floor($time)) * 1000000);
        $date  = new \DateTime(date('Y-m-d H:i:s.' . $micro, $time));
        
        $data['date'] = $date->format('Y-m-d H:i:s.u');
        
        $bind = [];
        if ($key !== null && $this->fetch($key)) {
            $id = $key;
            foreach ($data as $k => $v) {
                $bind[sprintf('`%1$s` = :%1$s', $k)] = $v;
            }
            $query = sprintf(
                'UPDATE `%s` SET %s WHERE `id` = %u',
                $this->_config['table'],
                join(', ', array_keys($bind)),
                $key
            );
        } else {
            $id = false;
            foreach ($data as $k => $v) {
                $bind[':' . $k] = $v;
            }
            $query = sprintf(
                'INSERT INTO `%s` (`%s`) VALUES (%s)',
                $this->_config['table'],
                join('`, `', array_keys($data)),
                join(', ', array_keys($bind))
            );
        }
        
        $stmt = $this->_query($query, $bind);
        if (!$stmt['status']) {
            return false;
        }
        
        if (!$id) {
            $id = $this->_getConnection()->_connection->insert_id;
        }
        
        return $id;
    }
    
    /**
     * Remove data from data storage
     *
     * @param string $key
     * 
     * @return mixed
     */
    public function remove($key)
    {
        return $this->_query('DELETE FROM `' . $this->_config['table'] . '` WHERE `id` = :id LIMIT 1', [':id' => $key])['status'];
    }
    
    /**
     * Remove all data from data storage
     *
     * @return mixed
     */
    public function removeAll()
    {
        return $this->_query('TRUNCATE TABLE `' . $this->_config['table'] . '`')['status'];
    }
    
    /**
     * Escape value
     *
     * @param string $value
     * 
     * @return mixed
     */
    protected function _escape($value)
    {
        return $this->_getConnection()->_connection->real_escape_string($value);
    }
    
    /**
     * Execute query
     *
     * @param string $query
     * 
     * @return mixed
     */
    protected function _query($query, array $bind = null)
    {
        $queryDebug = $query;
        if ($bind) {
            $bindEscape = [];
            foreach ($bind as $key => $val) {
                $bindEscape[$key] = '"' . $this->_escape($val) . '"';
            }
            $query = strtr($query, $bindEscape);
        }
        
        $stmt = $this->_getConnection()->_connection->query($query);
        $res  = $stmt ? $stmt : false;
        
        if (!empty($this->_config['debug'])) {
            $this->_debug(
                sprintf(
                    '[STATUS => "%s"%s] %s%s',
                    !empty($res) ? 'OK' : 'ERROR',
                    !empty($res) ? '' : ' ERROR: "' . $this->_connection->error . '"',
                    $queryDebug,
                    "\n\nExecuted SQL =>\n{$query}" . (!empty($bind) ? "\n\nParams =>\n" . var_export($bind, true) : '')
                )
            );
        }
        
        return ['status' => $res, 'stmt' => $stmt];
    }
    
    /**
     * Get connection to data storage
     * 
     * @return \AwmQueue\Sequence\AbstractSequence
     */
    protected function _getConnection()
    {
        if (!($this->_connection instanceof \mysqli)) {
            try {
                if (!extension_loaded('mysqli')) {
                    throw new ExceptionSequence('Extension "mysqli" is not loaded');
                }
                $this->_connection = new \mysqli(
                    $this->_config['host'],
                    $this->_config['user'],
                    $this->_config['password'],
                    $this->_config['database'],
                    !empty($this->_config['port']) ? $this->_config['port'] : null
                );
                if ($this->_connection->connect_error) {
                    throw new ExceptionSequence('Error: ' . $this->_connection->connect_error . ' ErrorNo: ' . $this->_connection->connect_errno);
                }
                
                if (!$this->_connection->set_charset('utf8')) {
                    throw new ExceptionSequence('Set names failed: ' . ($this->_connection->error ? $this->_connection->error : 'Unknown error'));
                }
            } catch (ExceptionSequence $e) {
                throw new ExceptionSequence('Connect failed: ' . $e->getMessage());
            }
        }
        
        return $this;
    }
    
    /**
     * Close connection to data storage
     * 
     * @return \AwmQueue\Sequence\AbstractSequence
     */
    protected function _closeConnection()
    {
        if ($this->_connection instanceof \mysqli) {
            $this->_connection->close();
            $this->_connection = null;
        }
        
        return $this;
    }
    
}
