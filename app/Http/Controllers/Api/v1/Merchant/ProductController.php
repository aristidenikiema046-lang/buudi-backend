<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Bloque tant que le dossier n'est pas "approved" ET marqué supermarché.
     * Même garde-fou que les autres contrôleurs Merchant (petite duplication
     * assumée plutôt qu'une abstraction partagée, cohérent avec le reste du
     * code — voir Merchant\PaymentRequestController::requireApprovedMerchant).
     */
    private function requireApprovedSupermarket(): ?\Illuminate\Http\JsonResponse
    {
        $merchantProfile = Auth::user()->merchantProfile;

        if (!$merchantProfile || $merchantProfile->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est en attente d\'approbation par l\'administration.',
            ], 403);
        }

        if (!$merchantProfile->is_supermarket) {
            return response()->json([
                'success' => false,
                'message' => 'Cette fonctionnalité est réservée aux comptes supermarché.',
            ], 403);
        }

        return null;
    }

    /**
     * GET /v1/merchant/products — Catalogue complet du supermarché connecté
     * (disponibles ET indisponibles, contrairement à la version publique
     * SupermarketController::products).
     */
    public function index(Request $request)
    {
        if ($blocked = $this->requireApprovedSupermarket()) {
            return $blocked;
        }

        $products = Auth::user()->merchantProfile->products()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ], 200);
    }

    /**
     * POST /v1/merchant/products — Ajoute un produit au catalogue.
     */
    public function store(Request $request)
    {
        if ($blocked = $this->requireApprovedSupermarket()) {
            return $blocked;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'image_url' => 'nullable|string|max:2048',
            'is_available' => 'sometimes|boolean',
            'stock_quantity' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = Auth::user()->merchantProfile->products()->create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'category' => $request->category,
            'image_url' => $request->image_url,
            'is_available' => $request->boolean('is_available', true),
            'stock_quantity' => $request->stock_quantity,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté au catalogue.',
            'data' => $product,
        ], 201);
    }

    /**
     * PUT /v1/merchant/products/{id} — Modifie un produit (prix, disponibilité,
     * etc). Recherche restreinte au supermarché connecté : impossible de
     * modifier le produit d'un autre marchand même en devinant son UUID.
     */
    public function update(Request $request, string $id)
    {
        if ($blocked = $this->requireApprovedSupermarket()) {
            return $blocked;
        }

        $product = Auth::user()->merchantProfile->products()->where('id', $id)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit introuvable.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|nullable|string|max:255',
            'image_url' => 'sometimes|nullable|string|max:2048',
            'is_available' => 'sometimes|boolean',
            'stock_quantity' => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour.',
            'data' => $product,
        ], 200);
    }
}
