<?php
function loadEnv($path)
{
    if (!file_exists($path)) {
        return "The .env file does not exist.";
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $loadedVars = [];

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove optional quotes
        $value = trim($value, '"\'');

        $_ENV[$key] = $value;
        putenv("$key=$value");

        $loadedVars[] = "$key => $value";
    }

    return implode("\n", $loadedVars);
}
