<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\EmailVerification;
use App\Mail\VerifyEmailOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // --- MÉTHODE DE CONNEXION (Email ou Téléphone) ---
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string', // Peut être l'email ou le téléphone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $loginInput = trim($request->login);

        // Recherche de l'utilisateur par e-mail OU par téléphone.
        // L'email est comparé en minuscules (PostgreSQL est sensible à la
        // casse) : sans ça, "Test@Gmail.com" et "test@gmail.com" seraient
        // considérés comme deux comptes différents.
        $user = User::where('email', strtolower($loginInput))
                    ->orWhere('phone', $loginInput)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects (Email/Téléphone ou mot de passe invalide).'
            ], 401);
        }

        // Génération du token JWT pour la connexion
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'token'   => $token,
            'user'    => $user
        ], 200);
    }

    // --- ÉTAPE 1 - ENVOI DU CODE OTP PAR EMAIL POUR CLIENT ---
    public function sendOtp(Request $request)
    {
        // Normalisation AVANT validation, pour que la vérification d'unicité
        // et le stockage utilisent la même valeur (voir explication dans login()).
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim($request->email))]);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $otpCode = rand(100000, 999999); // Code à 6 chiffres
        $expiresAt = Carbon::now()->addMinutes(10); // Valide 10 minutes

        EmailVerification::updateOrCreate(
            ['email' => $email],
            [
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt,
            ]
        );

        // Envoi réel de l'e-mail via le système Mailable
        try {
            Mail::to($email)->send(new VerifyEmailOtp($otpCode));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'e-mail : ' . $e->getMessage()
            ], 500);
        }
        
        $response = [
            'success' => true,
            'message' => 'Code de vérification envoyé avec succès par e-mail.',
        ];

        if (app()->environment(['local', 'testing'])) {
            $response['debug_otp'] = $otpCode;
        }

        return response()->json($response, 200);
    }

    // --- ÉTAPE 2 & 4 - VÉRIFICATION ET CRÉATION DU COMPTE CLIENT ---
    public function verifyAndRegisterClient(Request $request)
    {
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim($request->email))]);
        }

        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'phone'      => 'nullable|string|unique:users,phone', // Rendu optionnel pour éviter le blocage
            'email'      => 'required|email|unique:users,email',
            'city'       => 'required|string',
            'birth_date' => 'required|date',
            'gender'     => 'required|string',
            'password'   => 'required|string|min:8',
            'otp_code'   => 'required|string|size:6',
            'fcm_token'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérification de la validité du code OTP
        $verification = EmailVerification::where('email', $request->email)
            ->where('otp_code', $request->otp_code)
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false, 
                'message' => 'Code de vérification invalide.'
            ], 400);
        }

        if (Carbon::now()->greaterThan($verification->expires_at)) {
            return response()->json([
                'success' => false, 
                'message' => 'Le code a expiré. Veuillez en demander un nouveau.'
            ], 400);
        }

        // Création définitive de l'utilisateur avec le rôle 'client'
        $user = User::create([
            'id'                => (string) Str::uuid(),
            'name'              => $request->name,
            'phone'             => $request->phone,
            'email'             => $request->email,
            'city'              => $request->city,
            'birth_date'        => $request->birth_date,
            'gender'            => $request->gender,
            'password'          => Hash::make($request->password),
            'role'              => 'client',
            'fcm_token'         => $request->fcm_token,
            'email_verified_at' => Carbon::now(),
        ]);

        // Nettoyage du code OTP utilisé
        $verification->delete();

        // Génération du token JWT pour connecter directement l'utilisateur
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Compte créé et vérifié avec succès.',
            'token'   => $token,
            'user'    => $user
        ], 201);
    }
}