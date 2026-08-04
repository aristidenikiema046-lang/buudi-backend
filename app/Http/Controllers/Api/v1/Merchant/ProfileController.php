<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * GET /v1/merchant/profile — Infos du compte + de la boutique.
     * Même format que le ProfileController client pour la clé "user" (modèle
     * User brut). La partie boutique vit dans une table séparée
     * (merchant_profiles), donc elle est ajoutée à côté sous "merchant_profile"
     * plutôt que fusionnée artificiellement dans "user".
     */
    public function show(Request $request)
    {
        $user = Auth::user();
        $merchantProfile = $user->merchantProfile;

        // Accéder à $user->merchantProfile charge la relation sur le modèle,
        // qui la sérialiserait alors une deuxième fois (dans "user") si on ne
        // l'en retire pas explicitement — makeHidden() évite ce doublon.
        $user->makeHidden('merchantProfile');

        return response()->json([
            'success' => true,
            'user' => $user,
            'merchant_profile' => $merchantProfile,
        ], 200);
    }

    /**
     * PUT /v1/merchant/profile — Met à jour nom / email / téléphone (compte)
     * et business_name / business_type / business_address / business_phone
     * (boutique).
     *
     * Email/téléphone suivent exactement la même logique que
     * Client\ProfileController::update() : au moins un des deux doit rester
     * renseigné, et un changement d'email exige un otp_code valide (obtenu
     * via POST /client/send-email-otp sur la nouvelle adresse) — un compte
     * marchand reste un compte avec les mêmes enjeux de sécurité qu'un compte
     * client sur ce point.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', Rule::unique('users', 'phone')->ignore($user->id)],
            'otp_code' => 'sometimes|nullable|string|size:6',
            'business_name' => 'sometimes|string|max:255',
            'business_type' => 'sometimes|nullable|string|max:255',
            'business_address' => 'sometimes|nullable|string|max:255',
            'business_latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'business_longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'business_phone' => 'sometimes|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('email')) {
            $newEmail = $request->email !== null ? strtolower(trim($request->email)) : null;
        } else {
            $newEmail = $user->email;
        }

        $newPhone = $request->has('phone') ? $request->phone : $user->phone;

        if (empty($newEmail) && empty($newPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de retirer à la fois l\'email et le téléphone : au moins un des deux doit rester renseigné.',
            ], 422);
        }

        $emailChanged = $newEmail !== $user->email;

        if ($emailChanged && $newEmail !== null) {
            if (!$request->filled('otp_code')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un code de vérification est requis pour changer d\'email. Demandez-en un via /client/send-email-otp sur la nouvelle adresse.',
                ], 422);
            }

            $verification = EmailVerification::where('email', $newEmail)
                ->where('otp_code', $request->otp_code)
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code de vérification invalide.',
                ], 400);
            }

            if (now()->greaterThan($verification->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le code a expiré. Veuillez en demander un nouveau.',
                ], 400);
            }

            $verification->delete();
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        $user->email = $newEmail;
        $user->phone = $newPhone;
        $user->save();

        $merchantProfile = $user->merchantProfile;
        if ($merchantProfile) {
            $businessUpdates = [];
            foreach (['business_name', 'business_type', 'business_address', 'business_latitude', 'business_longitude', 'business_phone'] as $field) {
                if ($request->has($field)) {
                    $businessUpdates[$field] = $request->$field;
                }
            }
            if (!empty($businessUpdates)) {
                $merchantProfile->update($businessUpdates);
            }
        }

        $user->makeHidden('merchantProfile');

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'user' => $user,
            'merchant_profile' => $merchantProfile,
        ], 200);
    }
}
