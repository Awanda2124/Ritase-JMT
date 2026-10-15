<?php
// Dashboard hanya menampilkan sesi yang sedang berjalan. Jalankan dari folder app: php patch-dash.php

$root = getcwd();
$targets = [
    'ctrl' => $root.'/app/Http/Controllers/DashboardController.php',
    'view' => $root.'/resources/views/dashboard.blade.php',
];

$files = [];
foreach ($targets as $k => $p) {
    if (! is_file($p)) { fwrite(STDERR, "Tidak ada: $p\n"); exit(1); }
    $raw = file_get_contents($p);
    $files[$k] = ['path' => $p, 'crlf' => str_contains($raw, "\r\n"), 'src' => str_replace("\r\n", "\n", $raw)];
}

if (str_contains($files['ctrl']['src'], '->reportable()')) {
    fwrite(STDERR, "Sudah ter-patch (reportable ada). Berhenti.\n");
    exit(1);
}

$queryPattern = '/->withCount\(\'sessionDrivers\'\)(\s*)->latest\(\'date\'\)/';
$labelOld = <<<'EOT'
{{ $activeSession->isLive() ? 'Sesi aktif' : 'Sesi terakhir &middot; selesai' }}
EOT;
$typoOld = 'Belum adasesi aktif hari ini.';

$err = [];
if (preg_match_all($queryPattern, $files['ctrl']['src']) !== 1) { $err[] = 'query controller'; }
if (substr_count($files['view']['src'], $labelOld) !== 1) { $err[] = 'label dashboard'; }
if ($err) {
    fwrite(STDERR, "ANCHOR TIDAK COCOK: ".implode(', ', $err).". Tidak ada yang diubah.\n");
    exit(1);
}

$files['ctrl']['src'] = preg_replace_callback(
    $queryPattern,
    fn ($m) => "->withCount('sessionDrivers')".$m[1]."->reportable()".$m[1]."->latest('date')",
    $files['ctrl']['src'],
    1
);

// komentar lama (opsional; dilewati kalau teksnya beda)
$commentPattern = '/\/\/ Sesi terbaru \(bukan cuma hari ini\).*?\n\s*\/\/ walau sesi terakhir udah lewat\./s';
if (preg_match_all($commentPattern, $files['ctrl']['src']) === 1) {
    $files['ctrl']['src'] = preg_replace_callback(
        $commentPattern,
        fn () => '// Hanya sesi yang sedang berjalan (sudah dibuka, batas lapor belum lewat).',
        $files['ctrl']['src'],
        1
    );
} else {
    echo "Catatan: komentar lama di controller tidak ditemukan persis, dilewati (tidak masalah).\n";
}

$files['view']['src'] = str_replace($labelOld, 'Sesi aktif', $files['view']['src']);
if (substr_count($files['view']['src'], $typoOld) === 1) {
    $files['view']['src'] = str_replace($typoOld, 'Belum ada sesi aktif.', $files['view']['src']);
}

foreach ($files as $f) {
    copy($f['path'], $f['path'].'.bak4');
    file_put_contents($f['path'], $f['crlf'] ? str_replace("\n", "\r\n", $f['src']) : $f['src']);
}
echo "OK: dashboard hanya menampilkan sesi yang sedang berjalan. Backup: *.bak4\n";
