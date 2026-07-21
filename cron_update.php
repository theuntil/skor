<?php
/**
 * Cron Job - TFF Süper Lig Verilerini Güncelleme
 *
 * Bu dosya crontab'da günde bir defa çalıştırılmak için kullanılır.
 *
 * Crontab kullanımı:
 * 0 2 * * * /usr/bin/php /path/to/cron_update.php >> /path/to/cron.log 2>&1
 *
 * Bu komut her gün saat 02:00'da çalıştırır ve logları cron.log dosyasına yazar.
 */

// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log dosyası
$logFile = __DIR__ . '/data/cron.log';

// Loglama fonksiyonu
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Başlat
logMessage("=== TFF Veri Güncelleme Başladı ===");

// scrape_tff.php dosyasını dahil et ve çalıştır
try {
    require_once __DIR__ . '/scrape_tff.php';

    // Sadece bir kez çalıştır - duplicate çağrı önleme
    if (!isset($GLOBALS['tff_scraper_run'])) {
        $GLOBALS['tff_scraper_run'] = true;
        $scraper = new TFFScraper();
        $data = $scraper->scrape();

        // KRİTİK: scrape() veriyi sadece bellekte hazırlar, diske YAZMAZ.
        // Önceki sürümde bu iki satır eksikti — cron "başarılı" logluyordu
        // ama veri hiçbir zaman diske kaydedilmiyordu (189 gün boyunca
        // seed veri hiç değişmedi, sorun buydu).
        //
        // Ayrıca: yeni çekilen veri şüpheli derecede boşsa (TFF'ye erişim
        // geçici olarak engellenmiş/rate-limit yenmiş olabilir), eldeki
        // sağlıklı veriyi BOŞ veriyle ezmiyoruz — eski veri korunur.
        $existingFile = __DIR__ . '/data/tff_superlig_data.php';
        $existingFixtureCount = 0;
        if (file_exists($existingFile)) {
            $prev = null;
            include $existingFile; // $tff_superlig_data'yı tanımlar
            if (isset($tff_superlig_data['fixtures'])) {
                $existingFixtureCount = count($tff_superlig_data['fixtures']);
            }
        }

        $newFixtureCount = count($data['fixtures']);
        $newStandingCount = count($data['standings']);

        // Puan cetveli her zaman 18 satır olmalı; bu bile yoksa scrape
        // gerçekten başarısız olmuş demektir, hiçbir şeyi kaydetme.
        if ($newStandingCount === 0) {
            logMessage("⚠️ Yeni veri tamamen boş görünüyor (puan cetveli 0 satır) — muhtemelen TFF'ye erişim başarısız oldu. Eski veri KORUNDU, hiçbir şey üzerine yazılmadı.");
        } elseif ($existingFixtureCount > 10 && $newFixtureCount === 0) {
            logMessage("⚠️ Yeni fikstür verisi boş geldi ama eski veride $existingFixtureCount maç vardı — muhtemelen TFF'nin hafta bazlı sayfalarına erişim engellendi/rate-limit yendi. Eski fikstür verisi KORUNDU, güvenlik amacıyla kaydetme atlandı.");
        } else {
            $scraper->saveJson();
            $scraper->savePhpArray();
            logMessage("✓ Veri diske kaydedildi ($newFixtureCount maç, $newStandingCount puan cetveli satırı)");
        }
    }

    // Veri özeti
    $dataFile = __DIR__ . '/data/tff_superlig_data.php';
    if (file_exists($dataFile)) {
        include $dataFile;
        $fixtureCount = count($tff_superlig_data['fixtures']);
        $clubCount = count($tff_superlig_data['clubs']);
        $standingCount = count($tff_superlig_data['standings']);
        $scorerCount = count($tff_superlig_data['top_scorers']);

        logMessage("📊 Güncel veri: $fixtureCount maç, $clubCount kulüp, $standingCount puan, $scorerCount golcü");
    }

} catch (Exception $e) {
    logMessage("❌ Hata: " . $e->getMessage());
}

logMessage("=== TFF Veri Güncelleme Tamamlandı ===\n");
?>