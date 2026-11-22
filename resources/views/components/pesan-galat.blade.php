@props(['isian'])
{{-- Pesan galat satu isian. Isi awalnya pesan dari server (setelah submit gagal);
     Alpine menggantinya dengan hasil pengecekan saat isian diketik (resources/js/validasi.js).
     Bootstrap hanya menampilkannya bila isian di atasnya berkelas is-invalid. --}}
<div class="invalid-feedback" x-text="pesan('{{ $isian }}')">{{ $errors->first($isian) }}</div>
