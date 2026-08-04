<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use Illuminate\Http\Request;

class SupermarketController extends Controller
{
    /**
     * GET /v1/supermarkets — Liste publique des supermarchés partenaires
     * approuvés. Sans authentification, comme /payment-requests/{token} :
     * un client doit pouvoir parcourir le catalogue avant de se connecter.
     */
    public function index(Request $request)
    {
        $supermarkets = MerchantProfile::where('is_supermarket', true)
            ->where('status', 'approved')
            ->orderBy('business_name')
            ->get(['id', 'business_name', 'business_address', 'logo_url']);

        return response()->json([
            'success' => true,
            'data' => $supermarkets,
        ], 200);
    }

    /**
     * GET /v1/supermarkets/{id}/products — Catalogue public d'un supermarché
     * donné. Seuls les produits marqués disponibles apparaissent ici — le
     * catalogue complet (avec les indisponibles) reste réservé au marchand
     * lui-même via Merchant\ProductController::index.
     */
    public function products(Request $request, string $id)
    {
        $supermarket = MerchantProfile::where('id', $id)
            ->where('is_supermarket', true)
            ->where('status', 'approved')
            ->first();

        if (!$supermarket) {
            return response()->json([
                'success' => false,
                'message' => 'Supermarché introuvable.',
            ], 404);
        }

        $products = $supermarket->products()
            ->where('is_available', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'supermarket' => [
                    'id' => $supermarket->id,
                    'business_name' => $supermarket->business_name,
                    'business_address' => $supermarket->business_address,
                    'logo_url' => $supermarket->logo_url,
                ],
                'products' => $products,
            ],
        ], 200);
    }
}
