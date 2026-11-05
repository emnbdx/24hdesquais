<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::$pdo = self::connect(true);

        return self::$pdo;
    }

    public static function serverPdo(): PDO
    {
        return self::connect(false);
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = self::run($sql, $params)->fetchAll();

        return $rows;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    private static function connect(bool $withDatabase): PDO
    {
        $user = (string) ($_ENV['DB_USER'] ?? '');
        $pass = (string) ($_ENV['DB_PASSWORD'] ?? '');
        if ($user === '' || str_contains($user, ';')) {
            throw new RuntimeException('DB_USER invalide.');
        }

        $name = (string) ($_ENV['DB_NAME'] ?? '');
        if ($withDatabase && !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('DB_NAME invalide.');
        }

        $host = (string) ($_ENV['DB_HOST'] ?? '');
        $port = (int) ($_ENV['DB_PORT'] ?? 3306);
        if ($host === '' || str_contains($host, ';') || str_contains($host, "\0") || $port < 1) {
            throw new RuntimeException('DB_HOST invalide.');
        }
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';

        if ($withDatabase) {
            $dsn .= ';dbname=' . $name;
        }

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
