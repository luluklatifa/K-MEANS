<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$filename = 'katalog_gempa.csv';
$data = [];
$locations = [];

// Tanggal batas untuk filter
$startDate = strtotime('2013-01-01');
$endDate = strtotime('2023-01-31');

// Filter berdasarkan wilayah (misalnya, wilayah tertentu)
$filterRegion = isset($_GET['region']) ? $_GET['region'] : '';

if (($handle = fopen($filename, "r")) !== FALSE) {
    $header = fgetcsv($handle);
    $no = 1;
    while (($row = fgetcsv($handle)) !== FALSE) {
        $date = $row[0];
        $time = $row[1];
        $lat = (float) $row[2];
        $lon = (float) $row[3];
        $depth = $row[4];
        $mag = (float) $row[5]; // Pastikan ini adalah float
        $region = $row[6];

        // Menggabungkan tanggal dan waktu untuk filter
        $dateTime = strtotime("$date $time");

        // Memfilter data berdasarkan tanggal, magnitudo, dan wilayah
        if ($dateTime >= $startDate && $dateTime <= $endDate && $mag > 5) {
            if ($filterRegion === '' || stripos($region, $filterRegion) !== false) {
                $data[] = [
                    'no' => $no,
                    'date' => $date,
                    'time' => $time,
                    'lat' => $lat,
                    'lon' => $lon,
                    'depth' => $depth,
                    'mag' => $mag,
                    'region' => $region
                ];

                $locations[] = [
                    'lat' => $lat,
                    'lon' => $lon,
                    'info' => "📍 <strong>{$region}</strong>
🕒 {$date} {$time}
📏 Magnitudo: {$mag}",
                    'index' => $no - 1
                ];

                $no++;
            }
        }
    }
    fclose($handle);
}

// K-Means Clustering
function kMeans($data, $k) {
    // Inisialisasi centroid secara acak
    $centroids = [];
    for ($i = 0; $i < $k; $i++) {
        $centroids[] = $data[array_rand($data)];
    }

    $clusters = [];
    $prevCentroids = [];

    do {
        // Reset clusters
        $clusters = array_fill(0, $k, []);

        // Penugasan cluster
        foreach ($data as $point) {
            $distances = [];
            foreach ($centroids as $centroid) {
                $distance = sqrt(pow($point['lat'] - $centroid['lat'], 2) + pow($point['lon'] - $centroid['lon'], 2));
                $distances[] = $distance;
            }
            $closestCentroid = array_search(min($distances), $distances);
            $clusters[$closestCentroid][] = $point;
        }

        // Simpan centroid sebelumnya
        $prevCentroids = $centroids;

        // Perbarui centroid
        foreach ($clusters as $index => $cluster) {
            if (count($cluster) > 0) {
                $latSum = 0;
                $lonSum = 0;
                foreach ($cluster as $point) {
                    $latSum += $point['lat'];
                    $lonSum += $point['lon'];
                }
                $centroids[$index] = [
                    'lat' => $latSum / count($cluster),
                    'lon' => $lonSum / count($cluster)
                ];
            }
        }
    } while ($centroids !== $prevCentroids); // Ulangi sampai centroid tidak berubah

    return $clusters;
}

// Menjalankan K-Means
$k = 3; // Misalnya kita ingin 3 cluster
$points = array_map(function($item) {
    return ['lat' => $item['lat'], 'lon' => $item['lon']];
}, $data);

$clusters = kMeans($points, $k);

// Menambahkan cluster ke data
foreach ($clusters as $index => $cluster) {
    foreach ($cluster as $point) {
        foreach ($data as $key => $d) {
            if ($d['lat'] == $point['lat'] && $d['lon'] == $point['lon']) {
                $data[$key]['cluster'] = $index; // Menandai cluster
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Gempa BMKG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            height: 500px;
            margin-top: 30px;
            border-radius: 15px;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    <h2 class="mb-4 text-center">Data Gempa BMKG (2013 - 2023)</h2>

    <div class="mb-3 text-end">
        <form method="GET" class="d-inline">
            <input type="text" name="region" placeholder="Filter berdasarkan wilayah" class="form-control d-inline" style="width: auto; display: inline-block;">
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>

    <div class="table-responsive">
        <table id="tabel-gempa" class="table table-bordered table-striped table-hover">
            <thead class="table-dark text-center">
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Lintang</th>
                    <th>Bujur</th>
                    <th>Magnitude</th>
                    <th>Kedalaman</th>
                    <th>Wilayah</th>
                    <th>Cluster</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $i => $d): ?>
                    <tr data-index="<?= $i ?>">
                        <td class="text-center"><?= $d['no'] ?></td>
                        <td><?= $d['date'] ?></td>
                        <td><?= $d['time'] ?></td>
                        <td><?= $d['lat'] ?></td>
                        <td><?= $d['lon'] ?></td>
                        <td class="text-center"><strong><?= $d['mag'] ?></strong></td>
                        <td><?= $d['depth'] ?></td>
                        <td><?= $d['region'] ?></td>
                        <td class="text-center"><?= isset($d['cluster']) ? $d['cluster'] : 'N/A' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Keterangan Cluster -->
    <div class="mt-4">
        <h5>Keterangan Cluster:</h5>
        <ul>
            <li><span style="color: #FF0000;">●</span> Cluster 0: Aktivitas tinggi (gempa dengan magnitudo besar)</li>
            <li><span style="color: #00FF00;">●</span> Cluster 1: Aktivitas sedang (gempa dengan magnitudo sedang)</li>
            <li><span style="color: #0000FF;">●</span> Cluster 2: Aktivitas rendah (gempa dengan magnitudo kecil)</li>
        </ul>
    </div>

    <!-- Peta -->
    <div id="map"></div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
$(document).ready(function () {
    const table = $('#tabel-gempa').DataTable({
        pageLength: 10, // Menampilkan 10 baris per halaman
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            zeroRecords: "Data tidak ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            infoFiltered: "(difilter dari total _MAX_ data)",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "➡",
                previous: "⬅"
            }
        }
    });

    const locations = <?= json_encode($locations) ?>;
    const map = L.map('map').setView([-2.5, 117], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const markers = [];
    const clusterColors = ['#FF0000', '#00FF00', '#0000FF']; // Warna untuk setiap cluster

    locations.forEach((loc, i) => {
        const clusterIndex = <?= json_encode(array_column($data, 'cluster')) ?>[i]; // Ambil cluster dari data
        const marker = L.marker([loc.lat, loc.lon], {
            icon: L.divIcon({
                className: 'custom-marker',
                html: `<div style="background-color: ${clusterColors[clusterIndex]}; border-radius: 50%; width: 10px; height: 10px;"></div>`,
                iconSize: [10, 10]
            })
        }).addTo(map).bindPopup(loc.info);

        marker.on('click', function () {
            map.setView([loc.lat, loc.lon], 8);
            table.$('tr.table-primary').removeClass('table-primary');
            const row = $(`#tabel-gempa tbody tr[data-index="${loc.index}"]`);
            row.addClass('table-primary');
            $('html, body').animate({
                scrollTop: row.offset().top - 100
            }, 500);
        });

        markers.push(marker);
    });

    $('#tabel-gempa tbody').on('click', 'tr', function () {
        const index = $(this).data('index');
        if (markers[index]) {
            map.setView([locations[index].lat, locations[index].lon], 8);
            markers[index].openPopup();
        }
        table.$('tr.table-primary').removeClass('table-primary');
        $(this).addClass('table-primary');
    });
});
</script>

</body>
</html>
