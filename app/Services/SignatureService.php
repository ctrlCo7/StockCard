<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores users' electronic signature images on the private disk and stamps copies onto
 * documents. Images are rendered inline as data URIs, so they are never publicly served.
 */
class SignatureService
{
    private const DISK = 'local';

    /** Save a signature drawn in the browser (PNG data URI) or uploaded as an image file. */
    public function store(User $user, UploadedFile|string $signature): void
    {
        [$contents, $extension] = $signature instanceof UploadedFile
            ? [$signature->getContent(), $signature->extension() === 'png' ? 'png' : 'jpg']
            : [self::decodeDrawn($signature), 'png'];

        $path = "signatures/users/{$user->id}-".Str::uuid().".{$extension}";
        Storage::disk(self::DISK)->put($path, $contents);

        $previous = $user->signature_path;
        $user->forceFill(['signature_path' => $path, 'signature_updated_at' => now()])->save();
        if ($previous) {
            Storage::disk(self::DISK)->delete($previous);
        }

        AuditLogger::log('user.signature_updated', "{$user->name} updated their electronic signature", $user);
    }

    public function remove(User $user): void
    {
        if ($user->signature_path) {
            Storage::disk(self::DISK)->delete($user->signature_path);
        }
        $user->forceFill(['signature_path' => null, 'signature_updated_at' => null])->save();

        AuditLogger::log('user.signature_removed', "{$user->name} removed their electronic signature", $user);
    }

    /**
     * Copy the user's current signature for use on a document. Returns null when the user
     * has no signature on file.
     */
    public function snapshot(User $user, string $folder): ?string
    {
        $source = $user->signature_path;
        if (! $source || ! Storage::disk(self::DISK)->exists($source)) {
            return null;
        }

        $copy = "signatures/{$folder}/".Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
        Storage::disk(self::DISK)->copy($source, $copy);

        return $copy;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** Inline image source for <img src>, usable in pages, print views and dompdf PDFs. */
    public function dataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }
        $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';

        return "data:{$mime};base64,".base64_encode(Storage::disk(self::DISK)->get($path));
    }

    /** Raw PNG bytes from a "data:image/png;base64,..." string, or null when it is not a valid PNG. */
    public static function decodeDrawn(string $dataUri): ?string
    {
        if (! str_starts_with($dataUri, 'data:image/png;base64,')) {
            return null;
        }
        $bytes = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
        $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;

        return $info && $info[2] === IMAGETYPE_PNG ? $bytes : null;
    }
}
