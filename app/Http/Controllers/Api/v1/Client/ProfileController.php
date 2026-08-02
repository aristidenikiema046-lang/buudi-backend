<?php

namespace App\Http\Controllers\Api\v1\Client;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * GET /v1/client/profile — Infos du compte connecté.
     * Même format que login()/verifyAndRegisterClient() (clé "user", modèle
     * brut) pour que le UserModel Flutter n'ait rien à gérer de différent.
     */
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => Auth::user(),
        ], 200);
    }

    /**
     * PUT /v1/client/profile — Met à jour nom / email / téléphone.
     *
     * - name : appliqué directement.
     * - phone : appliqué directement (juste vérifié unique).
     * - email : si la nouvelle valeur diffère de l'email actuel, un
     *   "otp_code" valide (obtenu via POST /client/send-email-otp sur le
     *   NOUVEL email) est exigé avant d'appliquer le changement — même
     *   logique de vérification que l'inscription, pour éviter qu'un jeton
     *   JWT volé permette de détourner le compte en changeant l'email.
     * - Contrainte : il doit rester au moins un email ou un téléphone après
     *   la mise à jour.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', Rule::unique('users', 'phone')->ignore($user->id)],
            'otp_code' => 'sometimes|nullable|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        // "has('email')" est vrai dès que la clé est présente dans le JSON,
        // même si sa valeur est null : on distingue donc bien "le client n'a
        // pas touché ce champ" (on garde l'ancienne valeur) de "le client
        // envoie explicitement null" (il veut vider le champ).
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

        // Si l'email est vidé (newEmail = null), aucune vérification n'est
        // requise : on ne "vérifie" que l'ajout/changement vers une adresse,
        // pas sa suppression.
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

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'user' => $user,
        ], 200);
    }
}
