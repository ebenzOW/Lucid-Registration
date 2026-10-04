<?php
  require_once 'db_login.php';
  $conn = new mysqli($hostname, $username, $password, $db);
  
  if ($conn->connect_error){
  $conn = new mysqli($hostname, $username, $password) ;

    if ($conn->connect_error){
      die("Connection failed" . $conn->connect_error);
    }

  $conn->query("CREATE DATABASE IF NOT EXISTS $db");
  $conn->select_db($db);
  
  }
?>