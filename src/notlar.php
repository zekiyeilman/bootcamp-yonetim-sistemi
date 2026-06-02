<?php
require_once __DIR__ . '/config.php';

$error_msg = null;
$success_msg = null;
$lessons = [];
$students = [];
$grades = [];
$edit_grade = null;

$filter_ders_id = 0;

try {
    $db = getDB();
    $csrf_token = generate_csrf_token();

    // 1. Fetch dropdown data
    $lessons = $db->query("
        SELECT d.id, d.ad, b.ad as bootcamp_ad 
        FROM ders d
        JOIN bootcamp b ON d.bootcamp_id = b.id
        ORDER BY b.ad, d.ad
    ")->fetchAll();

    $students = $db->query("SELECT id, ad, soyad FROM ogrenci ORDER BY ad, soyad")->fetchAll();

    // 2. Handle POST Requests (Add, Update, Delete)
    if (is_post_request()) {
        validate_post_csrf();
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'ekle') {
            $ders_id = intval($_POST['ders_id'] ?? 0);
            $ogrenci_id = intval($_POST['ogrenci_id'] ?? 0);
            $kategori = $_POST['kategori'] ?? '';
            $puan = isset($_POST['puan']) ? intval($_POST['puan']) : -1;
            
            if ($ders_id === 0 || $ogrenci_id === 0 || empty($kategori) || $puan === -1) {
                set_flash_message('danger', 'Lütfen tüm alanları doldurun!');
            } else {
                try {
                    // This query will run, and triggers tg_NotKontrol will validate!
                    $stmt = $db->prepare("INSERT INTO notlar (ders_id, ogrenci_id, kategori, puan) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$ders_id, $ogrenci_id, $kategori, $puan]);
                    set_flash_message('success', 'Öğrencinin not kaydı başarıyla eklendi.');
                    header('Location: notlar.php?ders_id=' . $ders_id);
                    exit();
                } catch (PDOException $e) {
                    if ($e->getCode() == '45000') {
                        $trigger_err = $e->getMessage();
                        if (preg_match("/Hata: (.*)/", $trigger_err, $matches)) { $trigger_err = $matches[1]; }
                        set_flash_message('danger', 'Hata: ' . $trigger_err);
                    } else {
                        set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
                    }
                    header('Location: notlar.php');
                    exit();
                }
            }
        }
        
        elseif ($action === 'guncelle') {
            $id = intval($_POST['id'] ?? 0);
            $ders_id = intval($_POST['ders_id'] ?? 0);
            $ogrenci_id = intval($_POST['ogrenci_id'] ?? 0);
            $kategori = $_POST['kategori'] ?? '';
            $puan = isset($_POST['puan']) ? intval($_POST['puan']) : -1;
            
            if ($id === 0 || $ders_id === 0 || $ogrenci_id === 0 || empty($kategori) || $puan === -1) {
                set_flash_message('danger', 'Lütfen tüm alanları doldurun!');
            } else {
                try {
                    // This query will run, and trigger tg_NotGuncelle_Kontrol will validate!
                    $stmt = $db->prepare("UPDATE notlar SET ders_id = ?, ogrenci_id = ?, kategori = ?, puan = ? WHERE id = ?");
                    $stmt->execute([$ders_id, $ogrenci_id, $kategori, $puan, $id]);
                    set_flash_message('success', 'Öğrenci not bilgileri başarıyla güncellendi.');
                    header('Location: notlar.php?ders_id=' . $ders_id);
                    exit();
                } catch (PDOException $e) {
                    if ($e->getCode() == '45000') {
                        $trigger_err = $e->getMessage();
                        if (preg_match("/Hata: (.*)/", $trigger_err, $matches)) { $trigger_err = $matches[1]; }
                        set_flash_message('danger', 'Hata: ' . $trigger_err);
                    } else {
                        set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
                    }
                    header('Location: notlar.php');
                    exit();
                }
            }
        }
        
        elseif ($action === 'sil') {
            $id = intval($_POST['id'] ?? 0);
            $ders_id = intval($_POST['ders_id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare("DELETE FROM notlar WHERE id = ?");
                $stmt->execute([$id]);
                set_flash_message('success', 'Öğrenci not kaydı sistemden silindi.');
                header('Location: notlar.php?ders_id=' . $ders_id);
                exit();
            }
        }
    }

    // 3. Handle GET Edit Request
    if (isset($_GET['duzenle'])) {
        $edit_id = intval($_GET['duzenle']);
        $stmt = $db->prepare("SELECT * FROM notlar WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_grade = $stmt->fetch();
    }

    // 4. Handle GET Filter Request & Fetch Grades
    if (isset($_GET['ders_id']) && intval($_GET['ders_id']) > 0) {
        $filter_ders_id = intval($_GET['ders_id']);
        $stmt = $db->prepare("
            SELECT n.id, n.kategori, n.puan, n.ders_id,
                   d.ad as ders_ad, b.ad as bootcamp_ad,
                   o.id as ogrenci_id, CONCAT(o.ad, ' ', o.soyad) as ogrenci_ad
            FROM notlar n
            JOIN ders d ON n.ders_id = d.id
            JOIN bootcamp b ON d.bootcamp_id = b.id
            JOIN ogrenci o ON n.ogrenci_id = o.id
            WHERE n.ders_id = ?
            ORDER BY n.id DESC
        ");
        $stmt->execute([$filter_ders_id]);
        $grades = $stmt->fetchAll();
    } else {
        $stmt = $db->query("
            SELECT n.id, n.kategori, n.puan, n.ders_id,
                   d.ad as ders_ad, b.ad as bootcamp_ad,
                   o.id as ogrenci_id, CONCAT(o.ad, ' ', o.soyad) as ogrenci_ad
            FROM notlar n
            JOIN ders d ON n.ders_id = d.id
            JOIN bootcamp b ON d.bootcamp_id = b.id
            JOIN ogrenci o ON n.ogrenci_id = o.id
            ORDER BY n.id DESC
            LIMIT 50
        ");
        $grades = $stmt->fetchAll();
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
    <title>Akademik Not Sistemi - Bootcamp</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container">
        <!-- Header -->
        <div style="margin-bottom: 2.5rem;">
            <span class="badge badge-secondary" style="margin-bottom: 0.5rem;">Akademik Değerlendirme</span>
            <h1 style="font-size: 2.5rem;">💯 Akademik Not Sistemi</h1>
            <p style="color: var(--text-secondary);">Dersler için Sınav, Ödev ve Proje kategorilerinde öğrencilere not girişleri yapın.</p>
        </div>

        <?php display_flash_message(); ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">⚠️ Hata: <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Add / Edit Grade Card -->
            <div class="card">
                <?php if ($edit_grade): ?>
                    <h2>Not Bilgilerini Düzenle</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Seçili öğrenci not kaydını güncelleyin.</p>
                    
                    <form action="notlar.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="guncelle">
                        <input type="hidden" name="id" value="<?= $edit_grade['id'] ?>">
                        
                        <div class="form-group">
                            <label for="ogrenci_id">Öğrenci</label>
                            <select name="ogrenci_id" id="ogrenci_id" class="form-control" required>
                                <option value="">Öğrenci Seçin...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['id'] ?>" <?= $edit_grade['ogrenci_id'] == $student['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($student['ad']) ?> <?= htmlspecialchars($student['soyad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ders_id">Ders Modülü</label>
                            <select name="ders_id" id="ders_id" class="form-control" required>
                                <option value="">Ders Seçin...</option>
                                <?php foreach ($lessons as $lesson): ?>
                                    <option value="<?= $lesson['id'] ?>" <?= $edit_grade['ders_id'] == $lesson['id'] ? 'selected' : '' ?>>
                                        [<?= htmlspecialchars($lesson['bootcamp_ad']) ?>] - <?= htmlspecialchars($lesson['ad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="kategori">Kategori</label>
                            <select name="kategori" id="kategori" class="form-control" required>
                                <option value="">Not Kategorisi Seçin...</option>
                                <option value="Sınav" <?= $edit_grade['kategori'] === 'Sınav' ? 'selected' : '' ?>>Sınav</option>
                                <option value="Ödev" <?= $edit_grade['kategori'] === 'Ödev' ? 'selected' : '' ?>>Ödev</option>
                                <option value="Proje" <?= $edit_grade['kategori'] === 'Proje' ? 'selected' : '' ?>>Proje</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="puan">Not Puanı (0 - 100)</label>
                            <input type="number" name="puan" id="puan" class="form-control" value="<?= intval($edit_grade['puan']) ?>" placeholder="Puan girin" required>
                            <small style="color: var(--text-muted); font-size: 0.75rem;">Veritabanı trigger kontrolü 0'dan küçük, 100'den büyük notları engeller.</small>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                            <a href="notlar.php" class="btn btn-secondary">İptal Et</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h2>Yeni Not Girişi Yap</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Öğrenciye ait ders bazlı bir başarı/değerlendirme puanı ekleyin.</p>
                    
                    <form action="notlar.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="ekle">
                        
                        <div class="form-group">
                            <label for="ogrenci_id">Değerlendirilecek Öğrenci</label>
                            <select name="ogrenci_id" id="ogrenci_id" class="form-control" required>
                                <option value="">Öğrenci Seçin...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['ad']) ?> <?= htmlspecialchars($student['soyad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ders_id">Ders / Modül</label>
                            <select name="ders_id" id="ders_id" class="form-control" required>
                                <option value="">Ders Modülü Seçin...</option>
                                <?php foreach ($lessons as $lesson): ?>
                                    <option value="<?= $lesson['id'] ?>"><?= htmlspecialchars($lesson['ad']) ?> (<?= htmlspecialchars($lesson['bootcamp_ad']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="kategori">Not Kategorisi</label>
                            <select name="kategori" id="kategori" class="form-control" required>
                                <option value="">Kategori Seçin...</option>
                                <option value="Sınav">Sınav</option>
                                <option value="Ödev">Ödev</option>
                                <option value="Proje">Proje</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="puan">Not Değeri (0 - 100)</label>
                            <input type="number" name="puan" id="puan" class="form-control" placeholder="Puan girin (0 - 100)" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Notu Veritabanına Kaydet</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Grade List Card -->
            <div class="card">
                <h2>Akademik Not Listesi</h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Girilen başarı notları. Ders bazında filtreleme yapabilirsiniz.</p>
                
                <!-- Filter form -->
                <form action="notlar.php" method="GET" class="search-box">
                    <select name="ders_id" class="form-control" style="flex-grow: 1;">
                        <option value="">Tüm Ders Notlarını Listele</option>
                        <?php foreach ($lessons as $lesson): ?>
                            <option value="<?= $lesson['id'] ?>" <?= $filter_ders_id == $lesson['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lesson['ad']) ?> (<?= htmlspecialchars($lesson['bootcamp_ad']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-secondary">🔍 Filtrele</button>
                    <?php if ($filter_ders_id > 0): ?>
                        <a href="notlar.php" class="btn btn-danger">❌</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($grades)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">Seçili kriterlerde kayıtlı not bulunmamaktadır.</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Öğrenci</th>
                                    <th>Ders & Kategori</th>
                                    <th>Puan</th>
                                    <th style="text-align: right;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $grade): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?= htmlspecialchars($grade['ogrenci_ad']) ?>
                                            </div>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">Öğrenci ID: #<?= $grade['ogrenci_id'] ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: var(--text-primary); font-weight: 500;">
                                                <?= htmlspecialchars($grade['ders_ad']) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                                <?= htmlspecialchars($grade['bootcamp_ad']) ?> | <span style="color: var(--info); font-weight: 700;"><?= htmlspecialchars($grade['kategori']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                                $puan = intval($grade['puan']);
                                                $badge_class = 'badge-danger';
                                                if ($puan >= 85) $badge_class = 'badge-success';
                                                elseif ($puan >= 60) $badge_class = 'badge-warning';
                                            ?>
                                            <span class="badge <?= $badge_class ?>" style="font-size: 0.85rem; padding: 0.4rem 0.75rem; border-radius: 8px;">
                                                <?= $puan ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 0.5rem; justify-content: flex-end;">
                                                <a href="notlar.php?duzenle=<?= $grade['id'] ?>&ders_id=<?= $grade['ders_id'] ?>" class="btn btn-secondary btn-sm" title="Notu Düzenle">✏️</a>
                                                
                                                <form action="notlar.php" method="POST" onsubmit="return confirm('Not kaydını silmek istediğinize emin misiniz?');" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="sil">
                                                    <input type="hidden" name="id" value="<?= $grade['id'] ?>">
                                                    <input type="hidden" name="ders_id" value="<?= $grade['ders_id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Sil">🗑️</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="footer">
            <p>© 2026 <strong>Bootcamp</strong>. Bütün hakları saklıdır.</p>
            <div class="footer-cloud-tag">☁️ Cloud Native Architecture (GKE & Docker)</div>
        </footer>
    </main>

</body>
</html>
