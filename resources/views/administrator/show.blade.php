@extends('layouts.admin')

@section('title', $title)

@section('content')

<div class="page-header">
    <div>
        <h1>{{ $title }}</h1>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="{{ $backUrl }}" class="btn btn-info">← Back</a>
        <a href="{{ $editUrl }}" class="btn btn-primary">Edit</a>
    </div>
</div>

<div class="card" style="max-width:760px;padding:8px 24px;">
    <table style="width:100%;border-collapse:collapse;">
        @foreach ($rows as $label => $value)
            <tr style="border-bottom:1px solid #eee;">
                <th style="text-align:left;padding:12px 8px;width:36%;color:#555;font-weight:600;">{{ $label }}</th>
                <td style="padding:12px 8px;">{{ $value !== null && $value !== '' ? $value : '—' }}</td>
            </tr>
        @endforeach
    </table>
</div>

@endsection
