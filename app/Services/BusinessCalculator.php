<?php

namespace App\Services;

use InvalidArgumentException;

final class BusinessCalculator
{
    public function commission(string|float $base, string|float $rate): string
    {
        if ((float)$base < 0 || (float)$rate < 0) throw new InvalidArgumentException('La base et le taux doivent être positifs.');
        return number_format(((float)$base * (float)$rate) / 100, 2, '.', '');
    }

    public function salary(string|float $gross, string $mode, string|float $value): array
    {
        $irsa = $mode === 'pourcentage' ? (float)$this->commission($gross, $value) : (float)$value;
        if ($irsa > (float)$gross) throw new InvalidArgumentException("L'IRSA ne peut pas dépasser le salaire brut.");
        return ['irsa' => number_format($irsa,2,'.',''), 'net' => number_format((float)$gross-$irsa,2,'.','')];
    }
}
