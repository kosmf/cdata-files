<?php
// Set zona waktu ke Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

// Masukkan kredensial database Anda
$host = 'localhost';
$db = 'smgroupco_office'; 
$user = 'ngarep';             
$pass = '0kK.yN!tl39na9ae'; 

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    exit("Connection failed");
}

$sn = $_GET['SN'] ?? 'unknown_device';
$table = $_GET['table'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'POST') {
    $raw_input = file_get_contents('php://input');
    
    $data = !empty($raw_input) ? $raw_input : '';
    if (empty($data) && !empty($_POST)) {
        foreach ($_POST as $k => $v) {
            $data .= "$k\t$v\n";
        }
    }
    
    // --- 0. PENANGANAN STATUS BALIK COMMAND DARI MESIN ---
    if ($table == 'devicecmd' || isset($_GET['ID']) || strpos($data, 'CMDRETURN') !== false || strpos($data, 'ID=') !== false) {
        $cmd_id = $_GET['ID'] ?? null;
        if (!$cmd_id && preg_match('/ID=([0-9]+)/i', $data, $m)) {
            $cmd_id = $m[1];
        }
        if ($cmd_id) {
            try {
                $upd_cmd = $pdo->prepare("UPDATE iclock_commands SET status = 2 WHERE id = ?");
                $upd_cmd->execute([$cmd_id]);
            } catch (Exception $e) {}
        }
    }

    // --- 2. PENANGANAN DATA ABSENSI MASUK (CLOCK IN/OUT) ---
    if (!empty($data)) {
        $lines = explode("\n", $data);
        $today = date('Y-m-d');
        $current_time = date('H:i:s');

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Pisahkan berdasarkan spasi atau tab secara fleksibel
            $cols = preg_split('/\s+/', $line);
            
            $idkar = $cols[0] ?? null;
            $date = $cols[1] ?? null;
            $time = $cols[2] ?? null;
            
            if (!is_numeric($idkar) || empty($date) || empty($time)) {
                continue;
            }

            try {
                // Validasi format tanggal & waktu
                $full_datetime = $date . ' ' . $time;
                $datetime = new DateTime($full_datetime);
                $date_clean = $datetime->format('Y-m-d');
                $time_clean = $datetime->format('H:i:s');

                // FILTER 1: TOLAK TANGGAL MASA DEPAN
                if ($date_clean > $today) {
                    continue;
                }

                // FILTER 2: TOLAK JAM MASA DEPAN (Hari ini tapi jam di masa depan)
                if ($date_clean == $today && $time_clean > $current_time) {
                    continue; 
                }

                // FILTER 3: CEK DUPLIKAT
                $cek = $pdo->prepare("SELECT id FROM absensi WHERE idkar = ? AND date = ? AND time = ?");
                $cek->execute([$idkar, $date_clean, $time_clean]);
                
                if ($cek->rowCount() > 0) {
                    continue; 
                }

                // Simpan data absensi ke database
                $inputtime = date('Y-m-d H:i:s'); 
                $source_device = $sn;

                $stmt = $pdo->prepare("INSERT INTO absensi (idkar, date, time, inputtime, device) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$idkar, $date_clean, $time_clean, $inputtime, $source_device]);

            } catch (Exception $e) {
                continue;
            }
        }
    }
    
    echo "OK: 0";
    exit;
}

if ($method == 'GET') {
    $cek_pernah_kirim = $pdo->prepare("SELECT id FROM iclock_commands WHERE sn = ? AND cmd LIKE 'Set Date Time=%' AND DATE(created_at) = CURDATE() LIMIT 1");
    $cek_pernah_kirim->execute([$sn]);
    
    if ($cek_pernah_kirim->rowCount() == 0) {
        $server_datetime = date('Y-m-d H:i:s');
        $time_command = "Set Date Time=" . $server_datetime;
        $ins_time_cmd = $pdo->prepare("INSERT INTO iclock_commands (sn, cmd, status, created_at) VALUES (?, ?, 0, NOW())");
        $ins_time_cmd->execute([$sn, $time_command]);
    }

    $stmt_cmd = $pdo->prepare("SELECT id, cmd FROM iclock_commands WHERE sn = ? AND status = 0 ORDER BY id ASC LIMIT 1");
    $stmt_cmd->execute([$sn]);
    $command = $stmt_cmd->fetch(PDO::FETCH_ASSOC);

    if ($command) {
        $cmd_id = $command['id'];
        $cmd_string = $command['cmd'];
        $update_status = $pdo->prepare("UPDATE iclock_commands SET status = 1 WHERE id = ?");
        $update_status->execute([$cmd_id]);
        echo "C:{$cmd_id}:{$cmd_string}";
        exit;
    }

    if (isset($_GET['options']) || isset($_GET['pushver'])) {
        $response = "GET_OPTION FROM:{$sn}\r\n";
        $response .= "Stamp=9999\r\n";
        $response .= "OpStamp=9999\r\n";
        $response .= "ErrorDelay=60\r\n";
        $response .= "Delay=10\r\n";
        $response .= "TransTimes=00:00 23:59\r\n";
        $response .= "TransInterval=1\r\n";
        $response .= "TransFlag=1111000000\r\n";
        echo $response;
        exit;
    }
}

echo "1";
?>