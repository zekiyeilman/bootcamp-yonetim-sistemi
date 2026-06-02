<?php
require_once __DIR__ . '/config.php';

$error_msg = null;
$success_msg = null;
$trainers = [];
$edit_trainer = null;

try {
    $db = getDB();
    $csrf_token = generate_csrf_token();

    // 1. Handle POST Requests (Add, Update, Delete)
    if (is_post_request()) {
        validate_post_csrf();
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'ekle') {
            $ad = trim($_POST['ad'] ?? '');
            $soyad = trim($_POST['soyad'] ?? '');
            $eposta = trim($_POST['eposta'] ?? '');
            $telefon = trim($_POST['telefon'] ?? '');
            $uzmanlik = trim($_POST['uzmanlik'] ?? '');
            $deneyim_yili = intval($_POST['deneyim_yili'] ?? 0);
            
            if (empty($ad) || empty($soyad) || empty($eposta) || empty($telefon) || empty($uzmanlik)) {
                set_flash_message('danger', 'Lütfen tüm gerekli alanları doldurun!');
            } else {
                $stmt = $db->prepare("INSERT INTO egitmen (ad, soyad, eposta, telefon, uzmanlik, deneyim_yili) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$ad, $soyad, $eposta, $telefon, $uzmanlik, $deneyim_yili]);
                set_flash_message('success', 'Eğitmen başarıyla sisteme kaydedildi.');
                header('Location: egitmenler.php');
                exit();
            }
        }
        
        elseif ($action === 'guncelle') {
            $id = intval($_POST['id'] ?? 0);
            $ad = trim($_POST['ad'] ?? '');
            $soyad = trim($_POST['soyad'] ?? '');
            $eposta = trim($_POST['eposta'] ?? '');
            $telefon = trim($_POST['telefon'] ?? '');
            $uzmanlik = trim($_POST['uzmanlik'] ?? '');
            $deneyim_yili = intval($_POST['deneyim_yili'] ?? 0);
            
            if (empty($ad) || empty($soyad) || empty($eposta) || empty($telefon) || empty($uzmanlik) || $id === 0) {
                set_flash_message('danger', 'Lütfen tüm alanları doldurun!');
            } else {
                $stmt = $db->prepare("UPDATE egitmen SET ad = ?, soyad = ?, eposta = ?, telefon = ?, uzmanlik = ?, deneyim_yili = ? WHERE id = ?");
                $stmt->execute([$ad, $soyad, $eposta, $telefon, $uzmanlik, $deneyim_yili, $id]);
                set_flash_message('success', 'Eğitmen bilgileri başarıyla güncellendi.');
                header('Location: egitmenler.php');
                exit();
            }
        }
        
        elseif ($action === 'sil') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Delete Trainer - DB foreign key setting ON DELETE SET NULL will safely handle the matching bootcamp trainers!
                $stmt = $db->prepare("DELETE FROM egitmen WHERE id = ?");
                $stmt->execute([$id]);
                set_flash_message('success', 'Eğitmen sistemden silindi. Atanmış olduğu bootcamplerin eğitmen alanları güvenle temizlendi (Set Null).');
                header('Location: egitmenler.php');
                exit();
            }
        }
    }

    // 2. Handle GET Edit Request
    if (isset($_GET['duzenle'])) {
        $edit_id = intval($_GET['duzenle']);
        $stmt = $db->prepare("SELECT * FROM egitmen WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_trainer = $stmt->fetch();
    }

    // 3. Fetch Trainers list
    $stmt = $db->query("SELECT * FROM egitmen ORDER BY id DESC");
    $trainers = $stmt->fetchAll();

} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitmen Yönetimi - Bootcamp Hub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container">
        <!-- Header -->
        <div style="margin-bottom: 2.5rem;">
            <span class="badge badge-secondary" style="margin-bottom: 0.5rem;">Yönetim Paneli</span>
            <h1 style="font-size: 2.5rem;">👨‍🏫 Eğitmen Yönetimi</h1>
            <p style="color: var(--text-secondary);">Yeni uzman eğitmenler ekleyebilir, deneyimlerini ve uzmanlıklarını takip edebilir, düzenleyebilirsiniz.</p>
        </div>

        <?php display_flash_message(); ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">⚠️ Hata: <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Add / Edit Form Card -->
            <div class="card">
                <?php if ($edit_trainer): ?>
                    <h2>Eğitmen Bilgilerini Güncelle</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Mevcut eğitmenin uzmanlık ve iletişim detaylarını güncelleyin.</p>
                    
                    <form action="egitmenler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="guncelle">
                        <input type="hidden" name="id" value="<?= $edit_trainer['id'] ?>">
                        
                        <div class="form-group">
                            <label for="ad">Ad</label>
                            <input type="text" name="ad" id="ad" class="form-control" value="<?= htmlspecialchars($edit_trainer['ad']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="soyad">Soyad</label>
                            <input type="text" name="soyad" id="soyad" class="form-control" value="<?= htmlspecialchars($edit_trainer['soyad']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="eposta">E-Posta</label>
                            <input type="email" name="eposta" id="eposta" class="form-control" value="<?= htmlspecialchars($edit_trainer['eposta']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="telefon">Telefon</label>
                            <input type="text" name="telefon" id="telefon" class="form-control" value="<?= htmlspecialchars($edit_trainer['telefon']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="uzmanlik">Uzmanlık Alanları</label>
                            <input type="text" name="uzmanlik" id="uzmanlik" class="form-control" value="<?= htmlspecialchars($edit_trainer['uzmanlik']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="deneyim_yili">Deneyim Süresi (Yıl)</label>
                            <input type="number" name="deneyim_yili" id="deneyim_yili" class="form-control" min="0" value="<?= intval($edit_trainer['deneyim_yili']) ?>" required>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                            <a href="egitmenler.php" class="btn btn-secondary">İptal Et</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h2>Yeni Eğitmen Ekle</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Sisteme yeni bir uzman/eğitmen kaydı girin.</p>
                    
                    <form action="egitmenler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="ekle">
                        
                        <div class="form-group">
                            <label for="ad">Ad</label>
                            <input type="text" name="ad" id="ad" class="form-control" placeholder="Eğitmenin adı" required>
                        </div>
                        <div class="form-group">
                            <label for="soyad">Soyad</label>
                            <input type="text" name="soyad" id="soyad" class="form-control" placeholder="Eğitmenin soyadı" required>
                        </div>
                        <div class="form-group">
                            <label for="eposta">E-Posta Adresi</label>
                            <input type="email" name="eposta" id="eposta" class="form-control" placeholder="ad.soyad@kurum.com" required>
                        </div>
                        <div class="form-group">
                            <label for="telefon">Telefon Numarası</label>
                            <input type="text" name="telefon" id="telefon" class="form-control" placeholder="+905xxxxxxxxx" required>
                        </div>
                        <div class="form-group">
                            <label for="uzmanlik">Uzmanlık Alanları</label>
                            <input type="text" name="uzmanlik" id="uzmanlik" class="form-control" placeholder="Örn: Kubernetes, DevOps, Python, AI" required>
                        </div>
                        <div class="form-group">
                            <label for="deneyim_yili">Deneyim Süresi (Yıl)</label>
                            <input type="number" name="deneyim_yili" id="deneyim_yili" class="form-control" min="0" placeholder="Örn: 5" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Eğitmeni Sisteme Ekle</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Trainer List Card -->
            <div class="card">
                <h2>Aktif Eğitmen Listesi</h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Sistemde bootcampleri yöneten ve ders veren aktif kadro.</p>

                <?php if (empty($trainers)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">Kayıtlı eğitmen bulunmamaktadır.</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Eğitmen</th>
                                    <th>Uzmanlık / Deneyim</th>
                                    <th>İletişim</th>
                                    <th style="text-align: right;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trainers as $trainer): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?= htmlspecialchars($trainer['ad']) ?> <?= htmlspecialchars($trainer['soyad']) ?>
                                            </div>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">Eğitmen ID: #<?= $trainer['id'] ?></span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--secondary);"><?= htmlspecialchars($trainer['uzmanlik']) ?></div>
                                            <span class="badge badge-success" style="margin-top: 0.25rem; font-size: 0.7rem; font-weight: 700;"><?= intval($trainer['deneyim_yili']) ?> Yıllık Deneyim</span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: var(--text-primary);"><?= htmlspecialchars($trainer['eposta']) ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= htmlspecialchars($trainer['telefon']) ?></div>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 0.5rem; justify-content: flex-end;">
                                                <a href="egitmenler.php?duzenle=<?= $trainer['id'] ?>" class="btn btn-secondary btn-sm">✏️ Düzenle</a>
                                                
                                                <form action="egitmenler.php" method="POST" onsubmit="return confirm('Eğitmeni silmek istediğinize emin misiniz? Atandığı tüm programların eğitmen bilgisi boş (Null) olarak güncellenecektir.');" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="sil">
                                                    <input type="hidden" name="id" value="<?= $trainer['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Sil</button>
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
            <p>© 2026 <strong>Bootcamp Hub</strong>. Bütün hakları saklıdır.</p>
            <div class="footer-cloud-tag">☁️ Cloud Native Architecture (GKE & Docker)</div>
        </footer>
    </main>

</body>
</html>
