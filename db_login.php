<?php
// Automatically route to the Windows host if running inside the Docker container
if (file_exists('/.dockerenv')) {
    $hostname = 'host.docker.internal';
} else {
    $hostname = 'localhost';
}

$username = 'root';
$password = '';
$db       = 'lucid_university';
?>