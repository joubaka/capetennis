<form method="get" class="mp-filters">
    <div><label for="mp-cohort">Age group</label><select id="mp-cohort" name="cohort"><option value="">All preview groups</option>@foreach($cohorts as $key => $label)<option value="{{ $key }}" @selected($selected === $key)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="mp-search">Player name</label><input type="search" name="search" id="mp-search" maxlength="100" value="{{ $search }}" placeholder="Search this preview"></div>
    <button class="mp-btn" type="submit">Apply filters</button>
</form>
