<?php
require_once __DIR__ . '/config.php';

$error_msg = null;
$success_msg = null;
$lessons = [];
$bootcampler = [];
$edit_lesson = null;

try {
    $db = getDB();
    $csrf_token = generate_csrf_token();

    // 1. Handle POST Requests (Add, Update, Delete)
    if (is_post_request()) {
        validate_post_csrf();
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'ekle') {
            $ad = trim($_POST['ad'] ?? '');
            $bootcamp_id = intval($_POST['bootcamp_id'] ?? 0);
            
            if (empty($ad) || $bootcamp_id === 0) {
                set_flash_message('danger', 'Lütfen tüm alanları doldurun!');
            } else {
                $stmt = $db->prepare("INSERT INTO ders (ad, bootcamp_id) VALUES (?, ?)");
                $stmt->execute([$ad, $bootcamp_id]);
                set_flash_message('success', 'Yeni ders modülü başarıyla bootcamp programına eklendi.');
                header('Location: dersler.php');
                exit();
            }
        }
        
        elseif ($action === 'guncelle') {
            $id = intval($_POST['id'] ?? 0);
            $ad = trim($_POST['ad'] ?? '');
            $bootcamp_id = intval($_POST['bootcamp_id'] ?? 0);
            
            if (empty($ad) || $bootcamp_id === 0 || $id === 0) {
                set_flash_message('danger', 'Lütfen tüm alanları doldurun!');
            } else {
                $stmt = $db->prepare("UPDATE ders SET ad = ?, bootcamp_id = ? WHERE id = ?");
                $stmt->execute([$ad, $bootcamp_id, $id]);
                set_flash_message('success', 'Ders modül bilgileri başarıyla güncellendi.');
                header('Location: dersler.php');
                exit();
            }
        }
        
        elseif ($action === 'sil') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Delete Lesson - Cascade constraints in DB will automatically delete its grades and attendance!
                $stmt = $db->prepare("DELETE FROM ders WHERE id = ?");
                $stmt->execute([$id]);
                set_flash_message('success', 'Ders modülü sistemden silindi. Bu derse ait tüm yoklama ve not verileri de cascade ile otomatik temizlendi.');
                header('Location: dersler.php');
                exit();
            }
        }
    }

    // 2. Handle GET Edit Request
    if (isset($_GET['duzenle'])) {
        $edit_id = intval($_GET['duzenle']);
        $stmt = $db->prepare("SELECT * FROM ders WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_lesson = $stmt->fetch();
    }

    // 3. Fetch Bootcampler for dropdown selection
    $bootcampler = $db->query("SELECT id, ad FROM bootcamp ORDER BY baslangic_tarihi DESC")->fetchAll();

    // 4. Fetch Lessons list with Bootcamp names
    $stmt = $db->query("
        SELECT d.id, d.ad, d.bootcamp_id, b.ad as bootcamp_ad 
        FROM ders d
        JOIN bootcamp b ON d.bootcamp_id = b.id
        ORDER BY b.ad, d.ad
    ");
    $lessons = $stmt->fetchAll();

} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ders Yönetimi - Bootcamp Hub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container">
        <!-- Header -->
        <div style="margin-bottom: 2.5rem;">
            <span class="badge badge-info" style="margin-bottom: 0.5rem;">Ders Programlama</span>
            <h1 style="font-size: 2.5rem;">📚 Ders Yönetimi</h1>
            <p style="color: var(--text-secondary);">Bootcampler altında modüler dersler oluşturabilir ve bu derslerin akademik takibini gerçekleştirebilirsiniz.</p>
        </div>

        <?php display_flash_message(); ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">⚠️ Hata: <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Add / Edit Form Card -->
            <div class="card">
                <?php if ($edit_lesson): ?>
                    <h2>Ders Modülünü Güncelle</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Mevcut ders modülünün adını ve bağlı olduğu bootcamp'i güncelleyin.</p>
                    
                    <form action="dersler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="guncelle">
                        <input type="hidden" name="id" value="<?= $edit_lesson['id'] ?>">
                        
                        <div class="form-group">
                            <label for="ad">Ders / Modül Adı</label>
                            <input type="text" name="ad" id="ad" class="form-control" value="<?= htmlspecialchars($edit_lesson['ad']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="bootcamp_id">Bağlı Olduğu Bootcamp Programı</label>
                            <select name="bootcamp_id" id="bootcamp_id" class="form-control" required>
                                <option value="">Bootcamp Seçin...</option>
                                <?php foreach ($bootcampler as $bootcamp): ?>
                                    <option value="<?= $bootcamp['id'] ?>" <?= $edit_lesson['bootcamp_id'] == $bootcamp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bootcamp['ad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                            <a href="dersler.php" class="btn btn-secondary">İptal</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h2>Yeni Ders Modülü Ekle</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Mevcut bir bootcamp programının altına spesifik bir ders/modül tanımlayın.</p>
                    
                    <form action="dersler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="ekle">
                        
                        <div class="form-group">
                            <label for="ad">Ders / Modül Adı</label>
                            <input type="text" name="ad" id="ad" class="form-control" placeholder="Örn: Docker Konteyner Teknolojileri" required>
                        </div>
                        <div class="form-group">
                            <label for="bootcamp_id">Hangi Bootcamp Altında?</label>
                            <select name="bootcamp_id" id="bootcamp_id" class="form-control" required>
                                <option value="">Bootcamp Seçin...</option>
                                <?php foreach ($bootcampler as $bootcamp): ?>
                                    <option value="<?= $bootcamp['id'] ?>"><?= htmlspecialchars($bootcamp['ad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Ders Modülünü Ekle</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Lesson List Card -->
            <div class="card">
                <h2>Tanımlı Ders Modülleri</h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Sistemde kayıtlı aktif dersler ve bağlı oldukları ana eğitim programları.</p>

                <?php if (empty($lessons)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">Henüz tanımlanmış ders modülü bulunmamaktadır.</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ders Modülü</th>
                                    <th>Bağlı Bootcamp</th>
                                    <th style="text-align: right;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lessons as $lesson): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?= htmlspecialchars($lesson['ad']) ?>
                                            </div>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">Ders ID: #<?= $lesson['id'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info" style="font-size: 0.75rem; font-weight: 700;"><?= htmlspecialchars($lesson['bootcamp_ad']) ?></span>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 0.5rem; justify-content: flex-end;">
                                                <a href="dersler.php?duzenle=<?= $lesson['id'] ?>" class="btn btn-secondary btn-sm">✏️ Düzenle</a>
                                                
                                                <form action="dersler.php" method="POST" onsubmit="return confirm('Bu dersi silmek istediğinize emin misiniz? Dersi sildiğinizde bu derse ait tüm yoklama ve not verileri CASCADE ile kalıcı olarak silinecektir!');" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="sil">
                                                    <input type="hidden" name="id" value="<?= $lesson['id'] ?>">
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
