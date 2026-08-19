<div class="flex items-center gap-1.5 mb-4">
    @for ($i = 1; $i <= 3; $i++)
        <div class="flex-1 h-1 rounded-full {{ $i <= $step ? 'bg-[#1F3A52]' : 'bg-gray-200' }}"></div>
    @endfor
</div>
<div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-3">Step {{ $step }} of 3</div>
