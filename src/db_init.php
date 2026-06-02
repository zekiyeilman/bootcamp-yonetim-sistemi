<?php

function initialize_database(PDO $pdo) {
    // 1. Drop existing tables if they exist to start clean (optional, but since we only call this if tables don't exist, we just CREATE)
    
    // Create Egitmen Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS egitmen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ad VARCHAR(50) NOT NULL,
        soyad VARCHAR(50) NOT NULL,
        eposta VARCHAR(100) NOT NULL UNIQUE,
        telefon VARCHAR(20) NOT NULL,
        uzmanlik VARCHAR(100) NOT NULL,
        deneyim_yili INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create Ogrenci Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ogrenci (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ad VARCHAR(50) NOT NULL,
        soyad VARCHAR(50) NOT NULL,
        eposta VARCHAR(100) NOT NULL UNIQUE,
        telefon VARCHAR(20) NOT NULL,
        kayit_tarihi DATE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create Bootcamp Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bootcamp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ad VARCHAR(100) NOT NULL,
        baslangic_tarihi DATE NOT NULL,
        bitis_tarihi DATE NOT NULL,
        egitmen_id INT NULL,
        FOREIGN KEY (egitmen_id) REFERENCES egitmen(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create BootcampOgrenci Table (Many-to-Many)
    $pdo->exec("CREATE TABLE IF NOT EXISTS bootcamp_ogrenci (
        bootcamp_id INT NOT NULL,
        ogrenci_id INT NOT NULL,
        PRIMARY KEY (bootcamp_id, ogrenci_id),
        FOREIGN KEY (bootcamp_id) REFERENCES bootcamp(id) ON DELETE CASCADE,
        FOREIGN KEY (ogrenci_id) REFERENCES ogrenci(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create Ders Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ad VARCHAR(100) NOT NULL,
        bootcamp_id INT NOT NULL,
        FOREIGN KEY (bootcamp_id) REFERENCES bootcamp(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create Yoklama Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS yoklama (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ders_id INT NOT NULL,
        ogrenci_id INT NOT NULL,
        tarih DATE NOT NULL,
        durum ENUM('Var', 'Yok') NOT NULL,
        FOREIGN KEY (ders_id) REFERENCES ders(id) ON DELETE CASCADE,
        FOREIGN KEY (ogrenci_id) REFERENCES ogrenci(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create Notlar Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notlar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ders_id INT NOT NULL,
        ogrenci_id INT NOT NULL,
        kategori ENUM('Sınav', 'Ödev', 'Proje') NOT NULL,
        puan INT NOT NULL,
        FOREIGN KEY (ders_id) REFERENCES ders(id) ON DELETE CASCADE,
        FOREIGN KEY (ogrenci_id) REFERENCES ogrenci(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2. Create Stored Procedures
    // First drop if exists
    $pdo->exec("DROP PROCEDURE IF EXISTS sp_OgrenciBul");
    $pdo->exec("CREATE PROCEDURE sp_OgrenciBul(IN arama_terimi VARCHAR(100))
    BEGIN
        SELECT * FROM ogrenci 
        WHERE ad LIKE CONCAT('%', arama_terimi, '%') 
           OR soyad LIKE CONCAT('%', arama_terimi, '%') 
           OR telefon LIKE CONCAT('%', arama_terimi, '%') 
           OR eposta LIKE CONCAT('%', arama_terimi, '%')
        ORDER BY id DESC;
    END");

    // 3. Create Functions
    $pdo->exec("DROP FUNCTION IF EXISTS fn_OrtalamaNot");
    $pdo->exec("CREATE FUNCTION fn_OrtalamaNot(p_ogrenci_id INT, p_bootcamp_id INT) 
    RETURNS DECIMAL(5,2)
    DETERMINISTIC
    READS SQL DATA
    BEGIN
        DECLARE v_ortalama DECIMAL(5,2);
        
        SELECT AVG(n.puan) INTO v_ortalama
        FROM notlar n
        JOIN ders d ON n.ders_id = d.id
        WHERE n.ogrenci_id = p_ogrenci_id 
          AND d.bootcamp_id = p_bootcamp_id;
          
        RETURN IFNULL(v_ortalama, 0.00);
    END");

    // 4. Create Triggers
    // Trigger: tg_Katilim_Tarih_Kontrol (Prevent future attendance)
    $pdo->exec("DROP TRIGGER IF EXISTS tg_Katilim_Tarih_Kontrol");
    $pdo->exec("CREATE TRIGGER tg_Katilim_Tarih_Kontrol
    BEFORE INSERT ON yoklama
    FOR EACH ROW
    BEGIN
        IF NEW.tarih > CURDATE() THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Hata: Gelecek bir tarihe yoklama girişi yapılamaz!';
        END IF;
    END");

    // Trigger: tg_YoklamaTekil (Prevent duplicate attendance in the same class on same day)
    $pdo->exec("DROP TRIGGER IF EXISTS tg_YoklamaTekil");
    $pdo->exec("CREATE TRIGGER tg_YoklamaTekil
    BEFORE INSERT ON yoklama
    FOR EACH ROW
    BEGIN
        IF EXISTS (
            SELECT 1 FROM yoklama 
            WHERE ders_id = NEW.ders_id 
              AND ogrenci_id = NEW.ogrenci_id 
              AND tarih = NEW.tarih
        ) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Hata: Bu öğrenciye bu ders için bugün zaten yoklama girilmiş!';
        END IF;
    END");

    // Trigger: tg_NotKontrol (Check insert grade 0-100)
    $pdo->exec("DROP TRIGGER IF EXISTS tg_NotKontrol");
    $pdo->exec("CREATE TRIGGER tg_NotKontrol
    BEFORE INSERT ON notlar
    FOR EACH ROW
    BEGIN
        IF NEW.puan < 0 OR NEW.puan > 100 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Hata: Girilen not değeri 0 ile 100 arasında olmak zorundadır!';
        END IF;
    END");

    // Trigger: tg_NotGuncelle_Kontrol (Check update grade 0-100)
    $pdo->exec("DROP TRIGGER IF EXISTS tg_NotGuncelle_Kontrol");
    $pdo->exec("CREATE TRIGGER tg_NotGuncelle_Kontrol
    BEFORE UPDATE ON notlar
    FOR EACH ROW
    BEGIN
        IF NEW.puan < 0 OR NEW.puan > 100 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Hata: Güncellenecek not değeri 0 ile 100 arasında olmak zorundadır!';
        END IF;
    END");

    // 5. Seed Initial Data
    // Insert Egitmenler
    $pdo->exec("INSERT INTO egitmen (ad, soyad, eposta, telefon, uzmanlik, deneyim_yili) VALUES
    ('Kaan', 'Öztürk', 'kaan.ozturk@bootcamp.com', '+905551112233', 'Full Stack Web Development & Cloud Architecture', 12),
    ('Merve', 'Aslan', 'merve.aslan@bootcamp.com', '+905552223344', 'Data Science & Machine Learning', 8),
    ('Can', 'Yılmaz', 'can.yilmaz@bootcamp.com', '+905553334455', 'DevOps & Kubernetes Administration', 10)");

    // Insert Ogrenciler
    $pdo->exec("INSERT INTO ogrenci (ad, soyad, eposta, telefon, kayit_tarihi) VALUES
    ('Zeki', 'Demir', 'zeki.demir@gmail.com', '+905441112233', '2026-01-10'),
    ('Ayşe', 'Aksoy', 'ayse.aksoy@hotmail.com', '+905442223344', '2026-01-15'),
    ('Burak', 'Şahin', 'burak.sahin@outlook.com', '+905443334455', '2026-02-01'),
    ('Elif', 'Kaya', 'elif.kaya@gmail.com', '+905444445566', '2026-02-10'),
    ('Deniz', 'Çelik', 'deniz.celik@gmail.com', '+905445556677', '2026-02-15')");

    // Insert Bootcampler
    $pdo->exec("INSERT INTO bootcamp (ad, baslangic_tarihi, bitis_tarihi, egitmen_id) VALUES
    ('Cloud & Kubernetes Master Bootcamp', '2026-03-01', '2026-06-30', 3),
    ('Full Stack Web Geliştirme Programı', '2026-04-01', '2026-08-31', 1),
    ('Veri Analitiği ve Yapay Zeka', '2026-05-01', '2026-09-30', 2)");

    // Match Students with Bootcampler
    $pdo->exec("INSERT INTO bootcamp_ogrenci (bootcamp_id, ogrenci_id) VALUES
    (1, 1), (1, 2), (1, 4), -- Cloud Bootcamp Students
    (2, 1), (2, 3), (2, 5), -- Full Stack Students
    (3, 2), (3, 4), (3, 5)  -- Data Science Students");

    // Insert Dersler
    $pdo->exec("INSERT INTO ders (ad, bootcamp_id) VALUES
    ('Docker Konteyner Teknolojileri', 1),
    ('Kubernetes Mimarisi ve Pod Yönetimi', 1),
    ('GKE Üzerinde CI/CD Pipeline Kurulumu', 1),
    ('HTML5, CSS3 ve Modern CSS Grid', 2),
    ('PHP ve PDO ile Güvenli Veritabanı Yönetimi', 2),
    ('Python ile Veri Analizi ve Pandas', 3),
    ('Makine Öğrenmesi Algoritmaları', 3)");

    // Seed Yoklama (Attendance) - Using past dates
    $pdo->exec("INSERT INTO yoklama (ders_id, ogrenci_id, tarih, durum) VALUES
    (1, 1, '2026-05-15', 'Var'),
    (1, 2, '2026-05-15', 'Var'),
    (1, 4, '2026-05-15', 'Yok'),
    (1, 1, '2026-05-16', 'Var'),
    (1, 2, '2026-05-16', 'Var'),
    (1, 4, '2026-05-16', 'Var'),
    
    (2, 1, '2026-05-20', 'Var'),
    (2, 2, '2026-05-20', 'Yok'),
    (2, 4, '2026-05-20', 'Var'),
    
    (4, 1, '2026-05-10', 'Var'),
    (4, 3, '2026-05-10', 'Var'),
    (4, 5, '2026-05-10', 'Yok')");

    // Seed Notlar (Grades)
    $pdo->exec("INSERT INTO notlar (ders_id, ogrenci_id, kategori, puan) VALUES
    -- Docker dersi notları
    (1, 1, 'Sınav', 85),
    (1, 2, 'Sınav', 95),
    (1, 4, 'Sınav', 45),
    (1, 1, 'Ödev', 90),
    (1, 2, 'Ödev', 100),
    (1, 4, 'Ödev', 60),
    
    -- Kubernetes dersi notları
    (2, 1, 'Proje', 88),
    (2, 2, 'Proje', 70),
    (2, 4, 'Proje', 55),
    
    -- PHP dersi notları
    (5, 1, 'Sınav', 92),
    (5, 3, 'Sınav', 78),
    (5, 5, 'Sınav', 65),
    (5, 1, 'Ödev', 95),
    (5, 3, 'Ödev', 85)");
}
