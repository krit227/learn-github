<?php
$host = "aws-0-ap-southeast-1.pooler.supabase.com";
$port = "6543";
$dbname = "postgres";
$user = "postgres.ftgxmmlrurafstcdcgkt";
$password = "AAzz2546";

$connStr = "host=$host port=$port dbname=$dbname user=$user password=$password";
$dbconn = pg_connect($connStr);

if (!$dbconn) {
    echo "Connection failed.";
} else {
    echo "Connected successfully.";
}
?>