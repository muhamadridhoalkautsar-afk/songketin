<?php
$host = "localhost";
$user = "root";
$pass = "0907";
$db = "songketin";
$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi){
    die("Koneksi gagal: ". mysqli_connect_error());
}
?>