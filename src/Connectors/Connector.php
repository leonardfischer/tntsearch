<?php

namespace TeamTNT\TNTSearch\Connectors;

use PDO;

class Connector
{
    /**
     * The default PDO connection options.
     *
     * @var array
     */
    protected array $options = [
        PDO::ATTR_CASE                     => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS             => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES        => false,
        PDO::ATTR_EMULATE_PREPARES         => false,
    ];

    /**
     * Get the PDO options based on the configuration.
     *
     * @param  array  $config
     * @return array
     */
    public function getOptions(array $config)
    {
        return $this->options;
    }

    /**
     * Create a new PDO connection.
     *
     * @param  string  $dsn
     * @param  array   $config
     * @param  array   $options
     * @return \PDO
     */
    public function createConnection(string $dsn, array $config, array $options)
    {
        extract($config, EXTR_SKIP);

        if (!array_key_exists('username', $config)) {
            $username = null;
        }

        if (!array_key_exists('password', $config)) {
            $password = null;
        }

        return new PDO($dsn, $username, $password, $options);

    }

    /**
     * Get the default PDO connection options.
     *
     * @return array
     */
    public function getDefaultOptions()
    {
        return $this->options;
    }

    /**
     * Set the default PDO connection options.
     *
     * @param  array  $options
     * @return void
     */
    public function setDefaultOptions(array $options)
    {
        $this->options = $options;
    }
}
