<style>
    .flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; }
    .flash-success { background: #e7f6ec; color: #166534; border: 1px solid #bbe5c8; }
    .flash-error { background: #fdecec; color: #991b1b; border: 1px solid #f5c2c2; }
    .flash ul { margin: 6px 0 0 18px; padding: 0; }
</style>

@if (session('success'))
    <div class="flash flash-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="flash flash-error">
        Please fix the following:
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
