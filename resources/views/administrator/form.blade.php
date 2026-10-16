@extends('layouts.admin')

@section('title', $title)

@push('styles')
<style>
    .form-card { max-width: 760px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-group label { font-weight: 600; font-size: 14px; }
    .form-group input[type=text], .form-group input[type=email], .form-group input[type=password],
    .form-group input[type=number], .form-group input[type=date], .form-group input[type=time],
    .form-group select, .form-group textarea {
        padding: 10px 12px; border: 1px solid #d6d6d6; border-radius: 8px; font: inherit; width: 100%;
    }
    .form-group textarea { min-height: 96px; resize: vertical; }
    .check-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 8px; }
    .check-list label { font-weight: 400; display: flex; gap: 8px; align-items: center; }
    .field-error { color: #b91c1c; font-size: 13px; }
    .form-actions { margin-top: 22px; display: flex; gap: 10px; }
    @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1>{{ $title }}</h1>
    </div>
    <a href="{{ $backUrl }}" class="btn btn-info">← Back</a>
</div>

<div class="card form-card" style="padding:24px;">

    <form action="{{ $action }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <div class="form-grid">
            @foreach ($fields as $field)
                @php
                    $name = $field['name'];
                    $type = $field['type'];
                    $value = old($name, $item->{$name} ?? ($field['default'] ?? ''));
                    if (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    }
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d');
                    }
                    if ($type === 'time' && is_string($value)) {
                        $value = substr($value, 0, 5);
                    }
                    $wide = in_array($type, ['textarea', 'checkboxes', 'file']);
                @endphp

                <div class="form-group {{ $wide ? 'full' : '' }}">
                    <label for="f_{{ $name }}">
                        {{ $field['label'] }}@if (! empty($field['required'])) <span style="color:#b91c1c">*</span>@endif
                    </label>

                    @if ($type === 'textarea')
                        <textarea id="f_{{ $name }}" name="{{ $name }}">{{ $value }}</textarea>

                    @elseif ($type === 'select')
                        <select id="f_{{ $name }}" name="{{ $name }}">
                            <option value="">— Select —</option>
                            @foreach ($field['options'] as $optValue => $optLabel)
                                <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                            @endforeach
                        </select>

                    @elseif ($type === 'checkboxes')
                        @php $chosen = collect(old($name, $field['selected'] ?? []))->map(fn ($v) => (string) $v)->all(); @endphp
                        <div class="check-list">
                            @forelse ($field['options'] as $optValue => $optLabel)
                                <label>
                                    <input type="checkbox" name="{{ $name }}[]" value="{{ $optValue }}"
                                        @checked(in_array((string) $optValue, $chosen, true))>
                                    {{ $optLabel }}
                                </label>
                            @empty
                                <span style="color:#777">Nothing available yet.</span>
                            @endforelse
                        </div>

                    @elseif ($type === 'file')
                        @if ($item->{$name} ?? false)
                            <img src="{{ asset('storage/' . $item->{$name}) }}" alt="" style="height:70px;border-radius:8px;">
                        @endif
                        <input id="f_{{ $name }}" type="file" name="{{ $name }}" accept="image/*">

                    @elseif ($type === 'password')
                        <input id="f_{{ $name }}" type="password" name="{{ $name }}" autocomplete="new-password">

                    @else
                        <input id="f_{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}"
                            @if (! empty($field['step'])) step="{{ $field['step'] }}" @endif
                            @if ($type === 'number' && empty($field['step'])) min="0" @endif>
                    @endif

                    @error($name)
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ $backUrl }}" class="btn btn-info">Cancel</a>
        </div>
    </form>

</div>

@endsection
