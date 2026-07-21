<?php
/**
 * TFF Süper Lig API
 *
 * Kullanım:
 * - Tüm veri: api.php
 * - Hafta filtresi: api.php?week=18
 * - Sadece puan cetveli: api.php?type=standings
 * - Sadece fikstür: api.php?type=fixtures
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

/**
 * Gerçek istemci IP'sini bulur. Dokploy/Traefik gibi reverse proxy arkasında
 * REMOTE_ADDR proxy'nin kendi iç IP'si olabileceği için X-Forwarded-For'a bakar.
 */
function tffClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Basit, dosya tabanlı, IP başına sabit pencereli (fixed-window) rate limiter.
 * Harici bir servise (Redis vb.) ihtiyaç duymaz, data/ratelimit/ klasörüne yazar.
 * ÖNEMLİ: API_KEY tüm mobil app kullanıcıları arasında paylaşılan tek bir
 * sabit değer olduğu için limiti key'e göre değil IP'ye göre uyguluyoruz;
 * aksi halde tek bir kullanıcının yoğun kullanımı tüm app kullanıcılarını kilitler.
 */
function tffRateLimit($identifier, $maxRequests, $windowSeconds) {
    $rlDir = __DIR__ . '/data/ratelimit';
    if (!is_dir($rlDir)) {
        @mkdir($rlDir, 0775, true);
    }

    $safeId = preg_replace('/[^a-zA-Z0-9_.:]/', '_', $identifier);
    $bucket = (int) floor(time() / $windowSeconds);
    $file = $rlDir . '/' . $safeId . '_' . $bucket . '.count';

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return true; // dosya sistemiyle ilgili beklenmedik bir sorun API'yi kilitlemesin
    }

    flock($fp, LOCK_EX);
    $count = (int) fread($fp, 32);
    $count++;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string) $count);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    // Eski bucket dosyalarını arada bir temizle (her istekte değil, %2 ihtimalle)
    if (mt_rand(1, 50) === 1) {
        foreach ((glob($rlDir . '/*.count') ?: []) as $oldFile) {
            if (filemtime($oldFile) < time() - ($windowSeconds * 10)) {
                @unlink($oldFile);
            }
        }
    }

    return $count <= $maxRequests;
}

/**
 * team_id'ye karşılık gelen logo dosyası logos/ klasöründe varsa tam URL döner,
 * yoksa null döner. Dosya varlığı kontrolü lokal disk üzerinden yapılır (hızlı),
 * gerçek indirme/istek yapılmaz.
 */
function tffLogoUrl($teamId) {
    if (empty($teamId)) {
        return null;
    }
    $logoPath = __DIR__ . '/logos/' . $teamId . '.png';
    if (!file_exists($logoPath)) {
        return null;
    }
    $scheme = !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        ? $_SERVER['HTTP_X_FORWARDED_PROTO']
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$scheme://$host/logos/$teamId.png";
}

/**
 * Bir dizideki her kayda, $idKey alanındaki team_id'ye göre logo_url ekler.
 */
function tffAttachLogos(array $items, string $idKey) {
    foreach ($items as &$item) {
        $teamId = $item[$idKey] ?? null;
        $item['logo_url'] = tffLogoUrl($teamId);
    }
    unset($item);
    return $items;
}
header('Cache-Control: public, max-age=300'); // 5 dk CDN/proxy cache - veri günde birkaç kez güncellendiği için agresif cache güvenli

$dataFile = __DIR__ . '/data/tff_superlig_data.php';

// Basit health-check endpoint'i (key gerekmez - Docker/Dokploy healthcheck bunu kullanır)
if (isset($_GET['health'])) {
    echo json_encode(['status' => 'ok', 'time' => date('c')]);
    exit;
}

// ---- API Key koruması ----
// API_KEY env değişkeni set edilmişse, header (X-API-Key) veya query param (?api_key=)
// ile eşleşen bir key gönderilmeden veriye erişilemez.
$expectedApiKey = getenv('API_KEY');
if ($expectedApiKey) {
    $providedApiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
    if (!hash_equals($expectedApiKey, $providedApiKey)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz veya eksik API key.']);
        exit;
    }
}

// ---- Rate limiting (IP başına, dakika penceresi) ----
// RATE_LIMIT_MAX ve RATE_LIMIT_WINDOW env değişkenleriyle ayarlanabilir.
// Varsayılan: dakikada 60 istek. Aşılırsa 429 döner.
$rateLimitMax = (int) (getenv('RATE_LIMIT_MAX') ?: 60);
$rateLimitWindow = (int) (getenv('RATE_LIMIT_WINDOW') ?: 60);
if ($rateLimitMax > 0) {
    $clientIp = tffClientIp();
    if (!tffRateLimit($clientIp, $rateLimitMax, $rateLimitWindow)) {
        http_response_code(429);
        header('Retry-After: ' . $rateLimitWindow);
        echo json_encode([
            'status' => 'error',
            'message' => "Çok fazla istek gönderildi. Lütfen $rateLimitWindow saniye sonra tekrar deneyin."
        ]);
        exit;
    }
}

if (!file_exists($dataFile)) {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'Veri dosyası henüz oluşturulmadı. İlk scrape işlemi tamamlanmamış olabilir.'
    ]);
    exit;
}

include $dataFile;

if (!isset($tff_superlig_data) || !is_array($tff_superlig_data)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Veri dosyası okunamadı veya bozuk.']);
    exit;
}

// URL parametrelerini al
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$week = isset($_GET['week']) ? (int)$_GET['week'] : null;
$format = isset($_GET['format']) ? $_GET['format'] : 'json';

// Veri filtresi
$response = [];

switch ($type) {
    case 'fixtures':
        if ($week !== null) {
            $response = array_filter($tff_superlig_data['fixtures'], function($match) use ($week) {
                return $match['week'] === $week;
            });
            $response = array_values($response); // Index'leri sıfırla
        } else {
            $response = $tff_superlig_data['fixtures'];
        }
        foreach ($response as &$match) {
            $match['home_logo_url'] = tffLogoUrl($match['home_id'] ?? null);
            $match['away_logo_url'] = tffLogoUrl($match['away_id'] ?? null);
        }
        unset($match);
        break;

    case 'standings':
        $response = tffAttachLogos($tff_superlig_data['standings'], 'team_id');
        break;

    case 'clubs':
        $response = tffAttachLogos($tff_superlig_data['clubs'], 'id');
        break;

    case 'top_scorers':
        $response = $tff_superlig_data['top_scorers'];
        break;

    case 'stats':
        $response = [
            'total_fixtures' => count($tff_superlig_data['fixtures']),
            'total_clubs' => count($tff_superlig_data['clubs']),
            'total_standings' => count($tff_superlig_data['standings']),
            'total_scorers' => count($tff_superlig_data['top_scorers']),
            'weeks_count' => count(array_unique(array_column($tff_superlig_data['fixtures'], 'week'))),
            'last_update' => $tff_superlig_data['meta']['scraped_at']
        ];
        break;

    default:
        $response = $tff_superlig_data;
        $response['standings'] = tffAttachLogos($response['standings'], 'team_id');
        $response['clubs'] = tffAttachLogos($response['clubs'], 'id');
        unset($response['raw_html']); // raw_html gereksiz yere response'u şişiriyor, all response'ta dışarıda bırak
        break;
}

// JSON formatında döndür
if ($format === 'json') {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    // Plain text format
    echo "<pre>" . print_r($response, true) . "</pre>";
}
?>