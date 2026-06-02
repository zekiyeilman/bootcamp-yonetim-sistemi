<?php
require_once __DIR__ . '/config.php';

$error_msg = null;
$students = [];
$bootcampler = [];
$lessons = [];

// Selected filters
$selected_student_id = 0;
$selected_bootcamp_id = 0;
$selected_ders_id = 0;

// Report Results
$student_report_data = [];
$student_bootcamps = [];
$bootcamp_report_data = [];
$ders_report_data = [
    'average' => 0,
    'total_grades' => 0,
    'distribution' => [
        '90-100' => 0,
        '80-89' => 0,
        '70-79' => 0,
        '60-69' => 0,
        '0-59' => 0
    ],
    'grades_list' => []
];

try {
    $db = getDB();

    // Fetch lists for dropdowns
    $students = $db->query("SELECT id, ad, soyad FROM ogrenci ORDER BY ad, soyad")->fetchAll();
    $bootcampler = $db->query("SELECT id, ad FROM bootcamp ORDER BY ad")->fetchAll();
    $lessons = $db->query("
        SELECT d.id, d.ad, b.ad as bootcamp_ad 
        FROM ders d 
        JOIN bootcamp b ON d.bootcamp_id = b.id 
        ORDER BY b.ad, d.ad
    ")->fetchAll();

    // 1. PROCESS STUDENT SUCCESS REPORT
    if (isset($_GET['student_id']) && intval($_GET['student_id']) > 0) {
        $selected_student_id = intval($_GET['student_id']);
        
        // Find all bootcamps this student registered to
        $stmt = $db->prepare("
            SELECT b.id, b.ad, b.baslangic_tarihi, b.bitis_tarihi,
                   CONCAT(e.ad, ' ', e.soyad) as egitmen_ad
            FROM bootcamp b
            JOIN bootcamp_ogrenci bo ON b.id = bo.bootcamp_id
            LEFT JOIN egitmen e ON b.egitmen_id = e.id
            WHERE bo.ogrenci_id = ?
        ");
        $stmt->execute([$selected_student_id]);
        $student_bootcamps = $stmt->fetchAll();
        
        // For each bootcamp, calculate weighted average using db function fn_OrtalamaNot!
        foreach ($student_bootcamps as &$bc) {
            // Call Database Function fn_OrtalamaNot
            $avg_stmt = $db->prepare("SELECT fn_OrtalamaNot(?, ?) as ortalama");
            $avg_stmt->execute([$selected_student_id, $bc['id']]);
            $bc['ortalama'] = $avg_stmt->fetchColumn();
            
            // Get attendance rate for this bootcamp's lessons
            $att_stmt = $db->prepare("
                SELECT 
                    SUM(CASE WHEN y.durum = 'Var' THEN 1 ELSE 0 END) as var_adet,
                    COUNT(y.id) as toplam_adet
                FROM yoklama y
                JOIN ders d ON y.ders_id = d.id
                WHERE y.ogrenci_id = ? AND d.bootcamp_id = ?
            ");
            $att_stmt->execute([$selected_student_id, $bc['id']]);
            $att_info = $att_stmt->fetch();
            
            $bc['katilim_var'] = $att_info['var_adet'] ?: 0;
            $bc['katilim_toplam'] = $att_info['toplam_adet'] ?: 0;
            $bc['katilim_yuzde'] = $bc['katilim_toplam'] > 0 ? round(($bc['katilim_var'] / $bc['katilim_toplam']) * 100, 2) : 100.00;
            
            // Get grades details for lessons in this bootcamp
            $grd_stmt = $db->prepare("
                SELECT d.ad as ders_ad, n.kategori, n.puan
                FROM notlar n
                JOIN ders d ON n.ders_id = d.id
                WHERE n.ogrenci_id = ? AND d.bootcamp_id = ?
                ORDER BY d.ad, n.kategori
            ");
            $grd_stmt->execute([$selected_student_id, $bc['id']]);
            $bc['ders_notlari'] = $grd_stmt->fetchAll();
        }
    }

    // 2. PROCESS BOOTCAMP ATTENDANCE REPORT
    if (isset($_GET['bootcamp_id']) && intval($_GET['bootcamp_id']) > 0) {
        $selected_bootcamp_id = intval($_GET['bootcamp_id']);
        
        // Fetch all students in this bootcamp
        $stmt = $db->prepare("
            SELECT o.id, o.ad, o.soyad, o.eposta
            FROM ogrenci o
            JOIN bootcamp_ogrenci bo ON o.id = bo.ogrenci_id
            WHERE bo.bootcamp_id = ?
            ORDER BY o.ad, o.soyad
        ");
        $stmt->execute([$selected_bootcamp_id]);
        $bootcamp_students = $stmt->fetchAll();
        
        // Fetch total lesson count in this bootcamp
        $stmt = $db->prepare("SELECT COUNT(*) FROM ders WHERE bootcamp_id = ?");
        $stmt->execute([$selected_bootcamp_id]);
        $total_lessons = $stmt->fetchColumn();
        
        foreach ($bootcamp_students as $student) {
            // For each student, get attendance counts in this bootcamp's lessons
            $att_stmt = $db->prepare("
                SELECT 
                    SUM(CASE WHEN y.durum = 'Var' THEN 1 ELSE 0 END) as var_adet,
                    COUNT(y.id) as toplam_adet
                FROM yoklama y
                JOIN ders d ON y.ders_id = d.id
                WHERE y.ogrenci_id = ? AND d.bootcamp_id = ?
            ");
            $att_stmt->execute([$student['id'], $selected_bootcamp_id]);
            $att_info = $att_stmt->fetch();
            
            $var_count = $att_info['var_adet'] ?: 0;
            $tot_count = $att_info['toplam_adet'] ?: 0;
            $percent = $tot_count > 0 ? round(($var_count / $tot_count) * 100, 2) : 100.00;
            
            $bootcamp_report_data[] = [
                'ogrenci_id' => $student['id'],
                'ogrenci_ad' => $student['ad'] . ' ' . $student['soyad'],
                'eposta' => $student['eposta'],
                'var_sayisi' => $var_count,
                'toplam_yoklama' => $tot_count,
                'katilim_yuzdesi' => $percent
            ];
        }
    }

    // 3. PROCESS COURSE GRADE DISTRIBUTION REPORT (BELL CURVE)
    if (isset($_GET['ders_id']) && intval($_GET['ders_id']) > 0) {
        $selected_ders_id = intval($_GET['ders_id']);
        
        // Fetch all grades for this lesson
        $stmt = $db->prepare("
            SELECT n.kategori, n.puan, CONCAT(o.ad, ' ', o.soyad) as ogrenci_ad
            FROM notlar n
            JOIN ogrenci o ON n.ogrenci_id = o.id
            WHERE n.ders_id = ?
            ORDER BY n.puan DESC
        ");
        $stmt->execute([$selected_ders_id]);
        $grades_list = $stmt->fetchAll();
        $ders_report_data['grades_list'] = $grades_list;
        
        if (!empty($grades_list)) {
            $total_puan = 0;
            foreach ($grades_list as $g) {
                $puan = intval($g['puan']);
                $total_puan += $puan;
                
                // Categorize for Bell Curve preview
                if ($puan >= 90 && $puan <= 100) $ders_report_data['distribution']['90-100']++;
                elseif ($puan >= 80 && $puan <= 89) $ders_report_data['distribution']['80-89']++;
                elseif ($puan >= 70 && $puan <= 79) $ders_report_data['distribution']['70-79']++;
                elseif ($puan >= 60 && $puan <= 69) $ders_report_data['distribution']['60-69']++;
                else $ders_report_data['distribution']['0-59']++;
            }
            
            $ders_report_data['total_grades'] = count($grades_list);
            $ders_report_data['average'] = round($total_puan / count($grades_list), 2);
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
    <title>Dinamik Analiz ve Gelişmiş Raporlar - Bootcamp Hub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container">
        <!-- Header -->
        <div style="margin-bottom: 2.5rem;">
            <span class="badge badge-info" style="margin-bottom: 0.5rem;">Analiz Paneli</span>
            <h1 style="font-size: 2.5rem;">📊 Gelişmiş Raporlama ve Analiz</h1>
            <p style="color: var(--text-secondary);">Öğrenci karneleri, katılım barları ve ders çan eğrisi not dağılım grafiklerini içeren gelişmiş veri raporları.</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger">⚠️ Hata: <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <!-- Report Selector Tabs -->
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2.5rem; background: rgba(255,255,255,0.02); padding: 0.5rem; border-radius: 12px; border: 1px solid var(--border-color);">
            <a href="raporlar.php?view=ogrenci" class="btn <?= (!isset($_GET['view']) || $_GET['view'] === 'ogrenci') ? 'btn-primary' : 'btn-secondary' ?>" style="flex-grow: 1;">🧑‍🎓 Öğrenci Başarı Raporu (Karne)</a>
            <a href="raporlar.php?view=bootcamp" class="btn <?= (isset($_GET['view']) && $_GET['view'] === 'bootcamp') ? 'btn-primary' : 'btn-secondary' ?>" style="flex-grow: 1;">🚀 Bootcamp Katılım Raporu</a>
            <a href="raporlar.php?view=ders" class="btn <?= (isset($_GET['view']) && $_GET['view'] === 'ders') ? 'btn-primary' : 'btn-secondary' ?>" style="flex-grow: 1;">📈 Ders Başarı ve Çan Eğrisi</a>
        </div>

        <!-- 1. STUDENT SUCCESS REPORT VIEW -->
        <?php if (!isset($_GET['view']) || $_GET['view'] === 'ogrenci'): ?>
            <div class="card" style="margin-bottom: 2rem;">
                <h2>Öğrenci Başarı Raporu (Dijital Karne)</h2>

                
                <form action="raporlar.php" method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" name="view" value="ogrenci">
                    <div class="form-group" style="flex-grow: 1; min-width: 250px; margin-bottom: 0;">
                        <label for="student_id">Raporu Alınacak Öğrenci</label>
                        <select name="student_id" id="student_id" class="form-control" required>
                            <option value="">Öğrenci Seçin...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" <?= $selected_student_id == $student['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['ad']) ?> <?= htmlspecialchars($student['soyad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="height: 46px;">📄 Rapor Üret</button>
                </form>
            </div>

            <?php if ($selected_student_id > 0): ?>
                <?php 
                    $curr_student_name = '';
                    foreach ($students as $s) { if ($s['id'] == $selected_student_id) { $curr_student_name = $s['ad'] . ' ' . $s['soyad']; break; } }
                ?>
                <div class="card" style="border-color: var(--primary);">
                    <div style="margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                        <span class="badge badge-primary">Öğrenci Başarı Sicili</span>
                        <h2 style="font-size: 2rem; margin-top: 0.5rem;"><?= htmlspecialchars($curr_student_name) ?> - Karne Sonuçları</h2>
                    </div>

                    <?php if (empty($student_bootcamps)): ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 3rem 0;">Seçili öğrencinin kayıtlı olduğu bir bootcamp programı bulunmamaktadır.</p>
                    <?php else: ?>
                        <?php foreach ($student_bootcamps as $bc): ?>
                            <div class="card" style="background: rgba(255,255,255,0.01); border-color: rgba(255,255,255,0.05); margin-bottom: 1.5rem; box-shadow: none;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                                    <div>
                                        <h3 style="color: var(--text-primary); font-size: 1.3rem;">🚀 <?= htmlspecialchars($bc['ad']) ?></h3>
                                        <p style="color: var(--text-secondary); font-size: 0.8rem; margin-top: 0.25rem;">
                                            Eğitmen: <strong><?= htmlspecialchars($bc['egitmen_ad'] ?: 'Atanmadı') ?></strong> | 
                                            Süreç: <?= htmlspecialchars($bc['baslangic_tarihi']) ?> - <?= htmlspecialchars($bc['bitis_tarihi']) ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Weighted average from DB Function fn_OrtalamaNot -->
                                    <div style="text-align: right;">
                                        <span style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; display: block;">AĞIRLIKLI BAŞARI ORTALAMASI</span>
                                        <?php 
                                            $avg_puan = floatval($bc['ortalama']);
                                            $avg_class = 'badge-danger';
                                            if ($avg_puan >= 85) $avg_class = 'badge-success';
                                            elseif ($avg_puan >= 60) $avg_class = 'badge-warning';
                                        ?>
                                        <span class="badge <?= $avg_class ?>" style="font-size: 1.5rem; padding: 0.5rem 1rem; border-radius: 10px; margin-top: 0.25rem; font-weight: 800; box-shadow: 0 0 15px rgba(99, 102, 241, 0.1);">
                                            <?= number_format($avg_puan, 2) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="grid-2" style="margin-top: 0;">
                                    <!-- Lesson grades list -->
                                    <div>
                                        <h4 style="margin-bottom: 1rem; color: var(--secondary);">📚 Modül Not Detayları</h4>
                                        <?php if (empty($bc['ders_notlari'])): ?>
                                            <p style="color: var(--text-muted); font-size: 0.85rem;">Bu bootcamp kapsamındaki derslerden henüz bir not girişi yapılmamış.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>Ders Adı</th>
                                                            <th>Kategori</th>
                                                            <th>Not</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($bc['ders_notlari'] as $nt): ?>
                                                            <tr>
                                                                <td style="font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($nt['ders_ad']) ?></td>
                                                                <td style="font-size: 0.85rem; color: var(--info); font-weight: 600;"><?= htmlspecialchars($nt['kategori']) ?></td>
                                                                <td>
                                                                    <span class="badge <?= intval($nt['puan']) >= 60 ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.8rem; font-weight: 700;">
                                                                        <?= intval($nt['puan']) ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Attendance Performance -->
                                    <div>
                                        <h4 style="margin-bottom: 1rem; color: var(--success);">📅 Yoklama ve Katılım Grafiği</h4>
                                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; text-align: center;">
                                            <span style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 500;">DERS KATILIM PERFORMANSI</span>
                                            <h3 style="font-size: 2.2rem; color: var(--success); margin: 0.5rem 0 1rem 0; font-weight: 800;">
                                                %<?= number_format($bc['katilim_yuzde'], 2) ?>
                                            </h3>
                                            
                                            <!-- CSS progress bar -->
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: <?= $bc['katilim_yuzde'] ?>%; background: linear-gradient(90deg, var(--success), var(--info));"></div>
                                            </div>
                                            
                                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.75rem;">
                                                <span>Katıldığı Ders: <strong><?= $bc['katilim_var'] ?> gün</strong></span>
                                                <span>Toplam Takip: <strong><?= $bc['katilim_toplam'] ?> gün</strong></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <!-- 2. BOOTCAMP ATTENDANCE REPORT VIEW -->
        <?php elseif ($_GET['view'] === 'bootcamp'): ?>
            <div class="card" style="margin-bottom: 2rem;">
                <h2>Bootcamp Katılım Raporu (% Bazlı Analiz)</h2>

                
                <form action="raporlar.php" method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" name="view" value="bootcamp">
                    <div class="form-group" style="flex-grow: 1; min-width: 250px; margin-bottom: 0;">
                        <label for="bootcamp_id">Raporu Alınacak Bootcamp</label>
                        <select name="bootcamp_id" id="bootcamp_id" class="form-control" required>
                            <option value="">Bootcamp Seçin...</option>
                            <?php foreach ($bootcampler as $bc): ?>
                                <option value="<?= $bc['id'] ?>" <?= $selected_bootcamp_id == $bc['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bc['ad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="height: 46px;">📊 Katılımı Hesapla</button>
                </form>
            </div>

            <?php if ($selected_bootcamp_id > 0): ?>
                <?php 
                    $curr_bc_name = '';
                    foreach ($bootcampler as $bc) { if ($bc['id'] == $selected_bootcamp_id) { $curr_bc_name = $bc['ad']; break; } }
                ?>
                <div class="card" style="border-color: var(--success);">
                    <div style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                        <span class="badge badge-success">Devamsızlık Oranları</span>
                        <h2 style="font-size: 2rem; margin-top: 0.5rem;">"<?= htmlspecialchars($curr_bc_name) ?>" Devam Çizelgesi</h2>
                    </div>

                    <?php if (empty($bootcamp_report_data)): ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 3rem 0;">Seçili bootcamp programında henüz kayıtlı öğrenci bulunmamaktadır.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Kayıtlı Kursiyer</th>
                                        <th>İletişim</th>
                                        <th>Katılım Sayısı (Gün)</th>
                                        <th style="width: 300px;">Katılım Yüzdesi (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bootcamp_report_data as $data): ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?= htmlspecialchars($data['ogrenci_ad']) ?></td>
                                            <td style="font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars($data['eposta']) ?></td>
                                            <td style="font-weight: 600; font-size: 0.9rem;">
                                                <?= $data['var_sayisi'] ?> / <?= $data['toplam_yoklama'] ?> gün
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 1rem;">
                                                    <span style="font-weight: 800; color: var(--success); width: 60px; font-size: 0.9rem; text-align: right;">
                                                        %<?= number_format($data['katilim_yuzdesi'], 1) ?>
                                                    </span>
                                                    <div class="progress-bar-container" style="margin-top: 0; flex-grow: 1;">
                                                        <div class="progress-bar-fill" style="width: <?= $data['katilim_yuzdesi'] ?>%; background: linear-gradient(90deg, var(--success), var(--info));"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <!-- 3. COURSE GRADE DISTRIBUTION VIEW -->
        <?php elseif ($_GET['view'] === 'ders'): ?>
            <div class="card" style="margin-bottom: 2rem;">
                <h2>Ders Başarı Raporu & Çan Eğrisi Analizi</h2>

                
                <form action="raporlar.php" method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" name="view" value="ders">
                    <div class="form-group" style="flex-grow: 1; min-width: 250px; margin-bottom: 0;">
                        <label for="ders_id">Raporu Alınacak Ders Modülü</label>
                        <select name="ders_id" id="ders_id" class="form-control" required>
                            <option value="">Ders Seçin...</option>
                            <?php foreach ($lessons as $lesson): ?>
                                <option value="<?= $lesson['id'] ?>" <?= $selected_ders_id == $lesson['id'] ? 'selected' : '' ?>>
                                    [<?= htmlspecialchars($lesson['bootcamp_ad']) ?>] - <?= htmlspecialchars($lesson['ad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="height: 46px;">📈 Başarıyı Analiz Et</button>
                </form>
            </div>

            <?php if ($selected_ders_id > 0): ?>
                <?php 
                    $curr_ders_name = '';
                    foreach ($lessons as $l) { if ($l['id'] == $selected_ders_id) { $curr_ders_name = $l['ad']; break; } }
                ?>
                <div class="card" style="border-color: var(--secondary);">
                    <div style="margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                        <span class="badge badge-warning">Akademik Grafikler</span>
                        <h2 style="font-size: 2rem; margin-top: 0.5rem;">"<?= htmlspecialchars($curr_ders_name) ?>" Akademik Raporu</h2>
                    </div>

                    <?php if ($ders_report_data['total_grades'] === 0): ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 3rem 0;">Bu ders modülü için henüz girilmiş başarı notu kaydı bulunmamaktadır.</p>
                    <?php else: ?>
                        <div class="grid-2" style="margin-top: 0;">
                            <!-- Distribution and statistics -->
                            <div>
                                <h3 style="color: var(--text-primary); font-size: 1.3rem; margin-bottom: 1.5rem;">📊 Ders Genel İstatistikleri</h3>
                                
                                <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                                    <div class="stat-card" style="padding: 1.2rem;">
                                        <div class="stat-info">
                                            <h3 style="font-size: 1.8rem; color: var(--secondary);"><?= $ders_report_data['average'] ?></h3>
                                            <p style="font-size: 0.75rem;">SINIF ORTALAMASI</p>
                                        </div>
                                    </div>
                                    <div class="stat-card" style="padding: 1.2rem;">
                                        <div class="stat-info">
                                            <h3 style="font-size: 1.8rem; color: var(--info);"><?= $ders_report_data['total_grades'] ?></h3>
                                            <p style="font-size: 0.75rem;">TOPLAM NOT GİRİŞİ</p>
                                        </div>
                                    </div>
                                </div>

                                <h4 style="margin-bottom: 0.75rem; color: var(--text-secondary);">Öğrenci Not Listesi</h4>
                                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Kursiyer</th>
                                                <th>Kategori</th>
                                                <th>Puan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ders_report_data['grades_list'] as $gr): ?>
                                                <tr>
                                                    <td style="font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($gr['ogrenci_ad']) ?></td>
                                                    <td style="font-size: 0.8rem; color: var(--text-secondary);"><?= htmlspecialchars($gr['kategori']) ?></td>
                                                    <td>
                                                        <span class="badge <?= intval($gr['puan']) >= 60 ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.75rem; font-weight: 700;">
                                                            <?= intval($gr['puan']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Grade distribution bar chart (Bell Curve Visualizer) -->
                            <div>
                                <h3 style="color: var(--text-primary); font-size: 1.3rem; margin-bottom: 1.5rem; text-align: center;">📈 Not Dağılım Çan Eğrisi</h3>
                                <p style="color: var(--text-secondary); font-size: 0.8rem; text-align: center; margin-bottom: 1rem;">
                                    Öğrenci puanlarının harf notu aralıklarına göre frekans/adet dağılım barları.
                                </p>

                                <div class="bell-curve-chart">
                                    <?php 
                                        // Calculate percentage height for each bar to avoid overflow
                                        $max_count = max(array_values($ders_report_data['distribution']));
                                        if ($max_count == 0) $max_count = 1;
                                        
                                        foreach ($ders_report_data['distribution'] as $label => $count): 
                                            $height_percent = ($count / $max_count) * 80 + 5; // Scaling with 5% min
                                    ?>
                                        <div class="chart-bar-container">
                                            <div class="chart-bar" style="height: <?= $height_percent ?>%;">
                                                <span class="chart-bar-value"><?= $count ?> kişi</span>
                                            </div>
                                            <div class="chart-bar-label">
                                                <strong><?= $label ?></strong>
                                                <span style="display: block; font-size: 0.65rem; color: var(--text-muted); margin-top: 2px;">
                                                    <?php 
                                                        if ($label === '90-100') echo 'Pekiyi (AA)';
                                                        elseif ($label === '80-89') echo 'İyi (BA/BB)';
                                                        elseif ($label === '70-79') echo 'Orta (CB)';
                                                        elseif ($label === '60-69') echo 'Geçer (CC)';
                                                        else echo 'Başarısız (FF)';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <footer class="footer">
            <p>© 2026 <strong>Bootcamp Hub</strong>. Bütün hakları saklıdır.</p>
            <div class="footer-cloud-tag">☁️ Cloud Native Architecture (GKE & Docker)</div>
        </footer>
    </main>

</body>
</html>
