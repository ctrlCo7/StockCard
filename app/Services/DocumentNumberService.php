<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Generates sequential document numbers such as RIS-2026-0001.
 * The sequence row is locked so concurrent requests never receive the same number.
 */
class DocumentNumberService
{
    public const RIS = 'RIS';

    public const RECEIPT = 'RCV';

    public const ISSUANCE = 'ISS';

    public const ADJUSTMENT = 'ADJ';

    public function next(string $type, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($type, $year) {
            DocumentSequence::query()->insertOrIgnore([
                'type' => $type, 'year' => $year, 'last_number' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()
                ->where('type', $type)->where('year', $year)
                ->lockForUpdate()->firstOrFail();

            $sequence->increment('last_number');

            return sprintf('%s-%d-%04d', $type, $year, $sequence->last_number);
        });
    }
}
