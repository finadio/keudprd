<?php

use App\Services\BookkeepingService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$svc = new BookkeepingService;
$res = $svc->getBukuBankMonth(2026, 1);

echo 'Buku Bank Jan 2026 Test:'.PHP_EOL;
echo '  Rows: '.count($res['rows']).PHP_EOL;
echo '  Penerimaan: Rp '.number_format($res['summary']['periode_ini']['penerimaan'], 2, ',', '.').PHP_EOL;
echo '  Pengeluaran: Rp '.number_format($res['summary']['periode_ini']['pengeluaran'], 2, ',', '.').PHP_EOL;
echo '  Saldo Akhir: Rp '.number_format($res['summary']['sisa_kas'], 2, ',', '.').PHP_EOL;

$hitungan = $svc->getHitunganSummary(2026);
echo PHP_EOL.'Hitungan Summary Test:'.PHP_EOL;
echo '  Total Debet: Rp '.number_format($hitungan['totals']['debet'], 2, ',', '.').PHP_EOL;
echo '  Total Panjar: Rp '.number_format($hitungan['totals']['panjar'], 2, ',', '.').PHP_EOL;
echo '  Total SPJ: Rp '.number_format($hitungan['totals']['spj'], 2, ',', '.').PHP_EOL;
echo '  Kontrol Selisih: Rp '.number_format($hitungan['totals']['kontrol_selisih'], 2, ',', '.').PHP_EOL;
