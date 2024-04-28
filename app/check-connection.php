<?php
require_once 'local-config.php';

$db = new mysqli(AROPA_DB_HOST, AROPA_DB_USER, AROPA_DB_PASSWORD, AROPA_DB_DATABASE);
if( ! $db )
  echo "<h1>Unable to connect to the database</h1>";
else
  echo "<h1>The database connection is ok</h1>";

