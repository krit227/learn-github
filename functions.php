<?php
include_once 'supabase.php';

/**
 * Basic data accessors
 */
function getTeams() {
  return supabaseRequest('GET', 'teams', null, '?select=*&order=id.asc');
}

function getMatches() {
  return supabaseRequest('GET', 'matches', null, '?select=*&order=match_id.asc');
}

function addTeam($team_name) {
  return supabaseRequest('POST', 'teams', [
    'team_name'   => $team_name,
    'wins'        => 0,
    'losses'      => 0,
    'is_champion' => false,
    'rest_turn'   => 0,
    'status'      => 'Waiting'
  ]);
}

/**
 * Win stats – prefer view by normalized winner_name (team_key/display_name/total_wins).
 * Falls back to older view using team_id + teams!fk_team.
 */
function getWinStats() {
  // Try new shape first
  $q1 = supabaseRequest('GET', 'win_stats', null, '?select=team_key,display_name,total_wins&order=total_wins.desc');
  if (is_array($q1) && !isset($q1['error'])) {
    return $q1;
  }
  // Fallback to old shape
  $q2 = supabaseRequest('GET', 'win_stats', null, '?select=team_id,total_wins,teams!fk_team(team_name)&order=total_wins.desc');
  return $q2;
}

function updateTeam($id, $data) {
  return supabaseRequest('PATCH', "teams?id=eq.$id", $data);
}

function deleteTeam($id) {
  return supabaseRequest('DELETE', "teams?id=eq.$id");
}

/**
 * Helpers
 */
function existsPendingMatch() {
  $ms = getMatches();
  if (!is_array($ms)) return false;
  foreach ($ms as $m) {
    if (isset($m['status']) && $m['status'] === 'Pending') return true;
  }
  return false;
}

function getTeamById($id) {
  $row = supabaseRequest('GET', 'teams', null, "?id=eq.$id");
  if (is_array($row) && count($row) > 0) return $row[0];
  return null;
}

/**
 * Run match pairing with simple server-side check to avoid duplicate Pending.
 */
function runMatch() {
  if (existsPendingMatch()) {
    return ['error' => 'มี Pending อยู่แล้ว'];
  }

  $teams = getTeams();
  if (!is_array($teams)) return ['error' => 'โหลดทีมไม่สำเร็จ'];

  // Champion currently resting (rest_turn > 0)
  $champion_resting = array_filter($teams, function($t){
    return !empty($t['is_champion']) && intval($t['rest_turn']) > 0;
  });

  // Matchable = Waiting & rest_turn=0 OR Playing; exclude Champion with rest_turn>0
  $matchable = array_filter($teams, function($t){
    $status = $t['status'] ?? '';
    $rest   = intval($t['rest_turn'] ?? 0);
    $isChamp= !empty($t['is_champion']);
    return (
      ($status === 'Waiting' && $rest === 0) || $status === 'Playing'
    ) && (!$isChamp || $rest === 0);
  });

  // Order by id asc and pick first 2
  usort($matchable, function($a,$b){ return ($a['id'] ?? 0) <=> ($b['id'] ?? 0); });
  $matchable = array_values($matchable);

  if (count($matchable) < 2) {
    return ['success' => false, 'message' => 'ทีมไม่พอ'];
  }

  $t1 = $matchable[0];
  $t2 = $matchable[1];

  supabaseRequest('POST', 'matches', [
    'team1_id' => $t1['id'],
    'team2_id' => $t2['id'],
    'status'   => 'Pending'
  ]);

  updateTeam($t1['id'], ['status' => 'Playing']);
  updateTeam($t2['id'], ['status' => 'Playing']);

  // After pairing, decrease champion rest_turn
  foreach ($champion_resting as $c) {
    $new_rest = intval($c['rest_turn']) - 1;
    if ($new_rest <= 0) {
      updateTeam($c['id'], [
        'rest_turn'   => 0,
        'status'      => 'Waiting',
        'is_champion' => false
      ]);
    } else {
      updateTeam($c['id'], ['rest_turn' => $new_rest]);
    }
  }

  return ['success' => true, 'match' => [$t1, $t2]];
}

/**
 * Clear all teams and matches (no echo/exit in library).
 */
function clearAll() {
  // delete matches
  $matches = supabaseRequest('GET', 'matches');
  if (is_array($matches)) {
    foreach ($matches as $m) {
      if (isset($m['match_id']))
        supabaseRequest('DELETE', 'matches', null, '?match_id=eq.'.$m['match_id']);
    }
  }
  // delete teams
  $teams = supabaseRequest('GET', 'teams');
  if (is_array($teams)) {
    foreach ($teams as $t) {
      if (isset($t['id']))
        supabaseRequest('DELETE', 'teams', null, '?id=eq.'.$t['id']);
    }
  }
  return ['success' => true];
}



function deleteMatch($match_id) {
  $query = "?match_id=eq.$match_id";
  return supabaseRequest('DELETE', 'matches', null, $query);
}

/**
 * Record match result; freezes winner/loser names into matches for stable stats.
 */
function recordMatchResult($match_id, $winner_id) {
  $matches = getMatches();
  if (!is_array($matches)) return ['success' => false, 'message' => 'โหลดแมตช์ไม่สำเร็จ'];

  $found = array_filter($matches, fn($m) => isset($m['match_id']) && intval($m['match_id']) == intval($match_id));
  if (!$found) return ['success' => false, 'message' => 'ไม่พบแมตช์'];
  $match = array_values($found)[0];

  $team1 = $match['team1_id'];
  $team2 = $match['team2_id'];
  $loser  = (intval($winner_id) == intval($team1)) ? $team2 : $team1;

  // Fetch team rows
  $winner_data = getTeamById($winner_id);
  $loser_data  = getTeamById($loser);

  if (!$winner_data || !$loser_data) {
    return ['success' => false, 'message' => 'ไม่พบข้อมูลทีม'];
  }

  $new_wins   = intval($winner_data['wins']) + 1;
  $new_losses = intval($loser_data['losses']) + 1;

  // Update winner stats (2-in-a-row champion logic)
  if ($new_wins < 2) {
    updateTeam($winner_id, [
      'wins'   => $new_wins,
      'status' => 'Playing'
    ]);
  } else {
    // Became champion
    updateTeam($winner_id, [
      'wins'        => 0,
      'status'      => 'Champion',
      'is_champion' => true,
      'rest_turn'   => 1
    ]);
  }

  // Update loser
  updateTeam($loser, [
    'losses'      => $new_losses,
    'status'      => 'Lost',
    'is_champion' => false,
    'rest_turn'   => 0
  ]);

  // Freeze names into matches for stable, name-based stats
  $patch = [
    'winner_id'   => $winner_id,
    'loser_id'    => $loser,
    'winner_name' => $winner_data['team_name'],
    'loser_name'  => $loser_data['team_name'],
    'status'      => 'Completed'
  ];
  supabaseRequest('PATCH', "matches?match_id=eq.$match_id", $patch);

  // (Optional) If you still keep an old win_stats table by team_id, you could update it here.
  // Recommend moving to a DB view that aggregates by lower(winner_name) instead.

  return ['success' => true];
}
