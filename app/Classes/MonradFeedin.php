<?php

namespace App\Classes;

use App\Models\Draw;

class MonradFeedin

{
    public $drawName, $players, $size, $numPlayers,$fixtures;
    public function __construct($draw, $size)

    {

        $this->drawName = $draw->drawName;
        $this->players = Draw::find($draw->id)->registrations;
        $this->numPlayers = $this->players->count();
        $this->size = $size;
        $this->fixtures = $draw->drawFixtures;
    }

    public function print()
    {

        $data = $this->makeDraw();





        return $data;
    }

    public function makeDraw()
    {
      
        $x = 50;
        $y = 20;
        $height = 50;
        $width = 200;
        $data = '';
        $data .= '<h3>' . $this->drawName . '</h3>';

        $data .= '<svg width="1400" height="2200">';
        $data .= $this->rnd1Box($x,$y,$height,$width,$this->size / 2);
        $data .= $this->rnd2Box(($x+$width),($height),($height*2),$width,$this->size / 4);
        $data .= $this->rnd3Box(($x+$width+$width),($height),($height*4),$width,$this->size / 8);
        $data .= $this->rnd3Box(($x+$width+$width+$width),($height),($height*8),$width,$this->size / 16);
        $data .= $this->rnd3Box(($x+$width+$width+$width+$width),($height),($height*16),$width,$this->size / 32);

        $data .= '</svg>';
        return $data;
    }

    public function rnd1Box($x,$y,$height,$width,$boxes)
    {
        
        $data = '';
     
        
        for ($i = 0; $i < $boxes; $i++) {

            $data .= '<rect  x="' . $x . '" y="' . $y . '" width="'.$width.'" height="' . $height . '" stroke-width:3;stroke:rgb(0,0,0)" />';
            $y += $height;
            $data .= '<rect  x="' . $x . '" y="' . $y . '" width="'.$width.'" height="' . $height . '" fill-opacity="0" />';
            $y += $height;
        }

        return $data;
    }
    public function rnd2Box($x,$y,$height,$width,$boxes)
    {
        $data = '';
        $y =($height/2);
        
        for ($i = 0; $i < $boxes; $i++) {

            $data .= '<rect  x="' . $x . '" y="' . $y . '" width="'.$width.'" height="' . $height . '" ;stroke-width:3;stroke:rgb(0,0,0)" />';
            $y += $height;
            $data .= '<rect  x="' . $x . '" y="' . $y . '" width="'.$width.'" height="' . $height . '" fill-opacity="0" />';
            $y += $height;
        }

        return $data;
    }
    public function rnd3Box($x,$y,$height,$width,$boxes)
    {
        $data = '';
     
        $y =($height/2);
        for ($i = 0; $i < $boxes; $i++) {

            $data .= '<rect  x="' . $x . '" y="' . $y . '" width="'.$width.'" height="' . $height . '" style="fill:rgb(0,0,255);stroke-width:3;stroke:rgb(0,0,0)" />';
            $y += $height;
            $data .= '<rect  x="' . $x . '" y="' . $y . '" width="'.$width.'" height="' . $height . '" fill-opacity="0" />';
            $y += $height;
        }

        return $data;
    }
}
