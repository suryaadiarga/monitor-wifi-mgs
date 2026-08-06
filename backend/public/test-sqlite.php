<?php
header('Content-Type: text/plain');
echo 'pdo_sqlite=' . (extension_loaded('pdo_sqlite') ? 'yes' : 'no') . "\n";
echo 'sqlite3=' . (extension_loaded('sqlite3') ? 'yes' : 'no') . "\n";
echo 'PDO drivers=' . implode(',', PDO::getAvailableDrivers()) . "\n";
?>