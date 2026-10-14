<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, EventSelectionFinalization, EventSelectionRosterImport};
use App\Services\EventSelectionRosterImportService;
use Illuminate\Http\Request;

class EventSelectionRosterImportController extends Controller
{
    public function index(Request $request, Event $event, EventSelectionRosterImportService $service)
    {
        $this->authorize('event.manage', $event);
        abort_unless($event->isTeam(), 404);
        $sourcesQuery = Event::where('id', '!=', $event->id)->whereIn('id', EventSelectionFinalization::active()->select('event_id'))
            ->whereHas('eventTypeModel', fn ($q) => $q->where('type', \App\Models\EventType::TEAM));
        if (! $request->user()->hasRole('super-user')) {
            $adminEvents = \App\Models\EventAdmin::where('user_id', $request->user()->id)->select('event_id');
            $scorekeeperEvents = \App\Models\EventConvenor::where('user_id', $request->user()->id)->active()->where('role', 'score-keeper')->select('event_id');
            $convenorEvents = \App\Models\EventConvenor::where('user_id', $request->user()->id)->active()->whereNotIn('event_id', $scorekeeperEvents)->select('event_id');
            $sourcesQuery->where(fn ($q) => $q->whereIn('id', $adminEvents)->orWhereIn('id', $convenorEvents));
        }
        $sources = $sourcesQuery->orderByDesc('start_date')->paginate(30);
        $preview = null;
        $finalization = null;
        if ($request->filled('source_event_id')) {
            $data = $request->validate(['source_event_id' => 'required|integer']);
            $finalization = EventSelectionFinalization::active()->where('event_id', $data['source_event_id'])->firstOrFail();
            $preview = $service->preview($event, $finalization, $request->user());
        }
        $regions = $event->regions()->orderBy('region_name')->get();
        $imports = EventSelectionRosterImport::where('event_id', $event->id)->with('finalization', 'region')->orderByDesc('id')->paginate(10, ['*'], 'imports_page');
        return response()->view('backend.adminPage.selection-import', compact('event', 'sources', 'preview', 'finalization', 'regions', 'imports'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Event $event, EventSelectionRosterImportService $service)
    {
        $this->authorize('event.manage', $event);
        abort_unless($event->isTeam(), 404);
        $input = $request->validate(['finalization_id' => 'required|integer', 'region_id' => 'required|integer', 'mapping' => 'required|array|min:1|max:198', 'mapping.*' => 'required|integer|min:0', 'confirm_import' => 'accepted']);
        $import = $service->import($event, EventSelectionFinalization::findOrFail($input['finalization_id']), (int) $input['region_id'], $input['mapping'], $request->user());
        return redirect()->route('backend.selection-import.index', $event)->with('success', 'Imported '.count($import->groups).' finalized teams including their reserves. Review the destination rosters before publishing.')->header('Cache-Control', 'no-store, private');
    }
}
