<?php

class Database {
    private static ?PDO $instance = null;

    private static array $config = [
        'host'     => 'db',
        'dbname'   => 'betting_db',
        'user'     => 'betting_user',
        'password' => 'betting_pass',
        'charset'  => 'utf8mb4',
    ];

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::$config['host'],
                self::$config['dbname'],
                self::$config['charset']
            );
            self::$instance = new PDO($dsn, self::$config['user'], self::$config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }
}
