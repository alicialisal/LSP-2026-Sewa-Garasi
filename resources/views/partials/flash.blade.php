@if (session('status'))
    <div class="alert alert-primary" role="status">{{ session('status') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

@error('umum')
    <div class="alert alert-danger" role="alert">{{ $message }}</div>
@enderror

@if ($errors->any() && ! $errors->has('umum'))
    <div class="alert alert-danger" role="alert">Periksa kembali isian yang ditandai merah.</div>
@endif
