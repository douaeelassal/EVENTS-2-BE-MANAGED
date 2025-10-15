<?php
/**
 * EVENT2 - Database Connection Class
 * ================================
 *
 * Singleton pattern implementation for database connections.
 * Provides centralized database access with proper error handling.
 *
 * Features:
 * - Singleton pattern (single instance)
 * - PDO with proper configuration
 * - Error logging and handling
 * - UTF-8 character set support
 */

declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    /**
     * Get database instance using Singleton pattern
     *
     * @return PDO Database connection instance
     * @throws Exception If connection fails
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Erreur de connexion à la base de données");
            }
        }
        return self::$instance;
    }
}
?>
