<?php
require_once __DIR__ . '/config.php';

$error_msg = null;
$success_msg = null;
$students = [];
$search_query = '';
$edit_student = null;

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
            $kayit_tarihi = date('Y-m-d');
            
            if (empty($ad) || empty($soyad) || empty($eposta) || empty($telefon)) {
                set_flash_message('danger', 'Lütfen tüm alanları doldurun!');
            } else {
                $stmt = $db->prepare("INSERT INTO ogrenci (ad, soyad, eposta, telefon, kayit_tarihi) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$ad, $soyad, $eposta, $telefon, $kayit_tarihi]);
                set_flash_message('success', 'Öğrenci başarıyla sisteme kaydedildi.');
                header('Location: ogrenciler.php');
                exit();
            }
        }
        
        elseif ($action === 'guncelle') {
            $id = intval($_POST['id'] ?? 0);
            $ad = trim($_POST['ad'] ?? '');
            $soyad = trim($_POST['soyad'] ?? '');
            $eposta = trim($_POST['eposta'] ?? '');
            $telefon = trim($_POST['telefon'] ?? '');
            
            if (empty($ad) || empty($soyad) || empty($eposta) || empty($telefon) || $id === 0) {
                set_flash_message('danger', 'Lütfen tüm alanları doldurun!');
            } else {
                $stmt = $db->prepare("UPDATE ogrenci SET ad = ?, soyad = ?, eposta = ?, telefon = ? WHERE id = ?");
                $stmt->execute([$ad, $soyad, $eposta, $telefon, $id]);
                set_flash_message('success', 'Öğrenci bilgileri başarıyla güncellendi.');
                header('Location: ogrenciler.php');
                exit();
            }
        }
        
        elseif ($action === 'sil') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Delete Student - Database CASCADE constraints will automatically clear attendance, grades, and bootcamp matches safely!
                $stmt = $db->prepare("DELETE FROM ogrenci WHERE id = ?");
                $stmt->execute([$id]);
                set_flash_message('success', 'Öğrenci ve ilgili tüm yoklama/not verileri sistemden güvenle temizlendi (Cascade).');
                header('Location: ogrenciler.php');
                exit();
            }
        }
    }

    // 2. Handle GET Edit Request
    if (isset($_GET['duzenle'])) {
        $edit_id = intval($_GET['duzenle']);
        $stmt = $db->prepare("SELECT * FROM ogrenci WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_student = $stmt->fetch();
    }

    // 3. Fetch Students list (Smart Search using Stored Procedure sp_OgrenciBul)
    if (isset($_GET['ara']) && trim($_GET['ara']) !== '') {
        $search_query = trim($_GET['ara']);
        // Call the Stored Procedure sp_OgrenciBul
        $stmt = $db->prepare("CALL sp_OgrenciBul(?)");
        $stmt->execute([$search_query]);
        $students = $stmt->fetchAll();
        // Close cursor to allow next queries if any
        $stmt->closeCursor();
    } else {
        $stmt = $db->query("SELECT * FROM ogrenci ORDER BY id DESC");
        $students = $stmt->fetchAll();
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
    <title>Öğrenci Yönetimi - Bootcamp</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container">
        <!-- Header -->
        <div style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="badge badge-primary" style="margin-bottom: 0.5rem;">Yönetim Paneli</span>
                <h1 style="font-size: 2.5rem;">🧑‍🎓 Öğrenci Yönetimi</h1>
                <p style="color: var(--text-secondary);">Yeni öğrenci kaydedebilir, bilgilerini güncelleyebilir, akıllı arama yapabilir ve güvenle silebilirsiniz.</p>
            </div>
        </div>

        <?php display_flash_message(); ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">⚠️ Hata: <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Add / Edit Form Card -->
            <div class="card">
                <?php if ($edit_student): ?>
                    <h2>Öğrenci Bilgilerini Güncelle</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Mevcut öğrencinin iletişim bilgilerini güncelleyin.</p>
                    
                    <form action="ogrenciler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="guncelle">
                        <input type="hidden" name="id" value="<?= $edit_student['id'] ?>">
                        
                        <div class="form-group">
                            <label for="ad">Ad</label>
                            <input type="text" name="ad" id="ad" class="form-control" value="<?= htmlspecialchars($edit_student['ad']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="soyad">Soyad</label>
                            <input type="text" name="soyad" id="soyad" class="form-control" value="<?= htmlspecialchars($edit_student['soyad']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="eposta">E-Posta</label>
                            <input type="email" name="eposta" id="eposta" class="form-control" value="<?= htmlspecialchars($edit_student['eposta']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="telefon">Telefon</label>
                            <input type="text" name="telefon" id="telefon" class="form-control" value="<?= htmlspecialchars($edit_student['telefon']) ?>" required>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                            <a href="ogrenciler.php" class="btn btn-secondary">İptal Et</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h2>Yeni Öğrenci Kaydet</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Sisteme yeni bir kursiyer kaydı ekleyin.</p>
                    
                    <form action="ogrenciler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="ekle">
                        
                        <div class="form-group">
                            <label for="ad">Ad</label>
                            <input type="text" name="ad" id="ad" class="form-control" placeholder="Öğrencinin adı" required>
                        </div>
                        <div class="form-group">
                            <label for="soyad">Soyad</label>
                            <input type="text" name="soyad" id="soyad" class="form-control" placeholder="Öğrencinin soyadı" required>
                        </div>
                        <div class="form-group">
                            <label for="eposta">E-Posta Adresi</label>
                            <input type="email" name="eposta" id="eposta" class="form-control" placeholder="ornek@domain.com" required>
                        </div>
                        <div class="form-group">
                            <label for="telefon">Telefon Numarası</label>
                            <input type="text" name="telefon" id="telefon" class="form-control" placeholder="+905xxxxxxxxx" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Öğrenciyi Sisteme Ekle</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Student List Card -->
            <div class="card">
                <h2>Kayıtlı Öğrenciler</h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.85rem;">Akıllı arama özelliğini kullanarak istediğiniz öğrenciyi anında bulun.</p>
                
                <!-- Smart Search bar -->
                <form action="ogrenciler.php" method="GET" class="search-box">
                    <input type="text" name="ara" class="form-control" placeholder="Ad, Soyad, Telefon veya E-posta..." value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit" class="btn btn-secondary">🔍 Ara</button>
                    <?php if ($search_query !== ''): ?>
                        <a href="ogrenciler.php" class="btn btn-danger" title="Aramayı Temizle">❌</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($students)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">Arama kriterlerine uygun öğrenci bulunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Öğrenci</th>
                                    <th>İletişim</th>
                                    <th>Kayıt Tarihi</th>
                                    <th style="text-align: right;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?= htmlspecialchars($student['ad']) ?> <?= htmlspecialchars($student['soyad']) ?>
                                            </div>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">ID: #<?= $student['id'] ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: var(--text-primary);"><?= htmlspecialchars($student['eposta']) ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= htmlspecialchars($student['telefon']) ?></div>
                                        </td>
                                        <td style="font-size: 0.85rem; color: var(--text-secondary);">
                                            <?= htmlspecialchars($student['kayit_tarihi']) ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 0.5rem; justify-content: flex-end;">
                                                <a href="ogrenciler.php?duzenle=<?= $student['id'] ?>" class="btn btn-secondary btn-sm" title="Bilgileri Düzenle">✏️ Düzenle</a>
                                                
                                                <!-- Safe CASCADE delete via POST method -->
                                                <form action="ogrenciler.php" method="POST" onsubmit="return confirm('Öğrenciyi silmek istediğinize emin misiniz? Bu işlem geri alınamaz! Öğrencinin katıldığı tüm bootcampler, aldığı tüm notlar ve girilen tüm yoklama bilgileri CASCADE ile kalıcı olarak silinecektir.');" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="sil">
                                                    <input type="hidden" name="id" value="<?= $student['id'] ?>">
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
            <p>© 2026 <strong>Bootcamp</strong>. Bütün hakları saklıdır.</p>
            <div class="footer-cloud-tag">☁️ Cloud Native Architecture (GKE & Docker)</div>
        </footer>
    </main>

</body>
</html>
