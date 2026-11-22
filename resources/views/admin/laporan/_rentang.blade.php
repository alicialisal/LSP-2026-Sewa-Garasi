<form method="GET" class="row g-2 align-items-end mb-4">
    <div class="col-sm-4 col-md-3">
        <label for="dari" class="form-label">Dari tanggal</label>
        <input type="date" id="dari" name="dari" value="{{ $dari->toDateString() }}" class="form-control @error('dari') is-invalid @enderror">
        @error('dari')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-4 col-md-3">
        <label for="sampai" class="form-label">Sampai tanggal</label>
        <input type="date" id="sampai" name="sampai" value="{{ $sampai->toDateString() }}" class="form-control @error('sampai') is-invalid @enderror">
        @error('sampai')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-4 col-md-2">
        <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
    </div>
</form>
