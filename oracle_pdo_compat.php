<?php

class OraclePdoCompatStatement
{
    private $connection;
    private $sql;
    private $statement;
    private $bindings = [];

    public function __construct($connection, $sql)
    {
        $this->connection = $connection;
        $this->sql = $sql;
    }

    public function bindValue($parameter, $value, $dataType = null, $length = 0)
    {
        $this->bindings[$this->normalizeParameter($parameter)] = [
            'value' => $value,
            'reference' => false,
            'length' => $this->normalizeLength($value, $length),
        ];

        return true;
    }

    public function bindParam($parameter, &$variable, $dataType = null, $length = 0)
    {
        $this->bindings[$this->normalizeParameter($parameter)] = [
            'value' => &$variable,
            'reference' => true,
            'length' => $this->normalizeLength($variable, $length),
        ];

        return true;
    }

    public function execute($inputParameters = null)
    {
        if (is_array($inputParameters)) {
            foreach ($inputParameters as $parameter => $value) {
                $this->bindValue($parameter, $value);
            }
        }

        $this->statement = oci_parse($this->connection, $this->sql);
        if (!$this->statement) {
            $this->throwLastError('Could not prepare Oracle statement.');
        }

        foreach ($this->bindings as $parameter => &$binding) {
            if ($binding['reference']) {
                $variable =& $binding['value'];
            } else {
                $variable = $binding['value'];
            }

            $length = $binding['length'] > 0 ? $binding['length'] : -1;
            oci_bind_by_name($this->statement, $parameter, $variable, $length);
        }
        unset($binding);

        $executed = oci_execute($this->statement, OCI_COMMIT_ON_SUCCESS);
        if (!$executed) {
            $this->throwLastError('Could not execute Oracle statement.');
        }

        return true;
    }

    public function fetch($mode = null)
    {
        if (!$this->statement) {
            return false;
        }

        if ($mode === PDO::FETCH_COLUMN) {
            $row = oci_fetch_array($this->statement, OCI_NUM + OCI_RETURN_NULLS);
            return ($row === false) ? false : ($row[0] ?? null);
        }

        $fetchMode = OCI_ASSOC + OCI_RETURN_NULLS;
        if ($mode === PDO::FETCH_NUM) {
            $fetchMode = OCI_NUM + OCI_RETURN_NULLS;
        } elseif ($mode === PDO::FETCH_BOTH) {
            $fetchMode = OCI_BOTH + OCI_RETURN_NULLS;
        }

        return oci_fetch_array($this->statement, $fetchMode);
    }

    public function fetchAll($mode = null)
    {
        $rows = [];

        if ($mode === PDO::FETCH_COLUMN) {
            while (($value = $this->fetch(PDO::FETCH_COLUMN)) !== false) {
                $rows[] = $value;
            }

            return $rows;
        }

        while (($row = $this->fetch($mode)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function fetchColumn($columnNumber = 0)
    {
        if (!$this->statement) {
            return false;
        }

        $row = oci_fetch_array($this->statement, OCI_NUM + OCI_RETURN_NULLS);
        if ($row === false) {
            return false;
        }

        return $row[$columnNumber] ?? null;
    }

    public function rowCount()
    {
        return $this->statement ? oci_num_rows($this->statement) : 0;
    }

    public function closeCursor()
    {
        if ($this->statement) {
            oci_free_statement($this->statement);
            $this->statement = null;
        }

        return true;
    }

    public function __destruct()
    {
        $this->closeCursor();
    }

    private function normalizeParameter($parameter)
    {
        return ($parameter[0] === ':') ? $parameter : ':' . $parameter;
    }

    private function normalizeLength($value, $length)
    {
        if ($length > 0) {
            return $length;
        }

        if (is_string($value)) {
            return max(strlen($value), 1);
        }

        return $length;
    }

    private function throwLastError($fallbackMessage)
    {
        $error = oci_error($this->statement ?: $this->connection);
        $message = $fallbackMessage;

        if ($error && isset($error['message'])) {
            $message .= ' ' . trim($error['message']);
        }

        throw new PDOException($message);
    }
}

class OraclePdoCompat
{
    private $connection;

    public function __construct($dsn, $username, $password, array $options = [])
    {
        if (!function_exists('oci_connect')) {
            throw new PDOException('OCI8 extension is not loaded.');
        }

        $parsedDsn = $this->parseDsn($dsn);
        $this->connection = oci_connect($username, $password, $parsedDsn['dbname'], $parsedDsn['charset']);

        if (!$this->connection) {
            $this->throwLastError('Connection failed.');
        }
    }

    public function setAttribute($attribute, $value)
    {
        return true;
    }

    public function query($sql)
    {
        $statement = $this->prepare($sql);
        $statement->execute();
        return $statement;
    }

    public function prepare($sql)
    {
        return new OraclePdoCompatStatement($this->connection, $sql);
    }

    public function exec($sql)
    {
        $statement = oci_parse($this->connection, $sql);
        if (!$statement) {
            $this->throwLastError('Could not prepare Oracle statement.');
        }

        $executed = oci_execute($statement, OCI_COMMIT_ON_SUCCESS);
        if (!$executed) {
            $this->throwLastError('Could not execute Oracle statement.');
        }

        $rows = oci_num_rows($statement);
        oci_free_statement($statement);

        return $rows;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    private function parseDsn($dsn)
    {
        $result = [
            'dbname' => '//localhost:1521/XE',
            'charset' => null,
        ];

        if (preg_match('/dbname=([^;]+)(?:;charset=([^;]+))?/i', $dsn, $matches)) {
            $result['dbname'] = $matches[1];
            if (!empty($matches[2])) {
                $result['charset'] = $matches[2];
            }
        }

        return $result;
    }

    private function throwLastError($fallbackMessage)
    {
        $error = oci_error($this->connection);
        $message = $fallbackMessage;

        if ($error && isset($error['message'])) {
            $message .= ' ' . trim($error['message']);
        }

        throw new PDOException($message);
    }
}