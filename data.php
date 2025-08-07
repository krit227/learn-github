<?php include 'functions.php';
header('Content-Type: application/json');

echo json_encode([
  'teams' => getTeams(),
  'matches' => getMatches()
]);