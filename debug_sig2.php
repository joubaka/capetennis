<?php
$raw = 'm_payment_id=&pf_payment_id=3147517&payment_status=COMPLETE&item_name=Primary+Schools+Witzenberg%2FBreede+Vallei%2FLangeberg+Primary+Schools+Trials+-+Leg+2+2026&item_description=&amount_gross=285.00&amount_fee=-6.56&amount_net=278.44&custom_str1=u%2F13+Girls&custom_str2=Anje+De+Jager&custom_str3=Primary+Schools+Witzenberg%2FBreede+Vallei%2FLangeberg+Primary+Schools+Trials+-+Leg+2+2026&custom_str4=Super+User&custom_str5=&custom_int1=2093&custom_int2=1133&custom_int3=222&custom_int4=584&custom_int5=9503&name_first=&name_last=&email_address=&merchant_id=10008657&signature=53c4c5b7e3fd029bd39a27f4302ba6a9';

parse_str($raw, $data);
$received = $data['signature'];
$passphrases = ['SandboxPass123', 'jt7NOE43dXWn', 'PieterJoubert123', ''];

foreach ($passphrases as $passphrase) {
  $fields = array_filter($data, fn($v) => $v !== '' && $v !== null);
  unset($fields['signature'], $fields['passphrase']);
  if ($passphrase !== '') $fields['passphrase'] = $passphrase;
  ksort($fields);
  $str = http_build_query($fields);
  $sig = md5($str);
  $label = $passphrase === '' ? '(no passphrase)' : $passphrase;
  echo "Try [{$label}]: {$sig} " . ($sig === $received ? '✅ MATCH' : '❌') . "\n";
}
echo "\nReceived: {$received}\n";
exit;

$passphrase = 'SandboxPass123';

echo "Received:  {$received}\n\n";

// Attempt A: Our current code — strip empty+null, add passphrase, ksort, http_build_query
$fields = array_filter($data, fn($v) => $v !== '' && $v !== null);
unset($fields['signature'], $fields['passphrase']);
$fields['passphrase'] = $passphrase;
ksort($fields);
$strA = http_build_query($fields);
$sigA = md5($strA);
echo "A (current code):  {$sigA} " . ($sigA === $received ? '✅ MATCH' : '❌') . "\n";
echo "  String: {$strA}\n\n";

// Attempt B: keep empty fields (don't strip), add passphrase, ksort, http_build_query
$fields2 = $data;
unset($fields2['signature'], $fields2['passphrase']);
$fields2['passphrase'] = $passphrase;
ksort($fields2);
$strB = http_build_query($fields2);
$sigB = md5($strB);
echo "B (keep empty fields): {$sigB} " . ($sigB === $received ? '✅ MATCH' : '❌') . "\n";
echo "  String: {$strB}\n\n";

// Attempt C: no passphrase at all
$fields3 = array_filter($data, fn($v) => $v !== '' && $v !== null);
unset($fields3['signature'], $fields3['passphrase']);
ksort($fields3);
$strC = http_build_query($fields3);
$sigC = md5($strC);
echo "C (no passphrase):  {$sigC} " . ($sigC === $received ? '✅ MATCH' : '❌') . "\n\n";

echo "Fields included in A: " . implode(', ', array_keys($fields)) . "\n";
