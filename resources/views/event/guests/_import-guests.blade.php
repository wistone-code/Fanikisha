{{-- Bulk add guests: paste a list (one guest per line: name, phone, single/double), read it from a photo,
     or upload a CSV/text file. Used on the e-card guest page and the contribution account's Event invitation page. --}}
@error('import_file')
<div class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg px-3 py-2 mb-3">{{ $message }}</div>
@enderror
<form method="POST" action="{{ route('guests.import') }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    <div>
        <label for="importGuestsText" class="text-xs font-semibold">Paste or type the list</label>
        <textarea name="import_text" id="importGuestsText" rows="7" placeholder="Asha Said 0712 345 678 single&#10;Neema &amp; Baraka 0765 432 198 double&#10;John Mushi 0754 123 456" class="w-full border rounded-lg px-3 py-2 text-sm font-mono">{{ old('import_text') }}</textarea>
        <p class="text-xs text-gray-500 mt-1">One guest per line: <strong>[name] [phone number] [single or double]</strong>. Phone and card type are optional; without a card type the guest gets a single card. Lists copied from WhatsApp, Excel or Notes work too.</p>
    </div>

    <div class="rounded-lg border border-dashed px-3 py-3">
        <label for="importGuestsPhotos" class="text-xs font-semibold"><i class="fa-solid fa-camera"></i> Read the list from a photo</label>
        <p class="text-xs text-gray-500 mb-2">A photo or screenshot of a written or printed list, a WhatsApp chat or contacts (up to 4 photos). The names, numbers and card types go into the box above so you can check them before importing.</p>
        <input type="file" id="importGuestsPhotos" accept="image/*" multiple class="w-full text-sm">
        <button type="button" id="importGuestsPhotoBtn" class="btn btn-ghost mt-2"><i class="fa-solid fa-wand-magic-sparkles"></i> Read names from photo</button>
        <p id="importGuestsPhotoStatus" class="text-xs mt-2 hidden" role="status"></p>
    </div>

    <details class="text-xs">
        <summary class="cursor-pointer font-semibold">Or upload a CSV / text file</summary>
        <input type="file" name="import_file" accept=".csv,.txt" class="w-full border rounded-lg px-3 py-2 text-sm mt-2">
        <p class="text-gray-500 mt-1">Columns: Name, Phone, Card (single or double).</p>
    </details>

    <p class="text-xs text-gray-500">Up to 500 guests at a time. Guests already on the list are skipped.</p>
    <div class="flex gap-2 pt-1">
        @isset($cancelId)
        <button type="button" onclick="document.getElementById('{{ $cancelId }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
        @endisset
        <button class="btn btn-primary flex-1 justify-center"><i class="fa-solid fa-file-import"></i> Import guests</button>
    </div>
</form>
<script>
(function () {
    var btn = document.getElementById('importGuestsPhotoBtn');
    var input = document.getElementById('importGuestsPhotos');
    var status = document.getElementById('importGuestsPhotoStatus');
    var box = document.getElementById('importGuestsText');
    if (!btn) return;

    function say(text, isError) {
        status.textContent = text;
        status.classList.remove('hidden');
        status.classList.toggle('text-red-600', !!isError);
        status.classList.toggle('text-gray-500', !isError);
    }

    btn.addEventListener('click', async function () {
        if (!input.files.length) { say('Choose a photo first.', true); return; }
        if (input.files.length > 4) { say('Choose up to 4 photos at a time.', true); return; }

        var form = new FormData();
        for (var i = 0; i < input.files.length; i++) form.append('photos[]', input.files[i]);

        btn.disabled = true;
        say('Reading the photo… this can take up to a minute.');
        try {
            var res = await fetch({{ Js::from(route('guests.import-photo')) }}, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': {{ Js::from(csrf_token()) }}, 'Accept': 'application/json' },
                body: form,
            });
            var data = await res.json().catch(function () { return {}; });
            if (!res.ok) { say(data.error || 'Could not read that photo. Try a clearer one.', true); return; }

            var lines = data.items.map(function (g) {
                return [g.name, g.phone || '', g.card || ''].join(' ').replace(/\s+/g, ' ').trim();
            });
            var current = box.value.trim();
            box.value = (current ? current + '\n' : '') + lines.join('\n');
            box.focus();
            say('Found ' + lines.length + ' guest(s). Check the names, numbers and card types above, then tap Import guests.');
        } catch (e) {
            say('Could not reach the server. Check your connection and try again.', true);
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
