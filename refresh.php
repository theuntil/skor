<?php
// Veri yenileme endpoint'i (manuel tetikleme)
// Güvenlik: REFRESH_TOKEN env değişkeni set edilmişse, ?token=... eşleşmeden çalışmaz.
// Bu sayede herkesin bu endpoint'i çağırıp TFF'yi gereksiz yormasının önüne geçiliyor.

header('Content-Type: application/json; charset=utf-8');

$expectedToken = getenv('REFRESH_TOKEN');
if ($expectedToken) {
    $providedToken = $_GET['token'] ?? '';
    if (!hash_equals($expectedToken, $providedToken)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz veya eksik token.']);
        exit;
    }
}

try {
    // Mevcut scrape script'ini dahil et (sadece TFFScraper sınıfını tanımlar, otomatik çalıştırmaz)
    require_once __DIR__ . '/scrape_tff.php';

    // Tek scraper oluştur ve çalıştır
    $scraper = new TFFScraper();
    $data = $scraper->scrape();

    // Şüpheli derecede boş veri gelirse (TFF erişimi geçici engellenmiş
    // olabilir), eldeki sağlıklı veriyi ezmiyoruz.
    $existingFile = __DIR__ . '/data/tff_superlig_data.php';
    $existingFixtureCount = 0;
    if (file_exists($existingFile)) {
        include $existingFile;
        if (isset($tff_superlig_data['fixtures'])) {
            $existingFixtureCount = count($tff_superlig_data['fixtures']);
        }
    }

    $newFixtureCount = count($data['fixtures']);
    $newStandingCount = count($data['standings']);

    if ($newStandingCount === 0) {
        http_response_code(502);
        echo json_encode([
            'status' => 'error',
            'message' => 'Yeni veri tamamen boş geldi (muhtemelen TFF erişimi başarısız oldu). Eski veri korundu, hiçbir şey kaydedilmedi.'
        ]);
        exit;
    }

    if ($existingFixtureCount > 10 && $newFixtureCount === 0) {
        http_response_code(502);
        echo json_encode([
            'status' => 'error',
            'message' => "Yeni fikstür verisi boş geldi ama eski veride $existingFixtureCount maç vardı. Eski fikstür verisi korundu, güvenlik amacıyla kaydetme atlandı."
        ]);
        exit;
    }

    // JSON olarak kaydet
    $scraper->saveJson();

    // PHP array olarak da kaydet
    $scraper->savePhpArray();

    // Başarılı yanıt
    echo json_encode([
        'status' => 'success',
        'message' => 'Veriler başarıyla güncellendi!',
        'stats' => [
            'fixtures' => count($data['fixtures']),
            'clubs' => count($data['clubs']),
            'standings' => count($data['standings']),
            'top_scorers' => count($data['top_scorers']),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>