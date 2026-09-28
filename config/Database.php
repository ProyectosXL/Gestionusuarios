<?php
namespace GestionUsuarios\Config;

use Exception;

class Database {
    private static $instance = null;
    private $conn = null;
    private $env = [];

    private function __construct() {
        $candidatos = [];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidatos[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/.env';
            $candidatos[] = dirname($_SERVER['DOCUMENT_ROOT']) . '/.env';
        }
        $candidatos[] = __DIR__ . '/../../.env';
        $candidatos[] = 'W:/.env';
        $candidatos[] = 'D:/htdocs/.env';
        $candidatos[] = 'C:/xampp/htdocs/.env';

        $envFound = false;
        foreach ($candidatos as $path) {
            if (file_exists($path) && is_readable($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
                    list($k, $v) = explode('=', $line, 2);
                    $this->env[trim($k)] = trim(trim($v), '"\'');
                }
                $envFound = true;
                break;
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getEnv($key, $default = null) {
        if (isset($this->env[$key]) && $this->env[$key] !== '') {
            return $this->env[$key];
        }
        $envVal = getenv($key);
        if ($envVal !== false && $envVal !== '') {
            return $envVal;
        }
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public function connectApps() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        $host = $this->getEnv('HOST_APPS', 'XL-APPS');
        $db   = $this->getEnv('DATABASE_APPS', 'sistemas');
        $user = $this->getEnv('USER', 'sa');
        $pass = $this->getEnv('PASS', 'Axoft1988');

        $connectionInfo = [
            'Database' => $db,
            'UID' => $user,
            'PWD' => $pass,
            'CharacterSet' => 'UTF-8'
        ];

        $this->conn = sqlsrv_connect($host, $connectionInfo);
        if (!$this->conn) {
            $err = print_r(sqlsrv_errors(), true);
            error_log('Database::connectApps error: ' . $err);
            throw new Exception("Error al conectar con la base de datos de sistemas en $host ($db): $err");
        }
        return $this->conn;
    }
}
