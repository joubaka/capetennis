<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NominateController extends Controller
{
    public function index() {}
    public function create() { return 'nominate'; }
    public function show($id) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}

    public function store(Request $request) {
        $data=$request->validate(['event_id'=>'required|integer|exists:events,id','category_event_id'=>'required|integer|exists:category_events,id','players'=>'required|array','players.*'=>'integer|distinct|exists:players,id']);
        $category=CategoryEvent::with('event')->findOrFail($data['category_event_id']);
        abort_unless((int)$category->event_id===(int)$data['event_id'],404); $this->authorizeEvent($category->event); $this->sync($category,$data['players']);
        return response()->json(['success'=>true,'message'=>'Nominations saved successfully!','nominations'=>$this->nominations($category)]);
    }
    public function destroy(Request $request) { $n=EventNomination::with('categoryEvent.event')->findOrFail($request->integer('id')); $this->authorizeNomination($n); $n->delete(); return 1; }
    public function nominationInCategory($id) { $c=$this->authorizedCategory((int)$id); return EventNomination::where('category_event_id',$c->id)->with('player')->get(); }
    public function togglePublish($id) { $c=$this->authorizedCategory((int)$id); $c->update(['nominations_published'=>!$c->nominations_published]); return response()->json(['success'=>true,'published'=>(bool)$c->nominations_published]); }
    public function playersForCategory($id) { $c=$this->authorizedCategory((int)$id); $selected=EventNomination::where('category_event_id',$c->id)->pluck('player_id')->all(); return response()->json(Player::select('id','name','surname','email','cellNr')->orderBy('surname')->get()->map(fn($p)=>['id'=>$p->id,'text'=>trim("{$p->name} {$p->surname}")?:'Unknown Player','email'=>$p->email??'','cellNr'=>$p->cellNr??'','nominated'=>in_array($p->id,$selected)])); }
    public function getSelected($id) { $c=$this->authorizedCategory((int)$id); return response()->json(EventNomination::where('category_event_id',$c->id)->pluck('player_id')->all()); }
    public function save(Request $request) { $data=$request->validate(['category_event_id'=>'required|integer|exists:category_events,id','player_ids'=>'array','player_ids.*'=>'integer|distinct|exists:players,id']); $c=$this->authorizedCategory((int)$data['category_event_id']); $this->sync($c,$data['player_ids']??[]); return response()->json(['success'=>true,'count'=>count($data['player_ids']??[])]); }
    public function partialTable($id) { $category=$this->authorizedCategory((int)$id); $category->load('nominations.player'); return view('backend.nominations.partials.table',compact('category')); }
    public function remove(Request $request) { $data=$request->validate(['nomination_id'=>'required|integer|exists:event_nominations,id']); $n=EventNomination::with('categoryEvent.event')->findOrFail($data['nomination_id']); $this->authorizeNomination($n); $n->delete(); return response()->json(['success'=>true,'removed_id'=>$n->id]); }

    private function sync(CategoryEvent $category,array $playerIds): void { $ids=collect($playerIds)->map(fn($id)=>(int)$id)->unique()->values(); DB::transaction(function()use($category,$ids){$existing=EventNomination::where('category_event_id',$category->id)->lockForUpdate()->get()->keyBy('player_id'); EventNomination::where('category_event_id',$category->id)->whereNotIn('player_id',$ids)->delete(); foreach($ids as $playerId) if(!$existing->has($playerId)) EventNomination::create(['event_id'=>$category->event_id,'category_event_id'=>$category->id,'player_id'=>$playerId]);}); }
    private function nominations(CategoryEvent $category): array { return EventNomination::where('category_event_id',$category->id)->with('player:id,name,surname,email,cellNr')->get()->map(fn($n)=>['nomination_id'=>$n->id,'name'=>$n->player->name,'surname'=>$n->player->surname,'email'=>$n->player->email,'cellNr'=>$n->player->cellNr])->all(); }
    private function authorizedCategory(int $id): CategoryEvent { $c=CategoryEvent::with('event')->findOrFail($id); $this->authorizeEvent($c->event); return $c; }
    private function authorizeNomination(EventNomination $nomination): void { $event=$nomination->categoryEvent?->event; abort_unless($event && (int)$nomination->event_id===(int)$event->id,404); $this->authorizeEvent($event); }
    private function authorizeEvent(Event $event): void { $u=request()->user(); abort_unless($u&&($u->hasRole('super-user')||($u->hasRole('admin')&&$u->is_event_admin($event->id))),403); }
}
