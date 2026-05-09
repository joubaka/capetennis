<?php
// Raw ITN string exactly as received
$raw = 'm_payment_id=&pf_payment_id=3147517&payment_status=COMPLETE&item_name=Primary+Schools+Witzenberg%2FBreede+Vallei%2FLangeberg+Primary+Schools+Trials+-+Leg+2+2026&item_description=&amount_gross=285.00&amount_fee=-6.56&amount_net=278.44&custom_str1=u%2F13+Girls&custom_str2=Anje+De+Jager&custom_str3=Primary+Schools+Witzenberg%2FBreede+Vallei%2FLangeberg+Primary+Schools+Trials+-+Leg+2+2026&custom_str4=Super+User&custom_str5=&custom_int1=2093&custom_int2=1133&custom_int3=222&custom_int4=584&custom_int5=9503&name_first=&name_last=&email_address=&merchant_id=10008657&signature=53c4c5b7e3fd029bd39a27f4302ba6a9';

$received = '53c4c5b7e3fd029bd39a27f4302ba6a9';

// Strip signature from the raw string (everything before &signature=)
$withoutSig = preg_replace('/&signature=[^&]*$/', '', $raw);

echo "=== Raw string approach (no re-encoding) ===\n";
echo "String (no sig): {$withoutSig}\n\n";

$passphrases = ['SandboxPass123', 'jt7NOE43dXWn', 'PieterJoubert123', ''];
foreach ($passphrases as $pp) {
    $str = $withoutSig . ($pp !== '' ? '&passphrase=' . urlencode($pp) : '');
    $sig = md5($str);
    $label = $pp === '' ? '(none)' : $pp;
    echo "Passphrase [{$label}]: {$sig} " . ($sig === $received ? '✅ MATCH' : '❌') . "\n";
}

echo "\n=== Parsed + re-sorted approach ===\n";
parse_str($raw, $data);
$passphrases2 = ['SandboxPass123', 'jt7NOE43dXWn', 'PieterJoubert123', ''];
foreach ($passphrases2 as $pp) {
    $fields = $data;
    unset($fields['signature'], $fields['passphrase']);
    // try both strip-empty and keep-empty
    foreach ([true, false] as $strip) {
        $f = $strip ? array_filter($fields, fn($v) => $v !== '' && $v !== null) : $fields;
        if ($pp !== '') $f['passphrase'] = $pp;
        ksort($f);
        $str = http_build_query($f);
        $sig = md5($str);
        $label = ($pp === '' ? '(none)' : $pp) . ($strip ? ' stripped' : ' full');
        if ($sig === $received) echo "✅ MATCH passphrase=[{$label}]\n  String: {$str}\n";
    }
}
echo "Done.\n";
