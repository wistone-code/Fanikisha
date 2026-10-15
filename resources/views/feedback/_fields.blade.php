@php($sw = $sw ?? false)
@php($withRating = $withRating ?? true)
@php($labels = $sw
    ? ['problem' => 'Tatizo', 'idea' => 'Wazo', 'question' => 'Swali', 'praise' => 'Pongezi']
    : ['problem' => 'Problem', 'idea' => 'Idea', 'question' => 'Question', 'praise' => 'Praise'])
<fieldset>
    <legend class="text-xs font-semibold">{{ $sw ? 'Ni kuhusu nini?' : 'What is it about?' }} *</legend>
    <div class="flex flex-wrap gap-2 mt-1">
        @foreach ($labels as $v => $l)
            <label class="px-3 py-1.5 rounded-full border text-sm cursor-pointer"><input type="radio" name="type" value="{{ $v }}" class="mr-1" @checked(old('type', 'idea') === $v)>{{ $l }}</label>
        @endforeach
    </div>
    @error('type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</fieldset>
@if ($withRating)
<fieldset>
    <legend class="text-xs font-semibold">{{ $sw ? 'Unaipa alama ngapi? (si lazima)' : 'How would you rate Fanikisha? (optional)' }}</legend>
    <div class="flex gap-3 mt-1 text-sm">
        @foreach (range(1, 5) as $n)
            <label class="cursor-pointer"><input type="radio" name="rating" value="{{ $n }}" class="mr-1" @checked((int) old('rating') === $n)>{{ $n }} ★</label>
        @endforeach
    </div>
</fieldset>
@endif
<div>
    <label class="text-xs font-semibold" for="message">{{ $sw ? 'Ujumbe wako' : 'Your message' }} *</label>
    <textarea id="message" name="message" rows="5" maxlength="1000" required class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">{{ old('message') }}</textarea>
    @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
