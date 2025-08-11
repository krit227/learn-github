<?php
include 'functions.php';

/* ---------- Handle POST: Add Team ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['team_name'])) {
  $name = trim($_POST['team_name']);
  if ($name === '') {
    $post_error = 'กรุณากรอกชื่อทีม';
  } else {
    $result = addTeam($name);
    if (is_array($result) && isset($result['error'])) {
      // แสดงรายละเอียด error จาก supabase.php
      $post_error = 'เพิ่มทีมไม่สำเร็จ: ' . htmlspecialchars(print_r($result['error'], true), ENT_QUOTES, 'UTF-8');
    } else {
      // Redirect เพื่อกันโพสต์ซ้ำ
      header("Location: " . $_SERVER['PHP_SELF']);
      exit;
    }
  }
}

/* ---------- Load Win Stats for PHP section ---------- */
$win_stats = getWinStats();
if (!is_array($win_stats)) { $win_stats = []; }
if (isset($win_stats['data']) && is_array($win_stats['data'])) {
  $win_stats = $win_stats['data'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>คิวสนามฟ้า</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-4 min-h-screen">
  <h1 class="text-2xl font-bold text-center mb-4">🏀 คิวสนามฟ้า (สนามหน้าห้องน้ำ)</h1>

  <div class="max-w-md mx-auto bg-white p-4 rounded-xl shadow">
    <?php if (!empty($post_error)): ?>
      <div class="bg-red-100 text-red-700 p-2 rounded mb-3">
        <?= $post_error ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="flex gap-2 mb-4" onsubmit="return handleSubmit(event)">
      <input type="text" name="team_name" class="flex-1 border rounded px-2 py-1" placeholder="ชื่อทีม" required />
      <button type="submit" class="bg-blue-500 text-white px-4 py-1 rounded">เพิ่มทีม</button>
      <!-- ไม่ต้องพึ่ง name="add_team" แล้ว -->
    </form>

    <div id="live-display">
      <div class="mb-4">
        <h2 class="text-lg font-semibold mb-1">🏆 ทีมแชมป์</h2>
        <p id="champion" class="text-center text-xl text-green-600 font-bold">โหลดข้อมูล...</p>
      </div>

      <div class="mb-4">
        <h2 class="text-lg font-semibold mb-1">🔥 กำลังแข่ง</h2>
        <p id="playing" class="text-center text-lg text-orange-600">โหลดข้อมูล...</p>
      </div>

      <div class="mb-4">
        <h2 class="text-lg font-semibold mb-1">⏭️ ทีมถัดไป</h2>
        <ul id="next-teams" class="list-disc pl-5 text-sm"></ul>
      </div>

      <div>
        <h2 class="text-lg font-semibold mb-1">📋 รายชื่อทีมทั้งหมด</h2>
        <ul id="team-list" class="space-y-1 text-sm"></ul>
      </div>

      <div class="mt-6">
        <h2 class="text-lg font-semibold mb-2">📈 สถิติทีมที่ชนะมากที่สุด</h2>

        <?php if (!empty($win_stats)): ?>
          <div class="space-y-2">
            <?php
            $icons = ['🥇', '🥈', '🥉'];
            foreach ($win_stats as $index => $s):
              $teamName = null;

              if (is_array($s)) {
                if (!empty($s['display_name'])) {
                  $teamName = $s['display_name'];
                } elseif (!empty($s['team_key'])) {
                  $teamName = $s['team_key'];
                } elseif (isset($s['teams'])) {
                  if (is_array($s['teams']) && isset($s['teams']['team_name'])) {
                    $teamName = $s['teams']['team_name'];
                  } elseif (is_string($s['teams']) && $s['teams'] !== '') {
                    $teamName = $s['teams'];
                  }
                }
              }

              if (!$teamName) { $teamName = 'ไม่พบชื่อทีม'; }
              $teamName = htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8');

              $wins = (int)($s['total_wins'] ?? 0);
              $icon = $icons[$index] ?? '✅';
            ?>
              <div class="flex justify-between items-center bg-white rounded-md shadow p-2 border">
                <div class="flex items-center gap-2">
                  <span class="text-xl"><?= $icon ?></span>
                  <span class="font-medium"><?= $teamName ?></span>
                </div>
                <span class="text-sm text-gray-600"><?= $wins ?> ครั้ง</span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-sm text-gray-500">ยังไม่มีข้อมูล</p>
        <?php endif; ?>

      </div>

    </div>
  </div>

  <script>
    function handleSubmit(event) {
      const button = event.target.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
        button.innerText = "⏳ กำลังเพิ่ม...";
      }
      return true;
    }

    function escapeHTML(s){
      return String(s).replace(/[&<>"]/g, c => ({
        "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"
      }[c]));
    }

    async function loadTeams() {
      try {
        const res = await fetch('data.php');
        const data = await res.json();
        const teams = data.teams || [];

        const champ = teams.find(t => t.is_champion);
        document.getElementById("champion").innerText = champ ? `${champ.team_name} (พัก ${champ.rest_turn} ตา)` : 'ยังไม่มีแชมป์';

        const playing = teams.filter(t => t.status === 'Playing');
        document.getElementById("playing").innerText = (playing.length >= 2)
          ? `${playing[0].team_name} vs ${playing[1].team_name}` : 'รอเริ่มแข่ง';

        const waiting = teams.filter(t => t.status === 'Waiting' && Number(t.rest_turn) === 0);
        const nextHTML = waiting.slice(0, 3).map(t => `<li>${escapeHTML(t.team_name)}</li>`).join('');
        document.getElementById("next-teams").innerHTML = nextHTML || '<li>ไม่มีทีมถัดไป</li>';

        const allHTML = teams.map(t => `<li class="border p-1 rounded">${escapeHTML(t.team_name)} (${t.status})</li>`).join('');
        document.getElementById("team-list").innerHTML = allHTML;
      } catch (err) {
        console.error("โหลดข้อมูลไม่สำเร็จ", err);
      }
    }

    loadTeams();
    setInterval(loadTeams, 5000);
  </script>
</body>
</html>
