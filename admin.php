<?php
// ป้องกัน warning ไปปน JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

include 'secure_headers.php'; // ถ้าไม่มีไฟล์นี้ ตัดบรรทัดนี้ออกได้
include 'functions.php';

// helper ส่ง JSON เดียวจบ
function respond($arr, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  // เก็บเศษ output (ถ้ามี) ลง log แทน
  $buf = ob_get_clean();
  if ($buf) error_log("[admin.php stray output] ".$buf);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// เริ่ม output buffer กันเศษ echo
ob_start();

// ---------- GET ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // /admin.php?stats=1 -> ส่งสถิติ (array)
  if (isset($_GET['stats'])) {
    $stats = getWinStats();
    if (!is_array($stats)) $stats = [];
    respond($stats);
  }
  // ปกติ -> ส่งข้อมูลหลัก (flat keys เพื่อเข้ากับ admin.html)
  respond([
    'teams'   => getTeams(),
    'matches' => getMatches()
  ]);
}

// ---------- POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // เพิ่มทีม
  if (isset($_POST['add_team'])) {
    $name = trim($_POST['team_name'] ?? '');
    if ($name === '') respond(['ok'=>false,'error'=>'กรุณาใส่ชื่อทีม'], 400);
    $res = addTeam($name);
    if (is_array($res) && isset($res['error'])) respond(['ok'=>false,'error'=>$res['error']], 400);
    respond(['ok'=>true]);
  }

  // ลบทีม
  if (isset($_POST['delete_team'])) {
    $id = intval($_POST['team_id'] ?? 0);
    if ($id<=0) respond(['ok'=>false,'error'=>'team_id ไม่ถูกต้อง'], 400);
    deleteTeam($id);
    respond(['ok'=>true]);
  }

  // แก้ชื่อทีม
  if (isset($_POST['update_team'])) {
    $id = intval($_POST['team_id'] ?? 0);
    $new = trim($_POST['new_name'] ?? '');
    if ($id<=0 || $new==='') respond(['ok'=>false,'error'=>'ข้อมูลไม่ครบ'], 400);
    if (function_exists('updateTeamName')) updateTeamName($id,$new); else updateTeam($id,['team_name'=>$new]);
    respond(['ok'=>true]);
  }

  // จับคู่
  if (isset($_POST['run_match'])) {
    $res = runMatch();
    if (is_array($res) && isset($res['error'])) respond(['ok'=>false,'error'=>$res['error']], 400);
    respond(['ok'=>true]);
  }

  // บันทึกผล
  if (isset($_POST['record_result'])) {
    $mid = intval($_POST['match_id'] ?? 0);
    $wid = intval($_POST['winner_id'] ?? 0);
    if ($mid<=0 || $wid<=0) respond(['ok'=>false,'error'=>'ข้อมูลไม่ครบ'], 400);
    $res = recordMatchResult($mid,$wid);
    if (is_array($res) && isset($res['success']) && !$res['success']) respond(['ok'=>false,'error'=>$res['message'] ?? 'บันทึกผลไม่สำเร็จ'], 400);
    respond(['ok'=>true]);
  }

  // ลบแมตช์
  if (isset($_POST['delete_match'])) {
    $mid = intval($_POST['match_id'] ?? 0);
    if ($mid<=0) respond(['ok'=>false,'error'=>'match_id ไม่ถูกต้อง'], 400);
    deleteMatch($mid);
    respond(['ok'=>true]);
  }

  // เคลียร์ทั้งหมด — รองรับทั้ง clear_teams และ clear_all
  if (isset($_POST['clear_teams']) || isset($_POST['clear_all'])) {
    if (function_exists('clearAll')) {
      $res = clearAll();
      respond(['ok'=> (bool)($res['success'] ?? false)]);
    } else {
      respond(['ok'=>false,'error'=>'clearAll() not found'], 500);
    }
  }

  respond(['ok'=>false,'error'=>'Unknown action'], 404);
}

// วิธีอื่น = 405
respond(['ok'=>false,'error'=>'Method not allowed'], 405);
