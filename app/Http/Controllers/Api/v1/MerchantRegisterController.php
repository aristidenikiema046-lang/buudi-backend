<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MerchantProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class MerchantRegisterController extends Controller
{
    public function register(Request $request)
    {
        // Email normalisé en minuscules avant validation, pour éviter le même
        // problème de casse déjà corrigé sur AuthController (Test@x.com vs
        // test@x.com traités comme deux comptes différents par Postgres).
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim($request->email))]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'fcm_token' => 'nullable|string',

            'business_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'business_address' => 'nullable|string|max:255',
            'business_phone' => 'nullable|string|max:20',

            // Logo/photo de la boutique. Pas de documents d'identité (CNI,
            // permis, casier...) contrairement à l'inscription chauffeur :
            // un commerçant n'opère pas de véhicule sur la voie publique, le
            // même niveau de vérification ne se justifie pas pour ce premier
            // jet. Le dossier reste "pending" et passe par validation admin
            // sur la base des infos boutique, comme un KYB léger.
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'role' => 'merchant',
                'fcm_token' => $request->fcm_token,
            ]);

            $logoUrl = null;
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store("merchants/{$user->id}", 'public');
                $logoUrl = asset(Storage::url($path));
            }

            MerchantProfile::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'business_name' => $validated['business_name'],
                'business_type' => $validated['business_type'] ?? null,
                'business_address' => $validated['business_address'] ?? null,
                'business_phone' => $validated['business_phone'] ?? null,
                'logo_url' => $logoUrl,
            ]);

            DB::commit();

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => "Demande d'inscription reçue avec succès.",
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'status' => 'pending',
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($user)) {
                Storage::disk('public')->deleteDirectory("merchants/{$user->id}");
            }

            return response()->json([
                'success' => false,
                'message' => "Impossible de finaliser l'inscription.",
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
