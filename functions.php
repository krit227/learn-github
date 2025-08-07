<?php include_once 'supabase.php';

function getTeams() {
  return supabaseRequest('GET', 'teams', null, '?select=*&order=id.asc');
}

function getMatches() {
  return supabaseRequest('GET', 'matches', null, '?select=*&order=match_id.asc');
}

function addTeam($team_name) {
  return supabaseRequest('POST', 'teams', [
    'team_name' => $team_name,
    'wins' => 0,
    'losses' => 0,
    'is_champion' => false,
    'rest_turn' => 0,
    'status' => 'Waiting'
  ]);
}

function getWinStats() {
  return supabaseRequest('GET', 'win_stats', null, '?select=team_id,total_wins,teams!fk_team(team_name)&order=total_wins.desc');
}


function updateTeam($id, $data) {
  return supabaseRequest('PATCH', "teams?id=eq.$id", $data);
}

function deleteTeam($id) {
  return supabaseRequest('DELETE', "teams?id=eq.$id");
}

function runMatch() {
  $teams = getTeams();

  // ✅ Step 1: หาแชมป์ที่พักอยู่ (rest_turn > 0)
  $champion_resting = array_filter($teams, fn($t) =>
    $t['is_champion'] && $t['rest_turn'] > 0
  );

  // ✅ Step 2: ดึงทีมที่ matchable:
  // - status = Waiting และ rest_turn = 0
  // - หรือ status = Playing (กำลังชนะรอบเดียวอยู่)
  // ❌ แต่ตัด Champion ที่ rest_turn > 0 ออก
  $matchable = array_filter($teams, fn($t) =>
    (
      ($t['status'] === 'Waiting' && $t['rest_turn'] == 0) ||
      $t['status'] === 'Playing'
    ) && (!$t['is_champion'] || $t['rest_turn'] == 0)
  );

  // ✅ เรียงตาม ID
  usort($matchable, fn($a, $b) => $a['id'] <=> $b['id']);
  $matchable = array_values($matchable);

  if (count($matchable) < 2) {
    return ['success' => false, 'message' => 'ทีมไม่พอ'];
  }

  // ✅ Step 3: จับคู่แมตช์ใหม่
  $t1 = $matchable[0];
  $t2 = $matchable[1];

  supabaseRequest('POST', 'matches', [
    'team1_id' => $t1['id'],
    'team2_id' => $t2['id'],
    'status' => 'Pending'
  ]);

  updateTeam($t1['id'], ['status' => 'Playing']);
  updateTeam($t2['id'], ['status' => 'Playing']);

  // ✅ Step 4: หลังจับคู่เสร็จ ค่อยลด rest_turn ของแชมป์
  foreach ($champion_resting as $c) {
    $new_rest = $c['rest_turn'] - 1;

    if ($new_rest == 0) {
      updateTeam($c['id'], [
        'rest_turn' => 0,
        'status' => 'Waiting',
        'is_champion' => false
      ]);
    } else {
      updateTeam($c['id'], ['rest_turn' => $new_rest]);
    }
  }

  return ['success' => true, 'match' => [$t1, $t2]];
}

function clearTeams() {
    // ลบ teams
    $teams = supabaseRequest("GET", "teams");
    foreach ($teams as $team) {
      supabaseRequest("DELETE", "teams", null, "?id=eq." . $team['id']);
    }

    // ลบ matches
    $matches = supabaseRequest("GET", "matches");
    foreach ($matches as $match) {
      supabaseRequest("DELETE", "matches", null, "?match_id=eq." . $match['match_id']);
    }

    echo json_encode(["status" => "ok"]);
    exit;
}

function deleteMatch($match_id) {
  $query = "?match_id=eq.$match_id";
  return supabaseRequest('DELETE', 'matches', null, $query);
}



function recordMatchResult($match_id, $winner_id) {
  $matches = getMatches();
  $match = array_filter($matches, fn($m) => $m['match_id'] == $match_id);
  if (!$match) return ['success' => false, 'message' => 'ไม่พบแมตช์'];
  $match = array_values($match)[0];

  $team1 = $match['team1_id'];
  $team2 = $match['team2_id'];
  $loser = ($winner_id == $team1) ? $team2 : $team1;

  // ดึงข้อมูลทีม
  $winner_data = supabaseRequest('GET', "teams", null, "?id=eq.$winner_id")[0];
  $loser_data = supabaseRequest('GET', "teams", null, "?id=eq.$loser")[0];

  $new_wins = $winner_data['wins'] + 1;
  $new_losses = $loser_data['losses'] + 1;

  // ✅ เพิ่มสถิติชนะสะสม
  $stat = supabaseRequest('GET', "win_stats", null, "?team_id=eq.$winner_id");
  if (count($stat) > 0) {
    $current = $stat[0]['total_wins'];
    supabaseRequest('PATCH', "win_stats?team_id=eq.$winner_id", ['total_wins' => $current + 1]);
  } else {
    supabaseRequest('POST', "win_stats", ['team_id' => $winner_id, 'total_wins' => 1]);
  }

  // แพ้ → Lost
  updateTeam($loser, [
    'losses' => $new_losses,
    'status' => 'Lost',
    'is_champion' => false,
    'rest_turn' => 0
  ]);

  // ชนะ < 2 → ยังเล่นต่อ
  if ($new_wins < 2) {
    updateTeam($winner_id, [
      'wins' => $new_wins,
      'status' => 'Playing'
    ]);
  } else {
    // ชนะครบ 2 → แชมป์
    updateTeam($winner_id, [
      'wins' => 0,
      'status' => 'Champion',
      'is_champion' => true,
      'rest_turn' => 1
    ]);
  }
  

  // อัปเดตแมตช์
  supabaseRequest('PATCH', "matches?match_id=eq.$match_id", [
    'winner_id' => $winner_id,
    'loser_id' => $loser,
    'status' => 'Completed'
  ]);

  return ['success' => true];
}

