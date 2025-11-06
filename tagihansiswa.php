public function tagihan_siswakelas()
{
    if (!$this->request->getVar('id_siswakelas')) {
        echo '<div class="alert alert-purple alert-dismissible">';
        echo '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>';
        echo '<h5><i class="bi bi-info-lg"></i> Perhatian!</h5>';
        echo 'Anda harus memilih <b>Kelas</b> dan <b>Siswa</b> terlebih dahulu untuk menampilkan <b>Item Penerimaan</b>.';
        echo '</div>';
        return;
    }

    $this->SiswakelasModel->join("perkelasan", "siswakelas.id_perkelasan=perkelasan.id_perkelasan");
    $siswakelas = $this->SiswakelasModel->find($this->request->getVar('id_siswakelas'));

    $where1 = [
        'bayar.id_sekolah'      => $this->id_sekolah,
        'bayar.id_tahun_ajaran' => $siswakelas['id_tahun_ajaran'],
        'bayar.id_perkelasan'   => $siswakelas['id_perkelasan'],
    ];
    $bayar = $this->BayarModel->where($where1)->findAll();

    $tagihan = [];

    foreach ($bayar as $key => $value) {
        // Ambil data pembayaran yang sudah BENAR-BENAR dibayar (tidak NULL)
        $pembayaran = $this->TagihanModel
        ->where('id_bayar', $value['id_bayar'])
        ->where('id_siswakelas', $siswakelas['id_siswakelas'])
        ->where('jumlah_pembayaran IS NOT NULL')
        ->where('tanggal_pembayaran IS NOT NULL')
        ->where('id_rekening IS NOT NULL') // Tambahkan pengecekan id_rekening
        ->where('deleted_at', null)
        ->findAll();

        $terbayar  = 0;
        $terpotong = 0;
        foreach ($pembayaran as $v) {
            $terbayar  += (int)$v['jumlah_pembayaran'] - (int)$v['potongan_tagihan'];
            $terpotong += (int)$v['potongan_tagihan'];
        }

        // Ambil data potongan
        $potonganRow = $this->PotonganModel
        ->where('id_bayar', $value['id_bayar'])
        ->where('id_siswakelas', $siswakelas['id_siswakelas'])
        ->first();
        $potongan = empty($potonganRow) ? 0 : (int)$potonganRow['nominal_potongan'];

        if ($value['jenis_bayar'] == 'bulanan') {
            $terpotong += (12 - count($pembayaran)) * $potongan;
        } else {
            if ($value['angsur_bayar'] == 'ya') {
                $terpotong = $terpotong <= $potongan ? $potongan : $terpotong;
            } else {
                $terpotong = $terpotong == 0 ? $potongan : $terpotong;
            }
        }

        $tagihan[$key] = [
            'id_bayar'  => $value['id_bayar'],
            'sifat'     => $value['sifat_bayar'],
            'jenis'     => $value['jenis_bayar'],
            'untuk'     => $value['untuk_bayar'],
            'nominal'   => (int)$value['nominal_bayar'],
            'angsur'    => $value['angsur_bayar'],
            'potongan'  => (int)$potongan,
            'tagihan'   => $value['jenis_bayar'] == 'bulanan'
            ? ((int)$value['nominal_bayar'] * 12)
            : (int)$value['nominal_bayar'],
            'terpotong' => (int)$terpotong,
            'terbayar'  => (int)$terbayar,
        ];
        $tagihan[$key]['sisa'] = $tagihan[$key]['tagihan'] - $tagihan[$key]['terpotong'] - $tagihan[$key]['terbayar'];

        // PERBAIKAN untuk tagihan bulanan - ambil data yang sudah digenerate
        if ($value['jenis_bayar'] === 'bulanan') {
            // Ambil semua data tagihan bulanan yang sudah digenerate (baik yang sudah dibayar maupun belum)
            $allTagihanBulanan = $this->TagihanModel
            ->select('id_tagihan, periode_nama, jumlah_pembayaran, tanggal_pembayaran, id_rekening')
            ->where('id_bayar', $value['id_bayar'])
            ->where('id_siswakelas', $siswakelas['id_siswakelas'])
            ->where('jenis_tagihan', 'bulanan')
            ->where('deleted_at', null)
            ->findAll();

            $paidMonths = [];
            $unpaidTagihan = [];

            foreach ($allTagihanBulanan as $row) {
                if (!empty($row['periode_nama'])) {
                    $bulan = strtolower(trim($row['periode_nama']));
                    
                    // Jika sudah dibayar (semua field terisi)
                    if (!is_null($row['jumlah_pembayaran']) && 
                        !is_null($row['tanggal_pembayaran']) && 
                        !is_null($row['id_rekening'])) {
                        $paidMonths[] = $bulan;
                } else {
                        // Jika belum dibayar (masih ada yang NULL) - simpan untuk ditampilkan
                    $unpaidTagihan[] = [
                        'id_tagihan' => $row['id_tagihan'],
                        'bulan' => $bulan
                    ];
                }
            }
        }

        $tagihan[$key]['bulan_belum_bayar'] = array_column($unpaidTagihan, 'bulan');
        $tagihan[$key]['bulan_terbayar'] = array_unique($paidMonths);
        $tagihan[$key]['unpaid_tagihan_data'] = $unpaidTagihan;

    } else {
            // Untuk tagihan satuan - cek status pembayaran
        if ($value['angsur_bayar'] === 'ya') {
                // Untuk tagihan yang bisa diangsur
                // Cek apakah ada data yang belum dibayar (untuk update)
            $unpaidSatuan = $this->TagihanModel
            ->select('id_tagihan')
            ->where('id_bayar', $value['id_bayar'])
            ->where('id_siswakelas', $siswakelas['id_siswakelas'])
            ->where('jenis_tagihan !=', 'bulanan')
            ->where('jumlah_pembayaran IS NULL')
            ->where('tanggal_pembayaran IS NULL')
            ->where('id_rekening IS NULL')
            ->where('deleted_at', null)
            ->first();

                // Hitung berapa kali sudah dibayar (untuk menentukan angsuran ke berapa)
            $countPaid = $this->TagihanModel
            ->where('id_bayar', $value['id_bayar'])
            ->where('id_siswakelas', $siswakelas['id_siswakelas'])
            ->where('jenis_tagihan !=', 'bulanan')
            ->where('jumlah_pembayaran IS NOT NULL')
            ->where('tanggal_pembayaran IS NOT NULL')
            ->where('id_rekening IS NOT NULL')
            ->where('deleted_at', null)
            ->countAllResults();

            $tagihan[$key]['unpaid_satuan_id'] = $unpaidSatuan ? $unpaidSatuan['id_tagihan'] : null;
            $tagihan[$key]['is_first_payment'] = ($countPaid == 0 && $unpaidSatuan);
            $tagihan[$key]['angsuran_ke'] = $countPaid + 1;
        } else {
                // Untuk tagihan sekali bayar
            $unpaidSatuan = $this->TagihanModel
            ->select('id_tagihan')
            ->where('id_bayar', $value['id_bayar'])
            ->where('id_siswakelas', $siswakelas['id_siswakelas'])
            ->where('jenis_tagihan !=', 'bulanan')
            ->where('jumlah_pembayaran IS NULL')
            ->where('tanggal_pembayaran IS NULL')
            ->where('id_rekening IS NULL')
            ->where('deleted_at', null)
            ->first();

            $tagihan[$key]['unpaid_satuan_id'] = $unpaidSatuan ? $unpaidSatuan['id_tagihan'] : null;
            $tagihan[$key]['is_first_payment'] = true;
            $tagihan[$key]['angsuran_ke'] = 1;
        }

        $tagihan[$key]['bulan_belum_bayar'] = [];
        $tagihan[$key]['bulan_terbayar'] = [];
    }
}

if (empty($tagihan)) {
    echo "";
    return;
}

echo "<label>Item Pembayaran</label>";

foreach ($tagihan as $key => $value) {
    $untuk  = strlen($value['untuk']) > 3 ? ucfirst($value['untuk']) : strtoupper($value['untuk']);
    $sifat  = $value['sifat'] == 'wajib' ? " <small class='text-success'>(Wajib)</small>" : "";
    $angsur = $value['angsur'] == 'ya' ? " <small class='text-info'>(Angsur)</small>" : "";

    echo "<div class='row'>";
    echo "  <div class='col-md-6'>";
    if ($value['jenis'] == 'bulanan') {
        echo "    <label class='mb-0'>{$untuk}{$sifat}{$angsur}</label>";
        echo "    <small> @ Rp. ".number_format(max($value['nominal'] - $value['potongan'],0), 0, ',', '.')."</small>";
    } else {
        echo "    <label class='mb-0'>{$untuk}{$angsur}</label>";
    }

    echo "    <p class='mb-1 text-muted small'>";
    if ($value['terpotong'] == 0) {
        echo "      Tagihan : Rp. ".number_format($value['tagihan'], 0, ',', '.')." <br>";
    } else {
        echo "      Tagihan : Rp. ".number_format($value['tagihan'], 0, ',', '.')." dg Potongan : Rp. ".number_format($value['terpotong'], 0, ',', '.')."<br>";
    }

    if ($value['sisa'] <= 0) {
        echo "      Terbayar : Rp. ".number_format($value['terbayar'], 0, ',', '.')." - <strong class='text-success'>Sudah Lunas</strong>";
    } else {
        echo "      Terbayar : Rp. ".number_format($value['terbayar'], 0, ',', '.')." dg <strong>kurang bayar : Rp. ".number_format($value['sisa'], 0, ',', '.')."</strong>";
    }
    echo "    </p>";
    echo "  </div>";

    if ($value['jenis'] !== 'bulanan') {
            // Untuk tagihan SATUAN
        if ($value['sisa'] <= 0) {
            echo "  <div class='col-md-6 offset-md-6'>";
            echo "    <div class='alert alert-success mt-2 mb-0 py-1 px-2'><strong>Sudah Lunas</strong></div>";
            echo "  </div>";
        } else {
            $disabled = "";
            $placeholder = "Nominal";

            echo "  <div class='col-md-1'>";
            echo "    <div class='form-group mt-3'>";
            echo "      <div class='custom-control custom-checkbox'>";
            echo "        <input class='custom-control-input box' type='checkbox' id='check{$value['id_bayar']}' data-target='#input{$value['id_bayar']}' name='id_bayar[]' value='{$value['id_bayar']}' {$disabled}>";
            echo "        <label for='check{$value['id_bayar']}' class='custom-control-label'></label>";
            echo "      </div>";
            echo "    </div>";
            echo "  </div>";
            echo "  <div class='col-md-5'>";
            echo "    <input type='text' class='form-control form-control-border mt-2 nominal inputbox' id='input{$value['id_bayar']}' name='nominal_pembayaran[]' placeholder='{$placeholder}' disabled='disabled'>";
            echo "    <input type='hidden' name='periode_nama[]' value='".$value['untuk']."'>";
            echo "    <input type='hidden' name='id_bayar_ref[]' value='{$value['id_bayar']}'>";

                // Tambahkan informasi untuk menentukan UPDATE atau INSERT
            if (!empty($value['unpaid_satuan_id'])) {
                echo "    <input type='hidden' name='id_tagihan_update[]' value='{$value['unpaid_satuan_id']}'>";
                echo "    <input type='hidden' name='is_first_payment[]' value='" . ($value['is_first_payment'] ? '1' : '0') . "'>";
            } else {
                echo "    <input type='hidden' name='id_tagihan_update[]' value=''>";
                echo "    <input type='hidden' name='is_first_payment[]' value='0'>";
            }
            echo "    <input type='hidden' name='angsuran_ke[]' value='{$value['angsuran_ke']}'>";

            echo "  </div>";
        }
    } else {
            // Untuk tagihan BULANAN (tetap sama)
        echo "  <div class='col-md-6'></div>";

        $nominalPerBulan = max($value['nominal'] - $value['potongan'], 0);
        $unpaidMonths = $value['bulan_belum_bayar'];
        $unpaidData = $value['unpaid_tagihan_data'] ?? [];

        if (empty($unpaidMonths)) {
            echo "  <div class='col-md-6 offset-md-6'>";
            echo "    <div class='alert alert-success mt-2 mb-0 py-1 px-2'><strong>Semua bulan telah lunas.</strong></div>";
            echo "  </div>";
        } else {
            $tagihanIdMap = [];
            foreach ($unpaidData as $ud) {
                $tagihanIdMap[$ud['bulan']] = $ud['id_tagihan'];
            }

            foreach ($unpaidMonths as $bulan) {
                $idC  = "check{$value['id_bayar']}_{$bulan}";
                $idIn = "input{$value['id_bayar']}_{$bulan}";
                $idTagihan = $tagihanIdMap[$bulan] ?? '';

                echo "  <div class='col-md-1'>";
                echo "    <div class='form-group mt-2'>";
                echo "      <div class='custom-control custom-checkbox'>";
                echo "        <input class='custom-control-input box-bulan' type='checkbox' id='{$idC}' data-target='#{$idIn}'>";
                echo "        <label for='{$idC}' class='custom-control-label'></label>";
                echo "      </div>";
                echo "    </div>";
                echo "  </div>";
                echo "  <div class='col-md-5'>";
                echo "    <div class='input-group mt-2'>";
                echo "      <div class='input-group-prepend'><span class='input-group-text' style='min-width:100px;'>".ucfirst($bulan)."</span></div>";
                echo "      <input type='text' class='form-control form-control-border nominal inputbox' id='{$idIn}' name='nominal_pembayaran[]' placeholder='Nominal' value='Rp. ".number_format($nominalPerBulan,0,',','.')."' disabled>";
                echo "    </div>";

                echo "    <input type='hidden' name='periode_nama[]' value='{$bulan}' disabled>";
                echo "    <input type='hidden' name='id_bayar_ref[]' value='{$value['id_bayar']}' disabled>";
                echo "    <input type='hidden' name='id_tagihan_update[]' value='{$idTagihan}' disabled>";
                echo "    <input type='hidden' name='is_first_payment[]' value='1' disabled>";
                echo "    <input type='hidden' name='angsuran_ke[]' value='1' disabled>";
                echo "  </div>";
            }
        }
    }

    echo "</div>";
}

    // Tabungan section (tetap sama)
echo "<div class='row'>";
echo "  <div class='col-md-6'><label class='mt-3'>Tabungan</label></div>";
echo "  <div class='col-md-1'>";
echo "    <div class='form-group mt-3'>";
echo "      <div class='custom-control custom-checkbox'>";
echo "        <input class='custom-control-input box' type='checkbox' id='checktabungan' data-target='#inputtabungan' name='tabungan' value='tabungan'>";
echo "        <label for='checktabungan' class='custom-control-label'></label>";
echo "      </div>";
echo "    </div>";
echo "  </div>";
echo "  <div class='col-md-5'>";
echo "    <input type='text' class='form-control form-control-border mt-2 nominal inputbox' id='inputtabungan' name='nominal_tabungan' placeholder='Nominal' disabled='disabled'>";
echo "  </div>";
echo "</div>";
}
