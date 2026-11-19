@props([
    'label',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3']) }}>
    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
    <dd class="mt-1 text-sm font-medium text-slate-800">{{ $slot }}</dd>
</div>
