@props(['class' => 'btn btn-primary'])
{{-- Tombol simpan yang aktif hanya bila semua isian form sudah benar (getter `valid` di komponen Alpine).
     Server tetap memvalidasi ulang. --}}
<button type="submit" class="{{ $class }}" :disabled="!valid">{{ $slot }}</button>
