# Bootcamp Yönetim Sistemi - Final Projesi

Bu proje, bir Bootcamp Yönetim Sistemi (PHP/MySQL) web uygulamasının Dockerize edilmesi ve Google Kubernetes Engine (GKE) üzerinde modern DevOps pratikleri kullanılarak ayağa kaldırılması aşamalarını içermektedir. Proje, dersin final yönergesindeki tüm gereksinimleri (Deployment, Service, Scaling, Rolling Update, Rollback, PV/PVC, NetworkPolicy ve CI/CD) karşılayacak şekilde tasarlanmıştır.

## İçindekiler
- [Proje Mimarisi](#proje-mimarisi)
- [Zorunlu Kriterlerin Karşılanması](#zorunlu-kriterlerin-karşılanması)
  - [1. Docker ve Uygulama Dosyaları](#1-docker-ve-uygulama-dosyaları)
  - [2. Kubernetes Ortamı (GKE)](#2-kubernetes-ortamı-gke)
  - [3. Deployment ve Service](#3-deployment-ve-service)
  - [4. Scaling (Ölçeklendirme)](#4-scaling-ölçeklendirme)
  - [5. Rolling Update (Kesintisiz Güncelleme)](#5-rolling-update-kesintisiz-güncelleme)
  - [6. Rollback (Geri Alma)](#6-rollback-geri-alma)
  - [7. Persistent Volume & PVC](#7-persistent-volume--pvc)
  - [8. NetworkPolicy](#8-networkpolicy)
  - [9. CI/CD Pipeline](#9-cicd-pipeline)

---

## Proje Mimarisi

- **Frontend / Backend:** PHP tabanlı Bootcamp Yönetim web uygulaması (`src/` klasörü).
- **Veritabanı:** MySQL 8.0.
- **Containerization:** Docker (App için özel Dockerfile yazılmıştır).
- **Orchestration:** Google Kubernetes Engine (GKE).
- **CI/CD:** GitHub Actions (GKE Artifact Registry'e push ve Cluster'a otomatik deploy).

---

## Zorunlu Kriterlerin Karşılanması

### 1. Docker ve Uygulama Dosyaları
Uygulama kodları `src/` dizininde bulunmaktadır. Uygulamanın container haline getirilmesi için `docker/Dockerfile` kullanılmış olup, bağımlılıklar ve apache konfigürasyonları bu dosya içerisinde yapılmıştır.

### 2. Kubernetes Ortamı (GKE)
Sistem Google Cloud üzerinde bir Kubernetes cluster'ında çalışacak şekilde ayarlanmıştır. İlgili imajlar Google Artifact Registry'de tutulmaktadır.

### 3. Deployment ve Service
* **Deployment:** Hem PHP uygulaması (`app-deployment.yaml`) hem de MySQL veritabanı (`mysql-deployment.yaml`) için ayrı Deployment objeleri oluşturulmuştur. 
* **Service:** PHP uygulamasını dışarı açmak için `LoadBalancer` tipinde (`app-deployment.yaml` içinde), veritabanına sadece içeriden erişim için ise `ClusterIP` tipinde (`mysql-deployment.yaml` içinde) servisler tanımlanmıştır.

### 4. Scaling (Ölçeklendirme)
Uygulamanın gelen yüke göre otomatik ölçeklenebilmesi için **Horizontal Pod Autoscaler (HPA)** kurgulanmıştır.
* `kubernetes/hpa.yaml` dosyasında pod'ların CPU kullanımı %70'i veya Memory kullanımı %80'i aştığında `php-app` podlarının sayısı minimum 2'den maksimum 5'e kadar otomatik olarak çıkarılmaktadır.

### 5. Rolling Update (Kesintisiz Güncelleme)
Uygulamada yapılan güncellemelerin kullanıcılara kesinti yaşatmadan aktarılabilmesi için `app-deployment.yaml` içerisinde `strategy: type: RollingUpdate` kullanılmıştır. Yeni bir kod commit edildiğinde, CI/CD pipeline'ı imajın SHA etiketini günceller ve GKE eski podları kapatmadan önce yeni podları ayağa kaldırarak kesintisiz geçiş sağlar.

### 6. Rollback (Geri Alma)
Hatalı bir güncelleme (hatalı imaj veya kod) production ortamına yansıdığında, Kubernetes'in yerleşik rollback mekanizması kullanılarak sistem saniyeler içerisinde önceki stabil haline döndürülebilir.

**Rollback Komutları:**
```bash
# Deployment'ın geçmiş güncellemelerini görmek için:
kubectl rollout history deployment/php-app

# Bir önceki stabil sürüme geri dönmek (Rollback) için:
kubectl rollout undo deployment/php-app

# Belirli bir revizyona geri dönmek için:
kubectl rollout undo deployment/php-app --to-revision=2
```

### 7. Persistent Volume & PVC
Veritabanındaki öğrenci, eğitmen ve kamp verilerinin pod silindiğinde veya yeniden başlatıldığında kaybolmaması için **Persistent Volume Claim (PVC)** kullanılmıştır.
* `kubernetes/pvc.yaml` ile Google Cloud'un dinamik storage yapısı üzerinden MySQL datası (`/var/lib/mysql`) kalıcı hale getirilmiştir.

### 8. NetworkPolicy
Cluster içi güvenliği sağlamak amacıyla `kubernetes/network-policy.yaml` oluşturulmuştur.
* **Güvenlik Kuralı:** MySQL veritabanına (`3306` portuna) yalnızca `app: php-app` etiketine sahip olan podlardan (PHP uygulaması) gelen trafik kabul edilir. Diğer hiçbir pod veya dış kaynak veritabanına erişemez.

### 9. CI/CD Pipeline
Sürekli Entegrasyon ve Dağıtım süreci **GitHub Actions** (`.github/workflows/main.yml`) ile otomatize edilmiştir.
* Main branch'ine yapılan her push işleminde;
  1. Docker imajı build edilir.
  2. İmaj, commit SHA'sı ve `latest` etiketiyle GKE Artifact Registry'ye pushlanır.
  3. `app-deployment.yaml` dosyasındaki imaj versiyonu güncellenir.
  4. Değişiklikler otomatik olarak Kubernetes Cluster'ına apply edilir (`kubectl apply`).

---

## Kurulum ve Çalıştırma

Projenin Kubernetes manifestlerini sıfırdan kurmak isterseniz aşağıdaki komutları kullanabilirsiniz:

```bash
# 1. Secret'ları yükleyin (Veritabanı şifreleri vs.)
kubectl apply -f kubernetes/secret.yaml

# 2. Volume yapılandırmalarını yükleyin
kubectl apply -f kubernetes/pvc.yaml

# 3. Network Policy kurallarını uygulayın
kubectl apply -f kubernetes/network-policy.yaml

# 4. Veritabanını ayağa kaldırın
kubectl apply -f kubernetes/mysql-deployment.yaml

# 5. Web uygulamasını ve HPA'yı ayağa kaldırın
kubectl apply -f kubernetes/app-deployment.yaml
kubectl apply -f kubernetes/hpa.yaml
```

Servislerin durumunu izlemek için:
```bash
kubectl get pods
kubectl get svc
kubectl get hpa
```
Tebrikler, uygulamanız başarıyla yayında!
