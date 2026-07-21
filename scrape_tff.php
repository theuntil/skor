<?php

// TFF Süper Lig verilerini çekip JSON olarak kaydetme scripti

class TFFScraper {
    private $url = 'https://www.tff.org/Default.aspx?pageID=198';
    private $data = [];
    private $cookieJarPath;

    public function __construct() {
        // Her scrape çalışması kendi cookie jar'ını kullanır; sistem temp
        // dizinine yazılır, işlem bitince otomatik silinir (aşağıda destructor).
        $this->cookieJarPath = sys_get_temp_dir() . '/tff_scraper_cookies_' . uniqid() . '.txt';

        $this->data = [
            'meta' => [
                'source' => $this->url,
                'scraped_at' => date('Y-m-d H:i:s'),
                'title' => 'Trendyol Süper Lig'
            ],
            'fixtures' => [],
            'standings' => [],
            'clubs' => [],
            'top_scorers' => [],
            'raw_html' => ''
        ];
    }

    public function __destruct() {
        if ($this->cookieJarPath && file_exists($this->cookieJarPath)) {
            @unlink($this->cookieJarPath);
        }
    }

    public function scrape() {
        echo "TFF sitesinden veri çekiliyor...\n";

        // HTML içeriğini çek
        $html = $this->getHtmlContent();
        if (!$html) {
            throw new Exception("HTML içeriği çekilemedi");
        }

        $this->data['raw_html'] = $html;

        // HTML'yi parse et
        $this->parseContent($html);

        return $this->data;
    }

    private function getHtmlContent() {
        return $this->getHtmlContentFromUrl($this->url);
    }

    private function parseContent($html) {
        // Önce basit regex tabanlı parsing dene
        $this->parseFixturesRegex($html);
        $this->parseStandingsRegex($html);

        // Eğer regex ile veri bulunamadıysa DOM parsing kullan
        if (empty($this->data['fixtures']) || empty($this->data['standings'])) {
            echo "Regex parsing yetersiz, DOM parsing kullanılıyor...\n";

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);

            if (empty($this->data['fixtures'])) {
                $this->parseFixtures($xpath);
            }

            if (empty($this->data['standings'])) {
                $this->parseStandings($xpath);
            }
        }

        // Kulüp bilgilerini çıkar
        $this->parseClubsRegex($html);

