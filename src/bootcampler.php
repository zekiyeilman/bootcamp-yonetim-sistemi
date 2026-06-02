<?php
require_once __DIR__ . '/config.php';

$error_msg = null;
$success_msg = null;
$bootcampler = [];
$egitmenler = [];
$ogrenciler = [];
$edit_bootcamp = null;

// Selected bootcamp for enrollment management
$selected_bootcamp_id = 0;
$assigned_students = [];

try {
    $db = getDB();
    $csrf_token = generate_csrf_token();

    // 1. Handle POST Requests (Add, Update, Delete, Assign Student, Remove Student)
    if (is_post_request()) {
        validate_post_csrf();
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'ekle') {
            $ad = trim($_POST['ad'] ?? '');
            $baslangic_tarihi = $_POST['baslangic_tarihi'] ?? '';
            $bitis_tarihi = $_POST['bitis_tarihi'] ?? '';
            $egitmen_id = !empty($_POST['egitmen_id']) ? intval($_POST['egitmen_id']) : null;
            
            if (empty($ad) || empty($baslangic_tarihi) || empty($bitis_tarihi)) {
                set_flash_message('danger', 'Lütfen program adı ve tarih alanlarını doldurun!');
            } else {
                $stmt = $db->prepare("INSERT INTO bootcamp (ad, baslangic_tarihi, bitis_tarihi, egitmen_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ad, $baslangic_tarihi, $bitis_tarihi, $egitmen_id]);
                set_flash_message('success', 'Yeni bootcamp programı başarıyla tanımlandı.');
                header('Location: bootcampler.php');
                exit();
            }
        }
        
        elseif ($action === 'guncelle') {
            $id = intval($_POST['id'] ?? 0);
            $ad = trim($_POST['ad'] ?? '');
            $baslangic_tarihi = $_POST['baslangic_tarihi'] ?? '';
            $bitis_tarihi = $_POST['bitis_tarihi'] ?? '';
            $egitmen_id = !empty($_POST['egitmen_id']) ? intval($_POST['egitmen_id']) : null;
            
            if (empty($ad) || empty($baslangic_tarihi) || empty($bitis_tarihi) || $id === 0) {
                set_flash_message('danger', 'Lütfen gerekli alanları doldurun!');
            } else {
                $stmt = $db->prepare("UPDATE bootcamp SET ad = ?, baslangic_tarihi = ?, bitis_tarihi = ?, egitmen_id = ? WHERE id = ?");
                $stmt->execute([$ad, $baslangic_tarihi, $bitis_tarihi, $egitmen_id, $id]);
                set_flash_message('success', 'Bootcamp program detayları başarıyla güncellendi.');
                header('Location: bootcampler.php');
                exit();
            }
        }
        
        elseif ($action === 'sil') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Delete Bootcamp - cascade will clear student matches, lessons, grades, and attendance!
                $stmt = $db->prepare("DELETE FROM bootcamp WHERE id = ?");
                $stmt->execute([$id]);
                set_flash_message('success', 'Bootcamp ve ilgili tüm dersler, yoklamalar ve notlar sistemden temizlendi (Cascade).');
                header('Location: bootcampler.php');
                exit();
            }
        }
        
        elseif ($action === 'ogrenci_ekle') {
            $bootcamp_id = intval($_POST['bootcamp_id'] ?? 0);
            $ogrenci_id = intval($_POST['ogrenci_id'] ?? 0);
            
            if ($bootcamp_id > 0 && $ogrenci_id > 0) {
                // Check if already assigned
                $check = $db->prepare("SELECT 1 FROM bootcamp_ogrenci WHERE bootcamp_id = ? AND ogrenci_id = ?");
                $check->execute([$bootcamp_id, $ogrenci_id]);
                if ($check->fetch()) {
                    set_flash_message('danger', 'Bu öğrenci bu bootcamp programına zaten kayıtlı!');
                } else {
                    $stmt = $db->prepare("INSERT INTO bootcamp_ogrenci (bootcamp_id, ogrenci_id) VALUES (?, ?)");
                    $stmt->execute([$bootcamp_id, $ogrenci_id]);
                    set_flash_message('success', 'Öğrenci bootcamp programına başarıyla dahil edildi.');
                }
                header('Location: bootcampler.php?yonet=' . $bootcamp_id);
                exit();
            }
        }
        
        elseif ($action === 'ogrenci_cikar') {
            $bootcamp_id = intval($_POST['bootcamp_id'] ?? 0);
            $ogrenci_id = intval($_POST['ogrenci_id'] ?? 0);
            
            if ($bootcamp_id > 0 && $ogrenci_id > 0) {
                $stmt = $db->prepare("DELETE FROM bootcamp_ogrenci WHERE bootcamp_id = ? AND ogrenci_id = ?");
                $stmt->execute([$bootcamp_id, $ogrenci_id]);
                set_flash_message('success', 'Öğrenci bootcamp programından başarıyla çıkarıldı.');
                header('Location: bootcampler.php?yonet=' . $bootcamp_id);
                exit();
            }
        }
    }

    // 2. Handle GET Edit Request
    if (isset($_GET['duzenle'])) {
        $edit_id = intval($_GET['duzenle']);
        $stmt = $db->prepare("SELECT * FROM bootcamp WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_bootcamp = $stmt->fetch();
    }

    // 3. Handle GET Enrollment Management Request
    if (isset($_GET['yonet'])) {
        $selected_bootcamp_id = intval($_GET['yonet']);
        // Fetch assigned students
        $stmt = $db->prepare("
            SELECT o.* FROM ogrenci o
            JOIN bootcamp_ogrenci bo ON o.id = bo.ogrenci_id
            WHERE bo.bootcamp_id = ?
            ORDER BY o.ad, o.soyad
        ");
        $stmt->execute([$selected_bootcamp_id]);
        $assigned_students = $stmt->fetchAll();
    }

    // 4. Fetch lists for dropdowns
    $egitmenler = $db->query("SELECT id, ad, soyad FROM egitmen ORDER BY ad, soyad")->fetchAll();
    $ogrenciler = $db->query("SELECT id, ad, soyad FROM ogrenci ORDER BY ad, soyad")->fetchAll();

    // 5. Fetch Bootcampler
    $stmt = $db->query("
        SELECT b.id, b.ad, b.baslangic_tarihi, b.bitis_tarihi, b.egitmen_id,
               CONCAT(e.ad, ' ', e.soyad) as egitmen_ad,
               (SELECT COUNT(*) FROM bootcamp_ogrenci bo WHERE bo.bootcamp_id = b.id) as ogrenci_sayisi
        FROM bootcamp b
        LEFT JOIN egitmen e ON b.egitmen_id = e.id
        ORDER BY b.baslangic_tarihi DESC
    ");
    $bootcampler = $stmt->fetchAll();

} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bootcamp Yönetimi - Bootcamp Hub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container">
        <!-- Header -->
        <div style="margin-bottom: 2.5rem;">
            <span class="badge badge-success" style="margin-bottom: 0.5rem;">Program Yönetimi</span>
            <h1 style="font-size: 2.5rem;">🚀 Bootcamp Yönetimi</h1>
            <p style="color: var(--text-secondary);">Yeni eğitim programları tanımlayın, sorumlu eğitmenler atayın ve öğrencilerin kayıt süreçlerini (Çoktan Çoğa) yönetin.</p>
        </div>

        <?php display_flash_message(); ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">⚠️ Hata: <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Add / Edit Bootcamp Card -->
            <div class="card">
                <?php if ($edit_bootcamp): ?>
                    <h2>Bootcamp Programını Güncelle</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Mevcut eğitim programının süre ve eğitmen ayarlarını güncelleyin.</p>
                    
                    <form action="bootcampler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="guncelle">
                        <input type="hidden" name="id" value="<?= $edit_bootcamp['id'] ?>">
                        
                        <div class="form-group">
                            <label for="ad">Bootcamp Adı</label>
                            <input type="text" name="ad" id="ad" class="form-control" value="<?= htmlspecialchars($edit_bootcamp['ad']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="baslangic_tarihi">Başlangıç Tarihi</label>
                            <input type="date" name="baslangic_tarihi" id="baslangic_tarihi" class="form-control" value="<?= htmlspecialchars($edit_bootcamp['baslangic_tarihi']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="bitis_tarihi">Bitiş Tarihi</label>
                            <input type="date" name="bitis_tarihi" id="bitis_tarihi" class="form-control" value="<?= htmlspecialchars($edit_bootcamp['bitis_tarihi']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="egitmen_id">Ana Eğitmen</label>
                            <select name="egitmen_id" id="egitmen_id" class="form-control">
                                <option value="">Eğitmen Seçin (İsteğe Bağlı)</option>
                                <?php foreach ($egitmenler as $egitmen): ?>
                                    <option value="<?= $egitmen['id'] ?>" <?= $edit_bootcamp['egitmen_id'] == $egitmen['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($egitmen['ad']) ?> <?= htmlspecialchars($egitmen['soyad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                            <a href="bootcampler.php" class="btn btn-secondary">İptal</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h2>Yeni Bootcamp Tanımla</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Süreç takvimi ve ana eğitmenini belirleyerek yeni bir eğitim programı açın.</p>
                    
                    <form action="bootcampler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="ekle">
                        
                        <div class="form-group">
                            <label for="ad">Bootcamp Adı</label>
                            <input type="text" name="ad" id="ad" class="form-control" placeholder="Örn: Kubernetes Master Programı" required>
                        </div>
                        <div class="form-group">
                            <label for="baslangic_tarihi">Başlangıç Tarihi</label>
                            <input type="date" name="baslangic_tarihi" id="baslangic_tarihi" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bitis_tarihi">Bitiş Tarihi</label>
                            <input type="date" name="bitis_tarihi" id="bitis_tarihi" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="egitmen_id">Sorumlu Ana Eğitmen</label>
                            <select name="egitmen_id" id="egitmen_id" class="form-control">
                                <option value="">Ana Eğitmen Seçin (Bir bootcamp'in sadece bir ana eğitmeni olabilir)</option>
                                <?php foreach ($egitmenler as $egitmen): ?>
                                    <option value="<?= $egitmen['id'] ?>"><?= htmlspecialchars($egitmen['ad']) ?> <?= htmlspecialchars($egitmen['soyad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Programı Aç</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Bootcamp List Card -->
            <div class="card">
                <h2>Mevcut Bootcamp Programları</h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Program durumlarını inceleyin ve öğrenci atama ekranlarına erişin.</p>

                <?php if (empty($bootcampler)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">Tanımlı bootcamp programı bulunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Program Adı</th>
                                    <th>Tarih & Eğitmen</th>
                                    <th style="text-align: right;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bootcampler as $bootcamp): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?= htmlspecialchars($bootcamp['ad']) ?>
                                            </div>
                                            <div style="margin-top: 0.25rem;">
                                                <span class="badge badge-info"><?= $bootcamp['ogrenci_sayisi'] ?> Öğrenci Kayıtlı</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: var(--text-primary); font-weight: 500;">
                                                👨‍🏫 <?= htmlspecialchars($bootcamp['egitmen_ad'] ?: 'Henüz Eğitmen Atanmadı') ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                                🗓️ <?= htmlspecialchars($bootcamp['baslangic_tarihi']) ?> / <?= htmlspecialchars($bootcamp['bitis_tarihi']) ?>
                                            </div>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                                <a href="bootcampler.php?yonet=<?= $bootcamp['id'] ?>" class="btn btn-success btn-sm">👥 Öğrenciler</a>
                                                <a href="bootcampler.php?duzenle=<?= $bootcamp['id'] ?>" class="btn btn-secondary btn-sm" title="Düzenle">✏️</a>
                                                
                                                <form action="bootcampler.php" method="POST" onsubmit="return confirm('Bu programı silmek istediğinize emin misiniz? Bootcamp silindiğinde içerisindeki dersler, o derslere ait tüm yoklama ve not verileri CASCADE ile kalıcı olarak silinecektir!');" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="sil">
                                                    <input type="hidden" name="id" value="<?= $bootcamp['id'] ?>">
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

        <!-- Student Assignment Section (Displays when a bootcamp is selected for management) -->
        <?php if ($selected_bootcamp_id > 0): ?>
            <?php 
                $curr_bootcamp_name = '';
                foreach ($bootcampler as $b) {
                    if ($b['id'] === $selected_bootcamp_id) {
                        $curr_bootcamp_name = $b['ad'];
                        break;
                    }
                }
            ?>
            <div class="card" style="margin-top: 2rem; border-color: var(--secondary);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <span class="badge badge-warning" style="margin-bottom: 0.5rem;">Kayıt Yönetimi</span>
                        <h2>"<?= htmlspecialchars($curr_bootcamp_name) ?>" Öğrenci Kadrosu</h2>
                        <p style="color: var(--text-secondary); font-size: 0.85rem;">Bu programa kayıtlı kursiyerleri ekleyin veya listeden çıkartın (Many-to-Many).</p>
                    </div>
                    <div>
                        <a href="bootcampler.php" class="btn btn-secondary btn-sm">Kapat</a>
                    </div>
                </div>

                <div class="grid-2" style="margin-top: 0;">
                    <!-- Add student to this bootcamp -->
                    <div>
                        <h4>Programa Öğrenci Ekle</h4>
                        <p style="color: var(--text-secondary); font-size: 0.8rem; margin-bottom: 1rem;">Sistemde kayıtlı mevcut bir öğrenciyi bu bootcamp'e dahil edin.</p>
                        
                        <form action="bootcampler.php" method="POST" class="search-box">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="ogrenci_ekle">
                            <input type="hidden" name="bootcamp_id" value="<?= $selected_bootcamp_id ?>">
                            
                            <select name="ogrenci_id" class="form-control" required style="flex-grow: 1;">
                                <option value="">Öğrenci Seçin...</option>
                                <?php foreach ($ogrenciler as $student): ?>
                                    <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['ad']) ?> <?= htmlspecialchars($student['soyad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">➕ Kaydet</button>
                        </form>
                    </div>

                    <!-- List of students in this bootcamp -->
                    <div>
                        <h4>Kayıtlı Kursiyerler (<?= count($assigned_students) ?>)</h4>
                        
                        <?php if (empty($assigned_students)): ?>
                            <p style="color: var(--text-muted); padding: 1.5rem 0; font-size: 0.9rem; text-align: center;">Bu programda henüz kayıtlı öğrenci bulunmamaktadır.</p>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Öğrenci Adı Soyadı</th>
                                            <th>İletişim</th>
                                            <th style="text-align: right;">İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_students as $student): ?>
                                            <tr>
                                                <td style="font-weight: 600;"><?= htmlspecialchars($student['ad']) ?> <?= htmlspecialchars($student['soyad']) ?></td>
                                                <td style="font-size: 0.8rem; color: var(--text-secondary);"><?= htmlspecialchars($student['eposta']) ?></td>
                                                <td style="text-align: right;">
                                                    <form action="bootcampler.php" method="POST" onsubmit="return confirm('Öğrenciyi bu programdan çıkarmak istediğinize emin misiniz?');" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="action" value="ogrenci_cikar">
                                                        <input type="hidden" name="bootcamp_id" value="<?= $selected_bootcamp_id ?>">
                                                        <input type="hidden" name="ogrenci_id" value="<?= $student['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Çıkar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <footer class="footer">
            <p>© 2026 <strong>Bootcamp Hub</strong>. Bütün hakları saklıdır.</p>
            <div class="footer-cloud-tag">☁️ Cloud Native Architecture (GKE & Docker)</div>
        </footer>
    </main>

</body>
</html>
