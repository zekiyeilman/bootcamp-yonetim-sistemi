<?php
require_once __DIR__ . '/config.php';

$stats = [
    'ogrenci' => 0,
    'egitmen' => 0,
    'bootcamp' => 0,
    'ders' => 0
];
$bootcampler = [];
$error_msg = null;

try {
    $db = getDB();
    
    // Fetch statistics
    $stats['ogrenci'] = $db->query("SELECT COUNT(*) FROM ogrenci")->fetchColumn();
    $stats['egitmen'] = $db->query("SELECT COUNT(*) FROM egitmen")->fetchColumn();
    $stats['bootcamp'] = $db->query("SELECT COUNT(*) FROM bootcamp")->fetchColumn();
    $stats['ders'] = $db->query("SELECT COUNT(*) FROM ders")->fetchColumn();
    
    // Fetch bootcampler with trainer name and student count
    $stmt = $db->query("
        SELECT b.id, b.ad, b.baslangic_tarihi, b.bitis_tarihi, 
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
    <title>Bootcamp Hub - Kontrol Paneli</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container">
        <!-- Header Section -->
        <div style="margin-bottom: 2.5rem;">
            <span class="badge badge-info" style="margin-bottom: 0.5rem;">Yönetim Gösterge Paneli</span>
            <h1 style="font-size: 2.5rem; background: linear-gradient(135deg, #fff 30%, var(--text-secondary) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Bootcamp Yönetim Sistemi
            </h1>
            <p style="color: var(--text-secondary); margin-top: 0.25rem;">Bootcampler, eğitmenler, öğrenciler, yoklamalar ve not süreçlerini tek bir merkezden yönetin.</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="card" style="border-color: var(--danger); margin-bottom: 2rem;">
                <h3 style="color: var(--danger); display: flex; align-items: center; gap: 0.5rem;">
                    <span>⚠️</span> Sistem Başlatma Hatası
                </h3>
                <p style="margin-top: 1rem; color: var(--text-secondary); line-height: 1.6;">
                    Veritabanı bağlantısı henüz kurulmadı veya veritabanı sunucusu başlatılıyor olabilir. GKE ortamında bu durum birkaç saniye sürebilir.
                </p>
                <div style="background: rgba(0, 0, 0, 0.3); padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 0.85rem; margin-top: 1rem; border: 1px solid rgba(255,255,255,0.05); overflow-x: auto;">
                    <?= htmlspecialchars($error_msg) ?>
                </div>
                <div style="margin-top: 1.5rem;">
                    <a href="index.php" class="btn btn-primary btn-sm">Yeniden Dene</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">🧑‍🎓</div>
                <div class="stat-info">
                    <h3><?= $stats['ogrenci'] ?></h3>
                    <p>Toplam Öğrenci</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon secondary">👨‍🏫</div>
                <div class="stat-info">
                    <h3><?= $stats['egitmen'] ?></h3>
                    <p>Aktif Eğitmen</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">🚀</div>
                <div class="stat-info">
                    <h3><?= $stats['bootcamp'] ?></h3>
                    <p>Program Sayısı</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info">📚</div>
                <div class="stat-info">
                    <h3><?= $stats['ders'] ?></h3>
                    <p>Toplam Ders Modülü</p>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Bootcamp List Card -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2>Aktif Eğitim Programları</h2>
                    <a href="bootcampler.php" class="btn btn-secondary btn-sm">Hepsini Gör</a>
                </div>
                
                <?php if (empty($bootcampler)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">Kayıtlı aktif bootcamp programı bulunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Program Adı</th>
                                    <th>Eğitmen</th>
                                    <th>Süreç</th>
                                    <th>Kayıtlı Öğrenci</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bootcampler as $bootcamp): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($bootcamp['ad']) ?></td>
                                        <td><?= htmlspecialchars($bootcamp['egitmen_ad'] ?: 'Atanmadı') ?></td>
                                        <td style="font-size: 0.85rem; color: var(--text-secondary);">
                                            <?= htmlspecialchars($bootcamp['baslangic_tarihi']) ?> / <?= htmlspecialchars($bootcamp['bitis_tarihi']) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= $bootcamp['ogrenci_sayisi'] ?> Öğrenci</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Architectural Features Card -->
            <div class="card" style="background: linear-gradient(135deg, rgba(17, 24, 39, 0.7) 0%, rgba(99, 102, 241, 0.05) 100%);">
                <h2 style="margin-bottom: 1rem;">Mühendislik & Bulut Altyapısı</h2>
                <p style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 1.5rem;">
                    Bu sistem, Google Kubernetes Engine (GKE) mimarisine ve gelişmiş ilişkisel veritabanı kurallarına göre tasarlanmıştır:
                </p>
                
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                        <span style="font-size: 1.5rem; background: rgba(99, 102, 241, 0.1); padding: 0.4rem; border-radius: 8px; color: var(--primary);">🐳</span>
                        <div>
                            <h4 style="color: var(--text-primary);">Dockerized Isolation</h4>
                            <p style="color: var(--text-secondary); font-size: 0.85rem;">PHP & Apache ve MySQL veritabanı tamamen yalıtılmış containerlar olarak yapılandırılmıştır.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                        <span style="font-size: 1.5rem; background: rgba(168, 85, 247, 0.1); padding: 0.4rem; border-radius: 8px; color: var(--secondary);">☸️</span>
                        <div>
                            <h4 style="color: var(--text-primary);">GKE Self-Healing & Scaling</h4>
                            <p style="color: var(--text-secondary); font-size: 0.85rem;">Liveness & Readiness probe'ları sayesinde Kubernetes podları sürekli denetlenir, çökmeler otomatik giderilir.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                        <span style="font-size: 1.5rem; background: rgba(16, 185, 129, 0.1); padding: 0.4rem; border-radius: 8px; color: var(--success);">🔒</span>
                        <div>
                            <h4 style="color: var(--text-primary);">Veri Güvenliği ve Korumalar</h4>
                            <p style="color: var(--text-secondary); font-size: 0.85rem;">PDO Prepared Statements ile SQL Injection engellenmiş, POST formları ile CSRF güvenliği sağlanmıştır.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                        <span style="font-size: 1.5rem; background: rgba(6, 182, 212, 0.1); padding: 0.4rem; border-radius: 8px; color: var(--info);">⚡</span>
                        <div>
                            <h4 style="color: var(--text-primary);">Veritabanı Tetikleyicileri (Triggers)</h4>
                            <p style="color: var(--text-secondary); font-size: 0.85rem;">İş kuralları triggers (`tg_Katilim_Tarih_Kontrol`, `tg_NotKontrol`) ve saklı yordamlar (`sp_OgrenciBul`) ile DB seviyesinde kontrol edilir.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="footer">
            <p>© 2026 <strong>Bootcamp Hub</strong>. Bütün hakları saklıdır.</p>
            <div class="footer-cloud-tag">☁️ Cloud Native Architecture (GKE & Docker)</div>
        </footer>
    </main>

</body>
</html>
