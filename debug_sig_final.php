<?php
// Simulate exactly what the new validatePayfastSignature() does
$raw = 'm_payment_id=&pf_payment_id=3147517&payment_status=COMPLETE&item_name=Primary+Schools+Witzenberg%2FBreede+Vallei%2FLangeberg+Primary+Schools+Trials+-+Leg+2+2026&item_description=&amount_gross=285.00&amount_fee=-6.56&amount_net=278.44&custom_str1=u%2F13+Girls&custom_str2=Anje+De+Jager&custom_str3=Primary+Schools+Witzenberg%2FBreede+Vallei%2FLangeberg+Primary+Schools+Trials+-+Leg+2+2026&custom_str4=Super+User&custom_str5=&custom_int1=2093&custom_int2=1133&custom_int3=222&custom_int4=584&custom_int5=9503&name_first=&name_last=&email_address=&merchant_id=10008657&signature=53c4c5b7e3fd029bd39a27f4302ba6a9';

$received = '53c4c5b7e3fd029bd39a27f4302ba6a9';
$passphrase = 'SandboxPass123';

// Simulate $request->getContent() returning the raw body
$rawBody = $raw;

// Strip signature
$baseString = preg_replace('/&?signature=[^&]*/', '', $rawBody);
$baseString = rtrim($baseString, '&');

$pfOutput = $baseString . '&passphrase=' . urlencode(trim($passphrase));
$computed = md5($pfOutput);

echo "Received: {$received}\n";
echo "Computed: {$computed}\n";
echo "Match: " . ($computed === $received ? '✅ YES — ITN would PASS' : '❌ NO') . "\n";
echo "\nBase string: {$baseString}\n";
