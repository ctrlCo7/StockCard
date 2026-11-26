<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A business-rule violation (insufficient stock, invalid workflow transition,
 * duplicate posting, ...). Rendered back to the form as a validation-style error.
 */
class InventoryException extends RuntimeException
{
    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->withErrors(['inventory' => $this->getMessage()])->with('error', $this->getMessage());
    }
}
