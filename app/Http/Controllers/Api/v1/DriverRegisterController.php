<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class DriverRegisterController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validation des champs textes, du fcm_token et des fichiers physiques
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'fcm_token' => 'nullable|string', // 👈 Prise en charge du jeton Firebase
            
            // Validation des fichiers physiques envoyés depuis l'application
            'profile_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // Max 5Mo
            'cni' => 'required|file|mimes:pdf,jpeg,png,jpg|max:10240', // Max 10Mo
            'license' => 'required|file|mimes:pdf,jpeg,png,jpg|max:10240',
            'selfie' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'criminal_record' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
            'vehicle_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            // nullable et non 'required' : le parcours chauffeur (voiture) ne
            // collecte ni carte grise ni assurance côté Flutter aujourd'hui —
            // les rendre obligatoires ici casserait son inscription. Le
            // parcours livreur, qui les collecte, les impose déjà côté client.
            'vehicle_registration' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
            'insurance' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
            
            'vehicle_type' => 'required|string',
            'vehicle_brand' => 'required|string',
            'vehicle_model' => 'required|string',
            'vehicle_year' => 'required|integer',
            'vehicle_color' => 'required|string',
            'vehicle_plate' => 'required|string|unique:driver_profiles,vehicle_plate',
            'vehicle_seats' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            // 2. Création de l'utilisateur avec rôle et fcm_token
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'role' => 'driver',
                'fcm_token' => $request->fcm_token, // 👈 Enregistrement en BDD
            ]);

            // 3. Stockage des fichiers et génération des URLs dynamiques
            $urls = [];
            $filesToUpload = ['profile_image', 'cni', 'license', 'selfie', 'criminal_record', 'vehicle_image', 'vehicle_registration', 'insurance'];

            foreach ($filesToUpload as $fieldName) {
                if ($request->hasFile($fieldName)) {
                    // Stocke dans storage/app/public/drivers/{id_du_chauffeur}/...
                    $path = $request->file($fieldName)->store("drivers/{$user->id}", 'public');
                    $urls[$fieldName] = asset(Storage::url($path));
                } else {
                    $urls[$fieldName] = null;
                }
            }

            // 4. Création du profil chauffeur avec les URLs générées
            DriverProfile::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'profile_image_url' => $urls['profile_image'],
                'cni_url' => $urls['cni'],
                'license_url' => $urls['license'],
                'selfie_url' => $urls['selfie'],
                'criminal_record_url' => $urls['criminal_record'],
                'vehicle_type' => $validated['vehicle_type'],
                'vehicle_brand' => $validated['vehicle_brand'],
                'vehicle_model' => $validated['vehicle_model'],
                'vehicle_year' => $validated['vehicle_year'],
                'vehicle_color' => $validated['vehicle_color'],
                'vehicle_plate' => $validated['vehicle_plate'],
                'vehicle_seats' => $validated['vehicle_seats'],
                'vehicle_image_url' => $urls['vehicle_image'],
                'vehicle_registration_url' => $urls['vehicle_registration'],
                'insurance_url' => $urls['insurance'],
            ]);

            DB::commit();

            // Génération du jeton JWT
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => "Demande d'inscription reçue avec succès.",
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'status' => 'pending'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Nettoyage des fichiers fraîchement uploadés en cas d'échec
            if (isset($user)) {
                Storage::disk('public')->deleteDirectory("drivers/{$user->id}");
            }

            return response()->json([
                'success' => false,
                'message' => "Impossible de finaliser l'inscription.",
                'error' => $e->getMessage()
            ], 500);
        }
    }
}