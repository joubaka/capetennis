<?php

use App\Classes\Brackets;
use App\Classes\MonradFeedin;


?>
{{dd($printDraw)}}
{!!$printDraw!!}
@php


Brackets::get_bracket_plat($draw);

Brackets::get_bracket_3_4($draw);
Brackets::get_bracket_gold($draw, 3);

Brackets::get_bracket_7_8($draw, 4);
Brackets::get_bracket_9_12($draw, 5);
Brackets::get_bracket_13_16($draw, 6);
Brackets::get_bracket_17_24($draw, 7);

Brackets::get_bracket_25_32($draw, 8);

@endphp 