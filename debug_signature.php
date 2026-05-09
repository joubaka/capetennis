<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Exact ITN data from the log for PF 299913471
$data = [
    'm_payment_id'    => null,
    'pf_payment_id'   => '299913471',
    'payment_status'  => 'COMPLETE',
    'item_name'       => 'Primary Schools Witzenberg/Breede Vallei/Langeberg Primary Schools Trials - Leg 2 2026',
    'item_description'=> null,
    'amount_gross'    => '285.00',
    'amount_fee'      => '-12.79',
    'amount_net'      => '272.21',
    'custom_str1'     => 'u/12 Boys',
    'custom_str2'     => 'Ralph Köster',
    'custom_str3'     => 'Primary Schools Witzenberg/Breede Vallei/Langeberg Primary Schools Trials - Leg 2 2026',
    'custom_str4'     => 'Janine Köster',
    'custom_str5'     => null,
    'custom_int1'     => '2090',
    'custom_int2'     => '3473',
    'custom_int3'     => '222',
    'custom_int4'     => '3058',
    'custom_int5'     => '9499',
    'name_first'      => null,
    'name_last'       => null,
    'email_address'   => null,
    'merchant_id'     => '11307280',
    'signature'       => '60bb78853289ae2de2a3085c5ad5d234',
];

$received = $data['signature'];
$passphrase = 'PieterJoubert123';

echo "Received signature: {$received}\n\n";

// Attempt 1: Current code logic — strip null AND empty string
$fields1 = array_filter($data, fn($v) => $v !== '' && $v !== null);
unset($fields1['signature'], $fields1['passphrase']);
ksort($fields1);
$str1 = '';
foreach ($fields1 as $k => $v) {
    $str1 .= $k . '=' . urlencode(trim((string)$v)) . '&';
}
$str1 = rtrim($str1, '&') . '&passphrase=' . urlencode(trim($passphrase));
$sig1 = md5($str1);
echo "Attempt 1 (strip null+empty): {$sig1} " . ($sig1 === $received ? '✅ MATCH' : '❌') . "\n";

// Attempt 2: Keep null fields as empty string (don't strip them)
$fields2 = $data;
unset($fields2['signature'], $fields2['passphrase']);
ksort($fields2);
$str2 = '';
foreach ($fields2 as $k => $v) {
    $str2 .= $k . '=' . urlencode(trim((string)$v)) . '&';
}
$str2 = rtrim($str2, '&') . '&passphrase=' . urlencode(trim($passphrase));
$sig2 = md5($str2);
echo "Attempt 2 (keep nulls as empty): {$sig2} " . ($sig2 === $received ? '✅ MATCH' : '❌') . "\n";

// Attempt 3: Strip only empty string, keep null as empty string
$fields3 = $data;
unset($fields3['signature'], $fields3['passphrase']);
$fields3 = array_map(fn($v) => $v === null ? '' : $v, $fields3);
$fields3 = array_filter($fields3, fn($v) => $v !== '');
ksort($fields3);
$str3 = '';
foreach ($fields3 as $k => $v) {
    $str3 .= $k . '=' . urlencode(trim((string)$v)) . '&';
}
$str3 = rtrim($str3, '&') . '&passphrase=' . urlencode(trim($passphrase));
$sig3 = md5($str3);
echo "Attempt 3 (strip empty only): {$sig3} " . ($sig3 === $received ? '✅ MATCH' : '❌') . "\n";

// Attempt 4: No passphrase
$fields4 = array_filter($data, fn($v) => $v !== '' && $v !== null);
unset($fields4['signature'], $fields4['passphrase']);
ksort($fields4);
$str4 = '';
foreach ($fields4 as $k => $v) {
    $str4 .= $k . '=' . urlencode(trim((string)$v)) . '&';
}
$str4 = rtrim($str4, '&');
$sig4 = md5($str4);
echo "Attempt 4 (no passphrase): {$sig4} " . ($sig4 === $received ? '✅ MATCH' : '❌') . "\n";

// Attempt 5: http_build_query style (no manual urlencode)
$fields5 = array_filter($data, fn($v) => $v !== '' && $v !== null);
unset($fields5['signature'], $fields5['passphrase']);
ksort($fields5);
$fields5['passphrase'] = $passphrase;
ksort($fields5);
$str5 = http_build_query($fields5);
$sig5 = md5($str5);
echo "Attempt 5 (http_build_query): {$sig5} " . ($sig5 === $received ? '✅ MATCH' : '❌') . "\n";

// Attempt 6: PayFast docs exact — add passphrase to array, ksort ALL, http_build_query
$fields6 = array_filter($data, fn($v) => $v !== '' && $v !== null);
unset($fields6['signature'], $fields6['passphrase']);
$fields6['passphrase'] = $passphrase;
ksort($fields6);
$str6 = http_build_query($fields6);
$sig6 = md5($str6);
echo "Attempt 6 (docs exact — ksort+http_build_query): {$sig6} " . ($sig6 === $received ? '✅ MATCH' : '❌') . "\n";
echo "  String: {$str6}\n";
