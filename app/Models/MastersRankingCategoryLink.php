<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MastersRankingCategoryLink extends Model
{
    protected $guarded = [];
    protected $casts = ['enabled' => 'boolean', 'top_x' => 'integer'];

    public function event() { return $this->belongsTo(Event::class); }
    public function rankingList() { return $this->belongsTo(RankingList::class); }
    public function categoryEvent() { return $this->belongsTo(CategoryEvent::class); }
}
