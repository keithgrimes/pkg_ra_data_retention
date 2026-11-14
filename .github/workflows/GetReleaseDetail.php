<?php
try {
    $xml=simplexml_load_file($argv[1]) or die("Error: Cannot create object");
    $env_file = getenv('GITHUB_ENV');
    file_put_contents($env_file, "{$argv[3]}=v{$xml->{$argv[2]}}\n", FILE_APPEND | LOCK_EX);
} catch (Exception $e) {
    file_put_contents($env_file, "{$argv[3]}=v0.0.0\n", FILE_APPEND | LOCK_EX);
}
?>  