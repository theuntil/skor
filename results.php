<?php
// TFF Süper Lig verilerini dahil et
include __DIR__ . '/data/tff_superlig_data.php';

// Verileri işle
$fixturesByWeek = [];
foreach ($tff_superlig_data['fixtures'] as $fixture) {
    $week = $fixture['week'];
    if (!isset($fixturesByWeek[$week])) {
        $fixturesByWeek[$week] = [];
    }
    $fixturesByWeek[$week][] = $fixture;
}
ksort($fixturesByWeek);

// Kulüp ID'lerini indexle
$clubsById = [];
foreach ($tff_superlig_data['clubs'] as $club) {
    $clubsById[$club['id']] = $club;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="Content-Language" content="tr">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TFF Süper Lig - Fikstür ve Puan Cetveli</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            font-size: 14px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }

        .header h1 {
            color: #1e293b;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            text-align: center;
        }

        .header-info {
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            justify-content: center;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px 20px;
            text-align: center;
            min-width: 120px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 2px;
        }

        .stat-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tabs {
            margin-bottom: 24px;
        }

        .tab-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            justify-content: center;
        }

        .tab-button {
            padding: 6px 12px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            color: #64748b;
            transition: all 0.2s;
            font-size: 12px;
            min-width: 35px;
        }

        .tab-button:hover {
            background: #e2e8f0;
            color: #334155;
        }

        .tab-button.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .tab-content {
            display: none;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
            margin-top: 8px;
        }

        .tab-content.active {
            display: block;
        }

        .clubs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        .club-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .club-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .club-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .club-info {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .fixtures-container {
            max-height: 600px;
            overflow-y: auto;
        }

        .week-section {
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
            padding-left: 20px;
        }

        .week-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
            background: #ecf0f1;
            padding: 10px 15px;
            border-radius: 8px;
        }

        .fixture {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .fixture-teams {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .team {
            flex: 1;
            text-align: center;
            font-weight: 500;
        }

        .score {
            text-align: center;
            min-width: 80px;
        }

        .score strong {
            color: #e74c3c;
            font-size: 1.2em;
        }

        .vs {
            color: #7f8c8d;
            font-weight: bold;
        }

        .standings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .standings-table th,
        .standings-table td {
            padding: 12px 8px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }

        .standings-table th {
            background: #3498db;
            color: white;
            font-weight: 600;
        }

        .standings-table tr:nth-child(even) {
            background: #f8f9fa;
        }

        .standings-table tr:hover {
            background: #e8f4fd;
        }

        .footer {
            background: rgba(255, 255, 255, 0.9);
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            margin-top: 30px;
        }

        .refresh-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            margin-top: 15px;
            transition: background 0.3s;
        }

        .refresh-btn:hover {
            background: #2980b9;
        }

        .refresh-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }

        .scorers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .scorer-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .scorer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .scorer-rank {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8em;
        }

        .top-1 .scorer-rank { background: #f1c40f; color: #2c3e50; }
        .top-2 .scorer-rank { background: #95a5a6; color: white; }
        .top-3 .scorer-rank { background: #e67e22; color: white; }

        .scorer-name {
            font-size: 1.1em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .scorer-team {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 8px;
        }

        .scorer-goals {
            font-size: 1.2em;
            font-weight: bold;
            color: #e74c3c;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 40px;
        }

        @media (max-width: 768px) {
            .stats {
                flex-direction: column;
                gap: 15px;
            }

            .clubs-grid {
                grid-template-columns: 1fr;
            }

            .fixture-teams {
                flex-direction: column;
                gap: 10px;
            }

            .standings-table {
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>TFF Süper Lig Verileri</h1>
            <p><strong>Kaynak:</strong> <?php echo $tff_superlig_data['meta']['source']; ?></p>
            <p><strong>Son Güncelleme:</strong> <?php echo $tff_superlig_data['meta']['scraped_at']; ?></p>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($tff_superlig_data['fixtures']); ?></div>
                    <div class="stat-label">Toplam Fikstür</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($tff_superlig_data['clubs']); ?></div>
                    <div class="stat-label">Kulüp</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($tff_superlig_data['standings']); ?></div>
                    <div class="stat-label">Puan Cetveli</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($fixturesByWeek); ?></div>
                    <div class="stat-label">Hafta</div>
                </div>
            </div>
        </div>

        <!-- Fikstürler -->
        <div class="tabs">
            <div class="tab-nav" id="week-nav">
                <?php for ($i = 1; $i <= 34; $i++): ?>
                    <button class="tab-button <?php echo $i === 18 ? 'active' : ''; ?>" onclick="showWeek(<?php echo $i; ?>)">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
            </div>

            <?php foreach ($fixturesByWeek as $week => $weekFixtures): ?>
                <div class="tab-content <?php echo $week === 18 ? 'active' : ''; ?>" id="week-<?php echo $week; ?>">
                    <div class="table-container">
                        <div class="table-header">
                            <?php echo $week; ?>. Hafta Fikstürü
                        </div>
                        <table class="standings-table">
                            <thead>
                                <tr>
                                    <th style="width: 45%; text-align: right;">Ev Sahibi</th>
                                    <th style="width: 10%;">Skor</th>
                                    <th style="width: 45%; text-align: left;">Deplasman</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weekFixtures as $fixture): ?>
                                    <tr>
                                        <td style="text-align: right; padding-right: 20px;">
                                            <?php echo htmlspecialchars($fixture['home_team']); ?>
                                        </td>
                                        <td style="text-align: center; font-weight: bold; color: #2563eb;">
                                            <?php if (isset($fixture['home_score']) && isset($fixture['away_score']) && $fixture['status'] === 'completed'): ?>
                                                <?php echo $fixture['home_score']; ?> - <?php echo $fixture['away_score']; ?>
                                            <?php else: ?>
                                                vs
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: left; padding-left: 20px;">
                                            <?php echo htmlspecialchars($fixture['away_team']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Puan Cetveli -->
        <!-- Puan Cetveli -->
        <div class="tabs">
            <div class="tab-nav">
                <button class="tab-button active" onclick="showTable('standings')">Puan Cetveli</button>
                <button class="tab-button" onclick="showTable('clubs')">Kulüpler</button>
                <button class="tab-button" onclick="showTable('scorers')">Gol Krallığı</button>
            </div>

            <div class="tab-content active" id="standings">
                <div class="table-container">
                    <div class="table-header">
                        Trendyol Süper Lig 2025-2026 Sezonu Puan Cetveli
                    </div>
                    <?php if (!empty($tff_superlig_data['standings'])): ?>
                        <table class="standings-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Sıra</th>
                                    <th style="text-align: left;">Takım</th>
                                    <th style="width: 50px;">O</th>
                                    <th style="width: 50px;">G</th>
                                    <th style="width: 50px;">B</th>
                                    <th style="width: 50px;">M</th>
                                    <th style="width: 50px;">AG</th>
                                    <th style="width: 50px;">YG</th>
                                    <th style="width: 50px;">Av</th>
                                    <th style="width: 60px;">P</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tff_superlig_data['standings'] as $standing): ?>
                                    <tr>
                                        <td><?php echo $standing['position']; ?></td>
                                        <td class="team-name"><?php echo htmlspecialchars($standing['team']); ?></td>
                                        <td><?php echo $standing['played']; ?></td>
                                        <td><?php echo $standing['won']; ?></td>
                                        <td><?php echo $standing['drawn']; ?></td>
                                        <td><?php echo $standing['lost']; ?></td>
                                        <td><?php echo $standing['goals_for']; ?></td>
                                        <td><?php echo $standing['goals_against']; ?></td>
                                        <td><?php echo $standing['goal_difference']; ?></td>
                                        <td class="points"><?php echo $standing['points']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>Puan Cetveli Verisi Yok</h3>
                            <p>Puan cetveli verisi henüz çekilemedi.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-content" id="clubs">
                    <div class="standings-table" style="margin-top: 20px;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Takım</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tff_superlig_data['clubs'] as $index => $club): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td class="team-name"><?php echo htmlspecialchars($club['name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-content" id="scorers">
                <div class="standings-table" style="margin-top: 20px;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 10%;">#</th>
                                <th style="width: 50%;">Oyuncu</th>
                                <th style="width: 30%;">Takım</th>
                                <th style="width: 10%;">Gol</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ($tff_superlig_data['top_scorers'] as $scorer):
                                if ($scorer['goals'] > 0):
                            ?>
                                <tr>
                                    <td><?php echo $rank; ?></td>
                                    <td><?php echo htmlspecialchars($scorer['name']); ?></td>
                                    <td><?php echo htmlspecialchars($scorer['team']); ?></td>
                                    <td style="font-weight: bold; color: #dc2626;"><?php echo $scorer['goals']; ?></td>
                                </tr>
                            <?php
                                $rank++;
                                endif;
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div style="text-align: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color:rgb(48, 164, 218);">@slaweally</h3>
                <div style="margin-top: 10px;">
                    <a href="https://github.com/slaweally" target="_blank" style="color: #64748b; text-decoration: none; margin: 0 10px;">
                        <i style="margin-right: 5px;"></i>GitHub
                    </a>
                    <a href="https://twitter.com/slaweally" target="_blank" style="color: #64748b; text-decoration: none; margin: 0 10px;">
                        <i style="margin-right: 5px;"></i>Twitter
                    </a>
                    <a href="https://instagram.com/slaweally" target="_blank" style="color: #64748b; text-decoration: none; margin: 0 10px;">
                        <i style="margin-right: 5px;"></i>Instagram
                    </a>
                    <a href="https://linkedin.com/in/slaweally" target="_blank" style="color: #64748b; text-decoration: none; margin: 0 10px;">
                        <i style="margin-right: 5px;"></i>LinkedIn
                    </a>
                </div>
            </div>

            <div style="text-align: center;">
                <button class="refresh-btn" onclick="refreshData()">Verileri Güncelle</button>
                <br><br>
                <small style="color: #64748b;">
                    Veriler <?php echo $tff_superlig_data['meta']['scraped_at']; ?> tarihinde çekildi<br>
                    © 2025 @slaweally - TFF Süper Lig Veri Sistemi
                </small>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>Veriler Güncelleniyor...</h3>
            <p>TFF sitesinden yeni veriler çekiliyor. Lütfen bekleyin.</p>
        </div>
    </div>

    <script>
        // Sayfa yüklendiğinde istatistikleri animasyonla göster
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stat-number');

            statNumbers.forEach(stat => {
                const target = parseInt(stat.textContent);
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        stat.textContent = target;
                        clearInterval(timer);
                    } else {
                        stat.textContent = Math.floor(current);
                    }
                }, 30);
            });
        });

        // Tab değiştirme fonksiyonları
        function showWeek(weekNumber) {
            // Tüm hafta tablarından active sınıfını kaldır
            document.querySelectorAll('#week-nav .tab-button').forEach(btn => btn.classList.remove('active'));
            // Tüm hafta içeriklerinden active sınıfını kaldır
            document.querySelectorAll('.tab-content[id^="week-"]').forEach(content => content.classList.remove('active'));

            // Tıklanan butonu active yap
            event.target.classList.add('active');
            // İlgili içeriği göster
            document.getElementById('week-' + weekNumber).classList.add('active');
        }

        function showTable(tableName) {
            // Tüm tab butonlarından active sınıfını kaldır (sadece alt tablar için)
            document.querySelectorAll('.tabs .tab-nav:not(#week-nav) .tab-button').forEach(btn => btn.classList.remove('active'));
            // Tüm tab içeriklerinden active sınıfını kaldır (sadece alt tablar için)
            document.querySelectorAll('.tab-content:not([id^="week-"])').forEach(content => content.classList.remove('active'));

            // Tıklanan butonu active yap
            event.target.classList.add('active');
            // İlgili içeriği göster
            document.getElementById(tableName).classList.add('active');
        }

        // Yenile butonu işlevi
        function refreshData() {
            const refreshBtn = document.querySelector('.refresh-btn');
            const loadingOverlay = document.getElementById('loadingOverlay');

            // Butonu devre dışı bırak ve loading göster
            refreshBtn.disabled = true;
            refreshBtn.textContent = 'Güncelleniyor...';
            loadingOverlay.style.display = 'flex';

            // AJAX ile veri yenile
            fetch('refresh.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Başarılı mesajı göster
                    alert('Veriler başarıyla güncellendi!\n\n' +
                          'Yeni İstatistikler:\n' +
                          '• Fikstür: ' + data.stats.fixtures + '\n' +
                          '• Kulüp: ' + data.stats.clubs + '\n' +
                          '• Puan Cetveli: ' + data.stats.standings + '\n' +
                          '• Gol Krallığı: ' + data.stats.top_scorers + '\n\n' +
                          'Sayfa yenileniyor...');

                    // Sayfayı yenile
                    window.location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Hata:', error);
                alert('Bağlantı hatası! Lütfen tekrar deneyin.');
            })
            .finally(() => {
                // Loading'i gizle ve butonu etkinleştir
                loadingOverlay.style.display = 'none';
                refreshBtn.disabled = false;
                refreshBtn.textContent = 'Verileri Güncelle';
            });
        }

        // Yenile butonuna tıklama olayı ekle
        document.addEventListener('DOMContentLoaded', function() {
            const refreshBtn = document.querySelector('.refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirm('Verileri güncellemek istiyor musunuz? Bu işlem birkaç dakika sürebilir.')) {
                        refreshData();
                    }
                });
            }
        });
    </script>
</body>
</html>