@if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">
        {{ session('status') }}
    </div>
@endif
