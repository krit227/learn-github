<?php
$SUPABASE_URL = 'https://ftgxmmlrurafstcdcgkt.supabase.co';
$SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZ0Z3htbWxydXJhZnN0Y2RjZ2t0Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTQ0MjM1NTksImV4cCI6MjA2OTk5OTU1OX0.LoL-HsPPf8ywc1RujrvFzqN_onfPdzWJmfAsbY0G9oI';

function supabaseRequest($method, $endpoint, $data = null, $query = '') {
  global $SUPABASE_URL, $SUPABASE_KEY;
  $curl = curl_init();
  $headers = [
    "apikey: $SUPABASE_KEY",
    "Authorization: Bearer $SUPABASE_KEY",
    "Content-Type: application/json"
  ];
  $url = "$SUPABASE_URL/rest/v1/$endpoint$query";
  curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
  ]);
  if ($data) {
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
  }
  $response = curl_exec($curl);
  curl_close($curl);
  return json_decode($response, true);
}
