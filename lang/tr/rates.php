<?php

return [
    'not_loaded' => 'Döviz ve altın kurları henüz yüklenmedi. Fiyat hesaplama şu an güvenilir değil.',
    'currency_invalid' => 'Döviz kurları okunamadı veya geçersiz. Fiyat hesaplama şu an güvenilir değil.',
    'gold_invalid' => 'Altın kurları okunamadı veya geçersiz. Fiyat hesaplama şu an güvenilir değil.',
    'stale' => 'Kurlar :age eski (son güncelleme: :date). Fiyat hesaplama şu an güvenilir değil.',
    'age' => [
        'one_hour' => '1 saat',
        'hours' => ':count saat',
        'minutes' => ':count dakika',
        'seconds' => ':count saniye',
    ],
    'update_failed' => 'Kurlar güncellenemedi: :error',
    'currency_api_failed' => 'Altınkaynak döviz servisi yanıt vermedi: :url',
    'gold_api_failed' => 'Altınkaynak altın servisi yanıt vermedi: :url',
    'json_invalid' => 'Altınkaynak JSON formatı beklenenden farklı.',
];
