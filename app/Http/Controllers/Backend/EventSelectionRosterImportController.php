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
        $sourcesQuery = Event::where('id', '!=', $event->id)
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
        $sourceEvent = null;
        $sourceRegions = collect();
        $regionGroups = [];
        $selectedRegionIds = array_map('intval', (array) $request->input('source_region_ids', []));
        if ($request->filled('source_event_id')) {
            $data = $request->validate(['source_event_id' => 'required|integer', 'source_region_ids' => 'sometimes|array|min:1|max:500', 'source_region_ids.*' => 'required|integer|distinct', 'review_regions' => 'sometimes|accepted']);
            $sourceEvent = Event::findOrFail($data['source_event_id']);
            $this->authorize('event.manage', $sourceEvent);
            abort_unless($sourceEvent->isTeam() && $sourceEvent->id !== $event->id, 422, 'Choose a different team event.');
            $sourceRegions = $sourceEvent->regions()->orderBy('region_name')->limit(501)->get();
            abort_if($sourceRegions->count() > 500, 422, 'Selection import supports up to 500 regions per event.');
            $finalization = EventSelectionFinalization::active()->where('event_id', $sourceEvent->id)->first();
            if ($finalization) {
                $overview = $service->preview($event, $finalization, $request->user());
                $regionGroups = $overview['regionGroups'];
                // Preserve bookmarked legacy previews; the current chooser explicitly
                // asks for a region selection before showing destination mappings.
                if ($request->boolean('review_regions')) {
                    if ($selectedRegionIds === []) throw \Illuminate\Validation\ValidationException::withMessages(['source_region_ids' => 'Select at least one finalized region to review.']);
                    $preview = $service->preview($event, $finalization, $request->user(), $selectedRegionIds);
                } elseif (! $request->boolean('choose_regions')) {
                    $preview = $overview;
                }
            } elseif ($request->boolean('review_regions')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['source_region_ids' => 'Finalize the saved selection in the source event before importing.']);
            }
        }
        $regions = $event->regions()->orderBy('region_name')->get();
        $imports = EventSelectionRosterImport::where('event_id', $event->id)->with('finalization', 'region')->orderByDesc('id')->paginate(10, ['*'], 'imports_page');
        return response()->view('backend.adminPage.selection-import', compact('event', 'sources', 'preview', 'finalization', 'regions', 'imports', 'sourceEvent', 'sourceRegions', 'regionGroups', 'selectedRegionIds'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Event $event, EventSelectionRosterImportService $service)
    {
        $this->authorize('event.manage', $event);
        abort_unless($event->isTeam(), 404);
        $input = $request->validate(['finalization_id' => 'required|integer', 'region_id' => 'required|integer', 'source_region_ids' => 'sometimes|array|min:1|max:500', 'source_region_ids.*' => 'required|integer|distinct', 'mapping' => 'required|array|min:1|max:198', 'mapping.*' => 'required|integer|min:0', 'confirm_import' => 'accepted']);
        $import = $service->import($event, EventSelectionFinalization::findOrFail($input['finalization_id']), (int) $input['region_id'], $input['mapping'], $request->user(), $input['source_region_ids'] ?? null);
        return redirect()->route('backend.selection-import.index', $event)->with('success', 'Imported '.count($import->groups).' finalized teams including their reserves. Review the destination rosters before publishing.')->header('Cache-Control', 'no-store, private');
    }
}
