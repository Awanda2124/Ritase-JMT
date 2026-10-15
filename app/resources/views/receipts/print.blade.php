<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Struk {{ $receipt->receipt_no }}</title>
    <style>
        :root { --lebar: 72mm; }
        body.paper-58 { --lebar: 48mm; }
        body.paper-a4 { --lebar: 120mm; }
        body.paper-58 { font-size: 10px; }
        body.paper-58 .copy { padding: 3mm 1.5mm; }
        body.paper-58 .besar { font-size: 13px; }
        body.paper-58 .baris span:first-child { white-space: nowrap; }
        body.paper-58 .potong, body.paper-58 .ttd > div { font-size: 9px; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #000; font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.4; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 12px; background: #0f172a; color: #fff; font-family: system-ui, sans-serif; font-size: 13px; }
        .toolbar a, .toolbar button { color: #fff; background: #334155; border: 0; border-radius: 8px; padding: 8px 12px; font: inherit; text-decoration: none; cursor: pointer; }
        .toolbar button.utama { background: #059669; font-weight: 700; }
        .toolbar .info { margin-left: auto; opacity: .8; }
        .copy { width: var(--lebar); margin: 14px auto; padding: 4mm 3mm; background: #fff; }
        .tengah { text-align: center; }
        .tebal { font-weight: 700; }
        .besar { font-size: 15px; }
        .baris { display: flex; justify-content: space-between; gap: 6px; }
        .baris span:last-child { text-align: right; }
        hr { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
        .potong { margin: 10px 0 0; padding-top: 4px; border-top: 1px dashed #000; text-align: center; font-size: 10px; }
        .ttd { display: flex; gap: 8px; margin-top: 6px; }
        .ttd > div { flex: 1; text-align: center; font-size: 11px; }
        .ttd .garis { height: 14mm; border-bottom: 1px solid #000; }
        .tanda { margin: 4px 0; padding: 3px; border: 2px solid #000; text-align: center; font-weight: 700; }
        @@media print {
            @@page { margin: 0; }
            body { background: #fff; }
            .toolbar { display: none !important; }
            .potong { display: none; }
            .copy { margin: 0 auto; break-after: page; }
            main .copy:last-child { break-after: auto; }
            body.paper-a4 .copy { margin: 8mm auto; break-after: auto; }
            body.paper-a4 .potong { display: block; }
        }
    </style>
</head>
<body class="paper-{{ $paper }}">

@php
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $salinan = ['ASLI - UNTUK SUPIR', 'ARSIP - ADMIN'];
@endphp

<div class="toolbar">
    <a href="{{ route('work-sessions.verify', $session) }}">&larr; Kembali</a>
    @if ($receipt->isActive())
        <button type="button" class="utama" onclick="window.print()">Cetak (2 salinan)</button>
    @endif
    <span>Kertas:</span>
    @foreach (['80' => '80 mm', '58' => '58 mm', 'a4' => 'A4'] as $k => $label)
        <a href="{{ request()->fullUrlWithQuery(['kertas' => $k]) }}" @if ($paper === $k) style="background:#0ea5e9" @endif>{{ $label }}</a>
    @endforeach
    <span class="info">Sudah dicetak {{ $receipt->print_count }}x</span>
</div>

<main>
@foreach ($salinan as $label)
    <section class="copy">
        @if (! $receipt->isActive())
            <div class="tanda">STRUK DIBATALKAN - TIDAK BERLAKU</div>
            @if ($receipt->void_reason)
                <div class="tengah">Alasan: {{ $receipt->void_reason }}</div>
            @endif
        @endif

        <div class="tengah tebal besar">ABSENSI RITASE</div>
        <div class="tengah">JMT OPERASIONAL</div>
        <div class="tengah">{{ $session->route }}</div>
        <div class="tengah">{{ $session->date->translatedFormat('d M Y') }}</div>
        <hr>
        <div class="baris"><span>No. Struk</span><span>{{ $receipt->receipt_no }}</span></div>
        <div class="baris"><span>Terbit</span><span>{{ $receipt->printed_at->format('d/m/Y H:i') }}</span></div>
        <div class="baris"><span>Admin</span><span>{{ $receipt->printer?->name }}</span></div>
        <hr>
        <div class="tebal">{{ $receipt->driver->name }}</div>
        <div>KTP {{ str_replace("\u{2022}", '*', $receipt->driver->maskedKtp()) }}</div>
        <hr>
        <div class="baris tebal"><span>RIT</span><span>4 DIGIT SASIS</span></div>
        @foreach ($receipt->chassis ?? [] as $i => $sasis)
            <div class="baris"><span>{{ $i + 1 }}</span><span>{{ $sasis }}</span></div>
        @endforeach
        <hr>
        <div class="baris"><span>Jumlah ritase</span><span>{{ $receipt->total_trips }}</span></div>
        <div class="baris"><span>Tarif / ritase</span><span>{{ $rp($receipt->rate_per_trip) }}</span></div>
        <div class="baris tebal besar"><span>TOTAL</span><span>{{ $rp($receipt->amount) }}</span></div>
        <hr>
        <div class="ttd">
            <div><div class="garis"></div>Supir</div>
            <div><div class="garis"></div>Admin</div>
        </div>
        <hr>
        @if ($reprint)
            <div class="tengah" style="font-size:10px;">( cetak ulang )</div>
        @endif
        <div class="tengah tebal">{{ $label }}</div>
        @if (! $loop->last)
            <div class="potong">- - - potong di sini - - -</div>
        @endif
    </section>
@endforeach
</main>

@if ($receipt->isActive())
<script>
    // Setiap kali dialog cetak dibuka (tombol atau Ctrl+P), catat satu cetak. Sekali per muat halaman.
    (function () {
        var sudah = false;
        window.addEventListener('beforeprint', function () {
            if (sudah) { return; }
            sudah = true;
            fetch(@json(route('receipts.printed', [$session, $receipt])), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
                credentials: 'same-origin',
                keepalive: true
            });
        });
    })();
</script>
@endif

</body>
</html>