        // Gol krallığı verilerini çıkar
        $this->scrapeTopScorers();
    }

    private function parseFixtures($xpath) {
        echo "Fikstür bilgileri parse ediliyor...\n";

        // Fikstür bilgilerini içeren div'leri bul
        $fixtureDivs = $xpath->query("//div[contains(@id, 'divFixture') or contains(@class, 'fixture')]");

        if ($fixtureDivs->length == 0) {
            // Alternatif olarak tüm tabloları kontrol et
            $allTables = $xpath->query("//table");

            foreach ($allTables as $table) {
                $tableContent = $table->textContent;

                // Hafta içerip içermediğini kontrol et
                if (strpos($tableContent, 'Hafta') !== false) {
                    $this->parseFixtureTable($xpath, $table);
                }
            }
        } else {
            foreach ($fixtureDivs as $div) {
                $tables = $xpath->query('.//table', $div);
                foreach ($tables as $table) {
                    $this->parseFixtureTable($xpath, $table);
                }
            }
        }
    }

    private function parseFixtureTable($xpath, $table) {
        $rows = $xpath->query('.//tr', $table);

        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);
            $cellText = trim($row->textContent);

            // Hafta başlığı olan satırları bul
            if (preg_match('/(\d+)\.Hafta/', $cellText, $matches)) {
                $currentWeek = (int)$matches[1];
                continue;
            }

            // Maç satırlarını işle (genellikle 2 hücreli)
            if ($cells->length >= 2) {
                $cell1 = trim($cells->item(0)->textContent);
                $cell2 = trim($cells->item(1)->textContent);

                // Hücrelerde takım linkleri var mı kontrol et
                $links1 = $xpath->query('.//a', $cells->item(0));
                $links2 = $xpath->query('.//a', $cells->item(1));

                if ($links1->length > 0 && $links2->length > 0) {
                    $homeTeam = trim($links1->item(0)->textContent);
                    $awayTeam = trim($links2->item(0)->textContent);

                    $homeId = $this->extractClubId($links1->item(0)->getAttribute('href'));
                    $awayId = $this->extractClubId($links2->item(0)->getAttribute('href'));

                    if (!empty($homeTeam) && !empty($awayTeam) && isset($currentWeek)) {
                        $this->data['fixtures'][] = [
                            'week' => $currentWeek,
                            'home_team' => $homeTeam,
                            'away_team' => $awayTeam,
                            'home_id' => $homeId,
                            'away_id' => $awayId
                        ];
                    }
                }
            }
        }
    }

    private function parseStandings($xpath) {
        echo "Puan cetveli parse ediliyor...\n";

        // HTML içeriğinde "Puan Cetvelleri" başlığını ara
        $content = $this->data['raw_html'];

        // Puan cetveli tablosunu regex ile bul
        if (preg_match_all('/<table[^>]*>(.*?)<\/table>/s', $content, $tableMatches)) {
            foreach ($tableMatches[0] as $tableHtml) {
                // Bu tablo puan cetveli mi kontrol et
                if (strpos($tableHtml, 'Puan Cetveli') !== false ||
                    (strpos($tableHtml, 'Sıra') !== false && strpos($tableHtml, 'Takım') !== false)) {

                    // Tabloyu DOM'a yükle
                    $tableDom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    $tableDom->loadHTML('<?xml encoding="UTF-8">' . $tableHtml);
                    libxml_clear_errors();

                    $tableXpath = new DOMXPath($tableDom);
                    $rows = $tableXpath->query('//tr');

                    foreach ($rows as $row) {
                        $cells = $tableXpath->query('.//td', $row);

                        if ($cells->length >= 6) { // Minimum kolon sayısı
                            $position = trim($cells->item(0)->textContent);
                            $team = trim($cells->item(1)->textContent);
                            $played = trim($cells->item(2)->textContent);
                            $won = trim($cells->item(3)->textContent);
                            $drawn = trim($cells->item(4)->textContent);
                            $lost = trim($cells->item(5)->textContent);

                            // Kalan kolonları kontrol et
                            $goalsFor = $cells->length > 6 ? trim($cells->item(6)->textContent) : '';
                            $goalsAgainst = $cells->length > 7 ? trim($cells->item(7)->textContent) : '';
                            $goalDifference = $cells->length > 8 ? trim($cells->item(8)->textContent) : '';
                            $points = $cells->length > 9 ? trim($cells->item(9)->textContent) : '';

                            if (is_numeric($position) && !empty($team)) {
                                $this->data['standings'][] = [
                                    'position' => (int)$position,
                                    'team' => $team,
                                    'played' => (int)$played,
                                    'won' => (int)$won,
                                    'drawn' => (int)$drawn,
                                    'lost' => (int)$lost,
                                    'goals_for' => (int)$goalsFor,
                                    'goals_against' => (int)$goalsAgainst,
                                    'goal_difference' => (int)$goalDifference,
                                    'points' => (int)$points
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    private function parseFixturesRegex($html) {
        echo "Fikstür bilgileri tüm haftalardan çekiliyor...\n";

        $totalMatches = 0;

        // Tüm haftaları çek (1'den 34'e kadar)
        for ($week = 1; $week <= 34; $week++) {
            echo "Hafta $week çekiliyor...\n";

            $weekUrl = "https://www.tff.org/Default.aspx?pageID=198&hafta=" . $week;
            $weekHtml = $this->getHtmlContentFromUrl($weekUrl);

            if (!$weekHtml) {
                echo "Hafta $week çekilemedi, devam ediliyor...\n";
                continue;
            }

            // Bu haftanın maçlarını parse et
            $weekMatches = $this->parseWeekFixtures($weekHtml, $week);
            $totalMatches += $weekMatches;

            echo "Hafta $week: $weekMatches maç bulundu.\n";

            // Kısa bir bekleme ekle (TFF'yi yormamak için)
            usleep(1200000); // 1.2 saniye — bot koruması/rate-limit riskini azaltmak için artırıldı
        }

        echo "Toplam $totalMatches fikstür çekildi.\n";
    }

    private function parseWeekFixtures($html, $weekNumber) {
        $matchCount = 0;

        // Multiple patterns for different HTML structures
        $patterns = [
            // Pattern 1: Standard TFF format (with <b> and <a> tags)
            '/<td[^>]*class="haftaninMaclariEv"[^>]*>.*?<a[^>]*href="[^"]*kulupID=(\d+)"[^>]*>.*?<span[^>]*>(.*?)<\/span>.*?<\/a>.*?<\/td>.*?<td[^>]*class="haftaninMaclariSkor"[^>]*>.*?<a[^>]*href="[^"]*macId=(\d+)"[^>]*>.*?<span[^>]*>(\d*)<\/span>\s*-\s*<span[^>]*>(\d*)<\/span>.*?<\/a>.*?<\/td>.*?<td[^>]*class="haftaninMaclariDeplasman"[^>]*>.*?<a[^>]*href="[^"]*kulupID=(\d+)"[^>]*>.*?<span[^>]*>(.*?)<\/span>.*?<\/a>/is',

            // Pattern 2: Alternative format (no span tags)
            '/<td[^>]*class="haftaninMaclariEv"[^>]*>.*?<a[^>]*href="[^"]*kulupID=(\d+)"[^>]*>(.*?)<\/a>.*?<\/td>.*?<td[^>]*class="haftaninMaclariSkor"[^>]*>.*?<a[^>]*href="[^"]*macId=(\d+)"[^>]*>(.*?)<\/a>.*?<\/td>.*?<td[^>]*class="haftaninMaclariDeplasman"[^>]*>.*?<a[^>]*href="[^"]*kulupID=(\d+)"[^>]*>(.*?)<\/a>/is'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    // Extract data based on pattern
                    if (count($match) >= 7) {
                        $homeTeamId = (int)$match[1];
                        $homeTeamName = trim(strip_tags($match[2]));
                        $matchId = (int)$match[3];

                        // Handle score differently based on pattern
                        if (isset($match[5]) && !strpos($match[4], '-')) {
                            // Pattern 1: score in separate capture groups
                            $homeScore = ($match[4] !== '') ? (int)$match[4] : null;
                            $awayScore = ($match[5] !== '') ? (int)$match[5] : null;
                            $awayTeamId = (int)$match[6];
                            $awayTeamName = trim(strip_tags($match[7]));
                        } else {
                            // Pattern 2: score is in one capture group (e.g. "2 - 1" or " - ")
                            $scoreText = trim(strip_tags($match[4]));
                            if (preg_match('/(\d+)\s*-\s*(\d+)/', $scoreText, $scoreParts)) {
                                $homeScore = (int)$scoreParts[1];
                                $awayScore = (int)$scoreParts[2];
                            } else {
                                $homeScore = null;
                                $awayScore = null;
                            }
                            $awayTeamId = (int)$match[5];
                            $awayTeamName = trim(strip_tags($match[6]));
                        }

                        // Skip if we already have this match (duplicate prevention)
                        $matchKey = $homeTeamId . '-' . $awayTeamId . '-' . $weekNumber;
                        static $processedMatches = [];
                        if (isset($processedMatches[$matchKey])) {
                            continue;
                        }
                        $processedMatches[$matchKey] = true;

                        // Maç durumunu belirle
                        $status = 'scheduled';
                        if ($homeScore !== null && $awayScore !== null && ($homeScore > 0 || $awayScore > 0)) {
                            $status = 'completed';
                        }

                        $this->data['fixtures'][] = [
                            'week' => $weekNumber,
                            'home_team' => $homeTeamName,
                            'away_team' => $awayTeamName,
                            'home_id' => $homeTeamId,
                            'away_id' => $awayTeamId,
                            'home_score' => $homeScore,
                            'away_score' => $awayScore,
                            'match_id' => $matchId,
                            'status' => $status
                        ];

                        $matchCount++;
                    }
                }
            }
        }

        return $matchCount;
    }

    private function parseStandingsRegex($html) {
        echo "Puan cetveli regex ile parse ediliyor...\n";

        // Ana sayfadaki puan cetveli bölümünü bul (ctl00_MPane_m_198_12490 pattern'i ile)
        $standingPattern = '/<a[^>]*href="Default\.aspx\?pageId=28&amp;kulupID=(\d+)"[^>]*>(\d+)\.([^<]+)<\/a><\/b><\/td>\s*<td[^>]*align="center"[^>]*>\s*<span[^>]*>(\d+)<\/span><\/td>\s*<td[^>]*align="center"[^>]*>\s*<span[^>]*>(\d+)<\/span><\/td>\s*<td[^>]*align="center"[^>]*>\s*<span[^>]*>(\d+)<\/span><\/td>\s*<td[^>]*align="center"[^>]*>\s*<span[^>]*>(\d+)<\/span><\/td>\s*<td[^>]*align="center"[^>]*>\s*<span[^>]*>(\d+)<\/span><\/td>\s*<td[^>]*align="center"[^>]*>\s*<span[^>]*>(\d+)<\/span><\/td>\s*<td[^>]*align="center"[^>]*>\s*<span[^>]*>([+-]?\d+)<\/span><\/td>\s*<td[^>]*align="center"[^>]*>\s*<b>\s*<span[^>]*>(\d+)<\/span><\/b><\/td>/is';

        if (preg_match_all($standingPattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $teamId = (int)$match[1];
                $position = (int)$match[2];
                $teamName = trim(strip_tags($match[3]));
                $played = (int)$match[4];
                $won = (int)$match[5];
                $drawn = (int)$match[6];
                $lost = (int)$match[7];
                $goalsFor = (int)$match[8];
                $goalsAgainst = (int)$match[9];
                $goalDifference = (int)$match[10];
                $points = (int)$match[11];

                $this->data['standings'][] = [
                    'position' => $position,
                    'team' => $teamName,
                    'team_id' => $teamId,
                    'played' => $played,
                    'won' => $won,
                    'drawn' => $drawn,
                    'lost' => $lost,
                    'goals_for' => $goalsFor,
                    'goals_against' => $goalsAgainst,
                    'goal_difference' => $goalDifference,
                    'points' => $points
                ];
            }
        } else {
            echo "Puan cetveli pattern'i bulunamadı!\n";
        }

        echo "Regex ile " . count($this->data['standings']) . " puan cetveli kaydı bulundu.\n";
    }

    private function parseClubsRegex($html) {
        echo "Kulüp bilgileri regex ile parse ediliyor...\n";

        // HTML link pattern'ini ara
        $pattern = '/<a[^>]*href="\/Default\.aspx\?pageID=28&kulupId=(\d+)"[^>]*>([^<]+)<\/a>/i';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            $clubs = [];

            foreach ($matches as $match) {
                $id = (int)$match[1];
                $name = trim(strip_tags($match[2]));

                if (!empty($name) && !isset($clubs[$id])) {
                    $clubs[$id] = [
                        'id' => $id,
                        'name' => $name,
                        'url' => 'https://www.tff.org/Default.aspx?pageID=28&kulupId=' . $id
                    ];
                }
            }

            $this->data['clubs'] = array_values($clubs);
        }

        echo "Regex ile " . count($this->data['clubs']) . " kulüp bulundu.\n";
    }

    private function parseClubs($xpath) {
        echo "Kulüp bilgileri DOM ile parse ediliyor...\n";

        // Tüm kulüp linklerini bul
        $clubLinks = $xpath->query("//a[contains(@href, 'kulupId=')]");

        $clubs = [];
        foreach ($clubLinks as $link) {
            $href = $link->getAttribute('href');
            $name = trim($link->textContent);

            if (preg_match('/kulupId=(\d+)/', $href, $matches)) {
                $clubId = $matches[1];

                if (!isset($clubs[$clubId])) {
                    $clubs[$clubId] = [
                        'id' => (int)$clubId,
                        'name' => $name,
                        'url' => 'https://www.tff.org/' . $href
                    ];
                }
            }
        }

        $this->data['clubs'] = array_values($clubs);
    }

    private function scrapeTopScorers() {
        echo "Gol krallığı verileri çekiliyor...\n";

        $topScorersUrl = 'https://www.tff.org/Default.aspx?pageID=821';
        $html = $this->getHtmlContentFromUrl($topScorersUrl);

        if (!$html) {
            echo "Gol krallığı sayfası çekilemedi!\n";
            return;
        }

        // DOM ile parse et
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Gol krallığı verilerini regex ile çek
        $this->parseTopScorersRegex($html);
    }

    private function parseTopScorersRegex($html) {
        echo "Gol krallığı verileri regex ile parse ediliyor...\n";

        $pattern = '/<a[^>]*href="Default\.aspx\?pageID=30&amp;kisiID=(\d+)"[^>]*>([^<]+)<\/a><\/b><br \/>\s*<a[^>]*href="Default\.aspx\?pageID=28&amp;kulupID=(\d+)"[^>]*>([^<]+)<\/a>.*?<span[^>]*>(\d+)<\/span><\/b>/is';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            $topScorers = [];

            foreach ($matches as $match) {
                $playerId = (int)$match[1];
                $playerName = trim($match[2]);
                $teamId = (int)$match[3];
                $teamName = trim($match[4]);
                $goals = (int)$match[5];

                if ($goals > 0 && !empty($playerName)) {
                    $topScorers[] = [
                        'name' => $playerName,
                        'team' => $teamName,
                        'goals' => $goals,
                        'player_id' => $playerId,
                        'team_id' => $teamId
                    ];
                }
            }

            $this->data['top_scorers'] = $topScorers;
            echo count($topScorers) . " golcü verisi çekildi.\n";
        } else {
            echo "Gol krallığı HTML pattern'i bulunamadı!\n";

            // Debug için linkleri say
            $playerLinks = preg_match_all('/pageID=30&kisiID=\d+/', $html, $dummy);
            $teamLinks = preg_match_all('/pageID=28&kulupID=\d+/', $html, $dummy);
            $spans = preg_match_all('/<span[^>]*>\d+<\/span>/', $html, $dummy);

            echo "Debug: $playerLinks oyuncu linki, $teamLinks takım linki, $spans span bulundu.\n";
        }
    }

    private function getHtmlContentFromUrl($url) {
        $ch = curl_init();

        // Aynı scrape çalışması boyunca cookie'leri koru — ASP.NET WebForms
        // (bu site öyle: __VIEWSTATE var) session/anti-bot cookie'sine
        // duyarlı olabilir. Cookie olmadan art arda 30+ istek atmak bot
        // koruması/rate-limit'e takılma riskini artırıyor.
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer: https://www.tff.org/Default.aspx?pageID=198',
        ]);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJarPath);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJarPath);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            echo "cURL Hatası ($url): " . curl_error($ch) . "\n";
            curl_close($ch);
            return false;
        }

        if ($httpCode >= 400) {
            echo "HTTP Hatası ($url): status $httpCode\n";
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        // Convert encoding from windows-1254 to UTF-8
        if ($result) {
            $result = mb_convert_encoding($result, 'UTF-8', 'windows-1254');
        }

        return $result;
    }

    private function extractClubId($href) {
        if (preg_match('/kulupId=(\d+)/', $href, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    private function fixTurkishChars($text) {
        // Since we now convert to UTF-8 at the source, 
        // we only need to handle HTML entities and basic cleanup.
        $text = trim($text);
        
        // Handle HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove zero-width spaces or other common weird chars if any
        $text = str_replace(["\u{200B}", "\u{200C}", "\u{200D}"], '', $text);

        return trim($text);
    }

    private function fixDataTurkishChars() {
        // Tüm verilerdeki Türkçe karakterleri düzelt
        foreach ($this->data['fixtures'] as &$fixture) {
            $fixture['home_team'] = $this->fixTurkishChars($fixture['home_team']);
            $fixture['away_team'] = $this->fixTurkishChars($fixture['away_team']);
        }

        foreach ($this->data['clubs'] as &$club) {
            $club['name'] = $this->fixTurkishChars($club['name']);
        }

        foreach ($this->data['standings'] as &$standing) {
            $standing['team'] = $this->fixTurkishChars($standing['team']);
        }

        foreach ($this->data['top_scorers'] as &$scorer) {
            $scorer['name'] = $this->fixTurkishChars($scorer['name']);
            $scorer['team'] = $this->fixTurkishChars($scorer['team']);
        }
    }

    public function saveJson($filename = null) {
        if ($filename === null) {
            $filename = __DIR__ . '/data/tff_superlig_data.json';
        }
        // Türkçe karakterleri düzelt
        $this->fixDataTurkishChars();

        // JSON encoding sorununu çözmek için adım adım yap
        try {
            // Büyük veriyi parçalara bölerek encode et
            $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

            // Meta veriyi encode et
            $metaJson = json_encode($this->data['meta'], $jsonOptions);

            // Fikstürleri encode et
            $fixturesJson = json_encode($this->data['fixtures'], $jsonOptions);

            // Kulüpleri encode et
            $clubsJson = json_encode($this->data['clubs'], $jsonOptions);

            // Puan cetvelini encode et
            $standingsJson = json_encode($this->data['standings'], $jsonOptions);

            // Gol krallığını encode et
            $topScorersJson = json_encode($this->data['top_scorers'], $jsonOptions);

            // Manuel JSON oluştur
            $json = "{\n";
            $json .= '  "meta": ' . $metaJson . ",\n";
            $json .= '  "fixtures": ' . $fixturesJson . ",\n";
            $json .= '  "clubs": ' . $clubsJson . ",\n";
            $json .= '  "standings": ' . $standingsJson . ",\n";
            $json .= '  "top_scorers": ' . $topScorersJson . ",\n";
            $json .= '  "raw_html": ""';
            $json .= "\n}";

            // Dosya yazma iznini kontrol et
            $dir = dirname($filename);
            if (!is_writable($dir)) {
                echo "Dizin yazılabilir değil: $dir\n";
                return false;
            }

            if (file_put_contents($filename, $json) !== false) {
                echo "JSON dosyası başarıyla kaydedildi: $filename (" . strlen($json) . " karakter)\n";
                return true;
            } else {
                echo "JSON dosyası kaydedilemedi!\n";
                return false;
            }

        } catch (Exception $e) {
            echo "JSON encoding hatası: " . $e->getMessage() . "\n";

            // Alternatif olarak sadece temel bilgileri kaydet
            $simpleData = [
                'meta' => $this->data['meta'],
                'stats' => [
                    'fixtures_count' => count($this->data['fixtures']),
                    'clubs_count' => count($this->data['clubs']),
                    'standings_count' => count($this->data['standings']),
                    'top_scorers_count' => count($this->data['top_scorers'])
                ]
            ];

            $simpleJson = json_encode($simpleData, JSON_PRETTY_PRINT);
            file_put_contents($filename . '.simple', $simpleJson);
            echo "Basit JSON dosyası kaydedildi: $filename.simple\n";

            return false;
        }
    }

    public function savePhpArray($filename = null) {
        if ($filename === null) {
            $filename = __DIR__ . '/data/tff_superlig_data.php';
        }
        // Türkçe karakterleri düzelt (Eğer saveJson çağrılmadıysa veya önlem olarak)
        $this->fixDataTurkishChars();
        
        $phpContent = "<?php\n\n// TFF Süper Lig verileri\n// Kaynak: {$this->url}\n// Çekilme tarihi: " . date('Y-m-d H:i:s') . "\n\n\$tff_superlig_data = " . var_export($this->data, true) . ";\n";

        if (file_put_contents($filename, $phpContent)) {
            echo "PHP array dosyası başarıyla kaydedildi: $filename\n";
            return true;
        } else {
            echo "PHP array dosyası kaydedilemedi!\n";
            return false;
        }
    }
}

// Script çalıştırma
// NOT: Bu blok SADECE dosya doğrudan çalıştırıldığında (CLI'dan `php scrape_tff.php`
// veya cron ile) tetiklenir. refresh.php / cron_update.php gibi dosyalar bu dosyayı
// `require_once` ile dahil ettiğinde otomatik çalışmaz; böylece aynı scrape işlemi
// yanlışlıkla iki kez tetiklenmez ve TFF sunucusuna gereksiz yük binmez.
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    try {
        $scraper = new TFFScraper();

        // Veriyi çek
        $data = $scraper->scrape();

        // JSON olarak kaydet
        $scraper->saveJson();

        // PHP array olarak da kaydet
        $scraper->savePhpArray();

        // Özet bilgi ver
        echo "\n=== VERİ ÖZETİ ===\n";
        echo "Fikstür sayısı: " . count($data['fixtures']) . "\n";
        echo "Kulüp sayısı: " . count($data['clubs']) . "\n";
        echo "Puan cetveli kayıt sayısı: " . count($data['standings']) . "\n";

    } catch (Exception $e) {
        echo "Hata: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>