<div class="row g-3 mb-3">
<div class="col-md-6"><label class="form-label w-100">From name<input class="form-control" name="from_name" required maxlength="150" value="{{ old('from_name', auth()->user()->name) }}" autocomplete="name"></label></div>
<div class="col-md-6"><label class="form-label w-100">Reply-to address<input class="form-control" type="email" name="reply_to" required maxlength="255" value="{{ old('reply_to', auth()->user()->email) }}" autocomplete="email"></label></div>
</div>
