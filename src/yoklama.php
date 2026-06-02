<?php
require_once __DIR__ . '/config.php';

$error_msg = null;
$success_msg = null;
$lessons = [];
$students = [];

$selected_ders_id = 0;
$selected_tarih = date('Y-m-d');

try {
    $db = getDB();
    $csrf_token = generate_csrf_token();

    // Fetch all lessons for dropdown
    $lessons = $db->query("
        SELECT d.id, d.ad, b.ad as bootcamp_ad 
        FROM ders d
        JOIN bootcamp b ON d.bootcamp_id = b.id
        ORDER BY b.ad, d.ad
    ")->fetchAll();

    // 1. Handle GET Request to Load Students
    if (isset($_GET['ders_id']) && isset($_GET['tarih'])) {
        $selected_ders_id = intval($_GET['ders_id']);
        $selected_tarih = $_GET['tarih'];
        
        if ($selected_ders_id > 0) {
            // Find bootcamp of this lesson
            $stmt = $db->prepare("SELECT bootcamp_id FROM ders WHERE id = ?");
            $stmt->execute([$selected_ders_id]);
            $bootcamp_id = $stmt->fetchColumn();
            
            if ($bootcamp_id) {
                // Fetch all students registered to this bootcamp
                $stmt = $db->prepare("
                    SELECT o.id, o.ad, o.soyad 
                    FROM ogrenci o
                    JOIN bootcamp_ogrenci bo ON o.id = bo.ogrenci_id
                    WHERE bo.bootcamp_id = ?
                    ORDER BY o.ad, o.soyad
                ");
                $stmt->execute([$bootcamp_id]);
                $students = $stmt->fetchAll();
            }
        }
    }

    // 2. Handle POST Request to Save Bulk Attendance
    if (is_post_request()) {
        validate_post_csrf();
        
        $ders_id = intval($_POST['ders_id'] ?? 0);
        $tarih = $_POST['tarih'] ?? '';
        $durumlar = $_POST['durum'] ?? []; // Array of [student_id => 'Var'/'Yok']
        
        if ($ders_id === 0 || empty($tarih) || empty($durumlar)) {
            set_flash_message('danger', 'Hata: Eksik veri gönderildi!');
        } else {
            try {
                // Start transaction to insert all or nothing!
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO yoklama (ders_id, ogrenci_id, tarih, durum) VALUES (?, ?, ?, ?)");
                
                foreach ($durumlar as $ogrenci_id => $durum) {
                    // This query will execute, triggering tg_Katilim_Tarih_Kontrol and tg_YoklamaTekil
                    $stmt->execute([$ders_id, intval($ogrenci_id), $tarih, $durum]);
                }
                
                $db->commit();
                set_flash_message('success', 'Toplu yoklama başarıyla veritabanına kaydedildi.');
                header("Location: yoklama.php?ders_id={$ders_id}&tarih={$tarih}");
                exit();
            } catch (PDOException $e) {
                // Rollback transaction on trigger or connection error!
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                // Check if the error was thrown by our triggers (SQLSTATE 45000)
                if ($e->getCode() == '45000') {
                    // Extract trigger message
                    $trigger_error = $e->getMessage();
                    // Strip system prefix if any (like "SQLSTATE[45000]: <<custom error>>")
                    if (preg_match("/Hata: (.*)/", $trigger_error, $matches)) {
                        $trigger_error = $matches[1];
                    }
                    set_flash_message('danger', 'İş kuralı ihlali nedeniyle yoklama kaydedilemedi: ' . $trigger_error);
                } else {
                    set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
                }
                header("Location: yoklama.php?ders_id={$ders_id}&tarih={$tarih}");
                exit();
            }
        }
    }

} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yoklama ve Devamsızlık Takibi - Bootcamp</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container">
        <!-- Header -->
        <div style="margin-bottom: 2.5rem;">
            <span class="badge badge-warning" style="margin-bottom: 0.5rem;">Akademik Takip</span>
            <h1 style="font-size: 2.5rem;">✍️ Yoklama ve Devamsızlık Girişi</h1>
            <p style="color: var(--text-secondary);">Ders bazında ve tarih seçerek toplu yoklama girişi yapın. Sistem iş kuralları veritabanı seviyesinde (Triggers) denetlenmektedir.</p>
        </div>

        <?php display_flash_message(); ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">⚠️ Hata: <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <!-- Setup Form Card -->
        <div class="card" style="margin-bottom: 2rem;">
            <h2>Yoklama Kriterlerini Seçin</h2>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Yoklama girmek istediğiniz dersi ve tarihi seçerek öğrenci listesini yükleyin.</p>
            
            <form action="yoklama.php" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                <div class="form-group" style="flex-grow: 1; min-width: 250px; margin-bottom: 0;">
                    <label for="ders_id">Ders Modülü</label>
                    <select name="ders_id" id="ders_id" class="form-control" required>
                        <option value="">Ders Seçin...</option>
                        <?php foreach ($lessons as $lesson): ?>
                            <option value="<?= $lesson['id'] ?>" <?= $selected_ders_id == $lesson['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($lesson['bootcamp_ad']) ?>] - <?= htmlspecialchars($lesson['ad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="width: 200px; margin-bottom: 0;">
                    <label for="tarih">Yoklama Tarihi</label>
                    <input type="date" name="tarih" id="tarih" class="form-control" value="<?= htmlspecialchars($selected_tarih) ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="height: 46px;">📋 Öğrencileri Yükle</button>
            </form>
        </div>

        <!-- Attendance Entry Section -->
        <?php if ($selected_ders_id > 0): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <h2>Toplu Yoklama Giriş Paneli</h2>
                        <?php 
                            $sel_lesson_name = '';
                            foreach ($lessons as $l) {
                                if ($l['id'] == $selected_ders_id) { $sel_lesson_name = $l['ad']; break; }
                            }
                        ?>
                        <p style="color: var(--text-secondary); font-size: 0.85rem;">
                            <strong>Ders:</strong> <?= htmlspecialchars($sel_lesson_name) ?> | 
                            <strong>Tarih:</strong> <?= htmlspecialchars($selected_tarih) ?>
                        </p>
                    </div>
                </div>

                <?php if (empty($students)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 3rem 0;">
                        Bu dersin bağlı olduğu bootcamp programında kayıtlı öğrenci bulunamadı. Lütfen önce programa öğrenci atayın!
                    </p>
                <?php else: ?>


                    <form action="yoklama.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="ders_id" value="<?= $selected_ders_id ?>">
                        <input type="hidden" name="tarih" value="<?= htmlspecialchars($selected_tarih) ?>">

                        <div class="attendance-grid">
                            <?php foreach ($students as $student): ?>
                                <div class="attendance-item">
                                    <div class="attendance-name">
                                        <?= htmlspecialchars($student['ad']) ?> <?= htmlspecialchars($student['soyad']) ?>
                                        <span style="display: block; font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">Öğrenci ID: #<?= $student['id'] ?></span>
                                    </div>
                                    
                                    <div class="attendance-switch">
                                        <input type="radio" name="durum[<?= $student['id'] ?>]" id="durum_var_<?= $student['id'] ?>" value="Var" checked>
                                        <label for="durum_var_<?= $student['id'] ?>">VAR</label>
                                        
                                        <input type="radio" name="durum[<?= $student['id'] ?>]" id="durum_yok_<?= $student['id'] ?>" value="Yok">
                                        <label for="durum_yok_<?= $student['id'] ?>">YOK</label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-success" style="padding: 0.8rem 2.5rem;">💾 Yoklamayı Kaydet</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <footer class="footer">
            <p>© 2026 <strong>Bootcamp</strong>. Bütün hakları saklıdır.</p>
            <div class="footer-cloud-tag">☁️ Cloud Native Architecture (GKE & Docker)</div>
        </footer>
    </main>

</body>
</html>
