<?php
include 'functions.php';
include_once 'supabase.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');

  if (isset($_POST['add_team'])) {
    addTeam($_POST['team_name']);
    echo json_encode(['status' => 'ok']);
    exit;
  }

  if (isset($_POST['delete_team'])) {
    deleteTeam($_POST['team_id']);
    echo json_encode(['status' => 'ok']);
    exit;
  }

  if (isset($_POST['update_team'])) {
    updateTeam($_POST['team_id'], ['team_name' => $_POST['new_name']]);
    echo json_encode(['status' => 'ok']);
    exit;
  }

  if (isset($_POST['run_match'])) {
    runMatch();
    echo json_encode(['status' => 'ok']);
    exit;
  }

  if (isset($_POST['record_result'])) {
    recordMatchResult($_POST['match_id'], $_POST['winner_id']);
    echo json_encode(['status' => 'ok']);
    exit;
  }
}

  if (isset($_POST['clear_teams'])) {
    clearTeams();
    echo json_encode(['status' => 'ok']); exit;
  }

  if (isset($_GET['stats'])) {
  echo json_encode(supabaseRequest('GET', 'win_stats', null, '?select=*'));
  exit;
}

if (isset($_POST['delete_match'])) {
  $match_id = $_POST['match_id'];
  deleteMatch($match_id);
  echo json_encode(['status' => 'ok']);
  exit;
}


// สำหรับโหลดข้อมูลผ่าน JS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  header('Content-Type: application/json');
  echo json_encode([
    'teams' => getTeams(),
    'matches' => getMatches()
  ]);
  exit;
}
?>
