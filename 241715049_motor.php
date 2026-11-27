<?php
declare(strict_types=1);

function adim(array $s, string $a): array {
    if (!empty($s['bitti'])) {
        return $s;
    }

    $no = (int)$s['no'];
    $x  = $no % 10;              // 241715049 -> 9
    $y  = intdiv($no, 10) % 10;  // 241715049 -> 4

    if ($a === 'M') {
        if ($s['gunluk_musteri'] >= 5) {
            return $s;
        }

        $s['musteri_sira']++;
        $s['gunluk_musteri']++;
        $sira = $s['musteri_sira'];
        $m = musteri($no, $sira);
        $kod = $m['urun'];
        $urun = &$s['urunler'][$kod];

        $adet = !empty($s['yogunluk']) ? 2 : 1;
        $s['yogunluk'] = false;

        $sicakUrun = in_array($kod, ['tost', 'cay', 'kahve'], true);
        $elektrikEngeli = (!empty($s['elektrik']) && $sicakUrun);

        $birimFiyat = $urun['fiyat'];
        if ($m['tip'] === 'Öğrenci' || $m['tip'] === 'Burslu Öğrenci') {
            $birimFiyat = (int)floor($birimFiyat * 0.9);
        }

        $toplamTutar = $birimFiyat * $adet;

        if (!$elektrikEngeli && $urun['stok'] >= $adet && $m['butce'] >= $birimFiyat) {
            $satilan = min($adet, $urun['stok'], (int)floor($m['butce'] / max(1, $birimFiyat)));
            $satilan = max(1, $satilan);
            $urun['stok'] -= $satilan;
            $s['kasa'] += $birimFiyat * $satilan;
            $s['seri']++;
            $s['ret_sayac'][$kod] = 0;

            if ($s['seri'] % 3 === 0) {
                $s['memnuniyet'] = min(100, $s['memnuniyet'] + 2);
            }

            $s['log'][] = "{$m['ad']} ({$m['tip']}, bütçe {$m['butce']} TL) {$urun['ad']} istiyor → Satış: {$satilan} adet";
        } else {
            $s['seri'] = 0;
            $s['gunluk_ret']++;
            $s['ret_sayac'][$kod] = ($s['ret_sayac'][$kod] ?? 0) + 1;
            $s['memnuniyet'] = max(0, $s['memnuniyet'] - 2);

            if ($s['ret_sayac'][$kod] >= 2 && empty($s['zam'][$kod])) {
                $urun['fiyat'] += 5;
                $s['zam'][$kod] = true;
            }

            $s['log'][] = "{$m['ad']} ({$m['tip']}, bütçe {$m['butce']} TL) {$urun['ad']} istiyor → Satış yok";
        }
        unset($urun);

        $olay = olay($no, $sira);
        if ($olay === 'yogunluk') {
            $s['yogunluk'] = true;
            $s['log'][] = 'Öğle yoğunluğu';
        } elseif ($olay === 'elektrik') {
            $s['elektrik'] = 1;
            $s['log'][] = 'Elektrik kesintisi';
        } elseif ($olay === 'tedarik') {
            foreach ($s['urunler'] as $k => &$u) {
                if ($u['stok'] > 0) {
                    $u['stok']--;
                }
            }
            unset($u);
            $s['log'][] = 'Tedarik sorunu';
        } elseif ($olay === 'bahsis') {
            $s['kasa'] += 5;
            $s['log'][] = 'Bahşiş';
        }
    } elseif ($a === 'G') {
        $s['log'][] = "Gün {$s['gun']} kapandı";
        $s['kasa'] -= 60;
        $s['memnuniyet'] = max(0, $s['memnuniyet'] - $s['gunluk_ret']);
        $s['gun']++;
        $s['gunluk_musteri'] = 0;
        $s['gunluk_ret'] = 0;
        $s['elektrik'] = 0;
        $s['yogunluk'] = false;

        if ($s['gun'] > 5 || $s['kasa'] < 0 || $s['memnuniyet'] <= 0) {
            $s['bitti'] = true;
            $s['kazandi'] = ($s['gun'] > 5 && $s['kasa'] >= 1000 && $s['memnuniyet'] >= 50);
        }
    } elseif (str_starts_with($a, 'T-')) {
        $parca = explode('-', strtolower($a));
        $kod = $parca[1] ?? '';
        $adet = max(1, (int)($parca[2] ?? 1));

        if (isset($s['urunler'][$kod])) {
            $birimMaliyet = (int)ceil($s['urunler'][$kod]['fiyat'] * 0.5);
            $toplam = $birimMaliyet * $adet;
            if ($s['kasa'] >= $toplam) {
                $s['kasa'] -= $toplam;
                $s['urunler'][$kod]['stok'] += $adet;
                $s['log'][] = "Tedarik: {$s['urunler'][$kod]['ad']} (+{$adet})";
            }
        }
    }

    return $s;
}
