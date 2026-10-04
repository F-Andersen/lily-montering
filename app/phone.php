<?php
declare(strict_types=1);

function phone_countries(): array
{
    return [
        'NO' => ['label' => 'Norge', 'prefix' => '47'],
        'SE' => ['label' => 'Sverige', 'prefix' => '46'],
        'DK' => ['label' => 'Danmark', 'prefix' => '45'],
        'FI' => ['label' => 'Finland', 'prefix' => '358'],
        'UA' => ['label' => 'Ukraina', 'prefix' => '380'],
        'PL' => ['label' => 'Polen', 'prefix' => '48'],
        'DE' => ['label' => 'Tyskland', 'prefix' => '49'],
        'GB' => ['label' => 'Storbritannia', 'prefix' => '44'],
        'OTHER' => ['label' => 'Annet', 'prefix' => ''],
    ];
}

function normalize_phone(string $phone, string $country): ?string
{
    $countries = phone_countries();
    if (!isset($countries[$country]) || !preg_match('/^[0-9+() .-]+$/D', $phone)) return null;
    $number = preg_replace('/[() .-]/', '', $phone);
    if (str_starts_with($number, '00')) $number = '+' . substr($number, 2);
    if (!str_starts_with($number, '+')) {
        if ($country === 'OTHER') return null;
        if ($country === 'NO' && !preg_match('/^[0-9]{8}$/D', $number)) return null;
        if (in_array($country, ['SE', 'FI', 'UA', 'DE', 'GB'], true)) $number = preg_replace('/^0/', '', $number);
        $number = '+' . $countries[$country]['prefix'] . $number;
    }
    // This checks international format, not whether the number is assigned or reachable.
    return preg_match('/^\+[1-9][0-9]{6,14}$/D', $number) ? $number : null;
}
