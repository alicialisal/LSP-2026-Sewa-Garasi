<?php

namespace App\Http\Requests\Concerns;

use App\Support\PaymentProofStorage;

/**
 * Aturan dan pesan validasi bukti bayar yang sama untuk sewa baru dan
 * perpanjangan (asumsi nomor 28). Batas diambil dari PaymentProofStorage agar
 * hanya ada satu sumber. Dipakai StoreRentalRequest dan StoreExtensionRequest.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
trait ValidasiBuktiBayar
{
    /**
     * @return array<int, string>
     */
    protected function aturanBuktiBayar(): array
    {
        return ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.PaymentProofStorage::MAKS_KB];
    }

    /**
     * @return array<string, string>
     */
    protected function pesanBuktiBayar(): array
    {
        $batasMb = PaymentProofStorage::MAKS_KB / 1024;

        return [
            'bukti_bayar.required' => 'Bukti bayar wajib diunggah.',
            'bukti_bayar.file' => 'Bukti bayar gagal diunggah.',
            'bukti_bayar.uploaded' => "Bukti bayar gagal diunggah. Ukuran maksimal {$batasMb} MB.",
            'bukti_bayar.mimes' => 'Bukti bayar harus berupa gambar JPG, JPEG, atau PNG.',
            'bukti_bayar.max' => "Ukuran bukti bayar melebihi {$batasMb} MB.",
        ];
    }
}
