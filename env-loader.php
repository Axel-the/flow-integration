<?php
/**
 * Carga variables de entorno desde un archivo .env local si existe.
 * Útil para desarrollo local sin depender de dependencias externas.
 */
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Ignorar comentarios o líneas vacías
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Buscar el primer signo '='
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // Limpiar comillas iniciales y finales en el valor
        $value = trim($value, '"\'');

        // Definir la variable de entorno si no está previamente configurada en el sistema
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Intentar cargar el archivo .env local
loadEnv(__DIR__ . '/.env');
