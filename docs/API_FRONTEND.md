# API Buudi — guide d'utilisation pour le frontend

Backend déployé et accessible à l'adresse :

```
https://api.buudi.net
```

Toutes les routes API sont préfixées par `/api` (ex. `/api/login`, pas `/login`).

CORS est ouvert (`Access-Control-Allow-Origin: *`), aucune configuration particulière n'est nécessaire côté frontend pour appeler l'API depuis un navigateur ou une app mobile.

## Authentification

L'API utilise des tokens **JWT**. Après login/inscription, le backend retourne un `token` à stocker côté client (localStorage, secure storage mobile...) et à renvoyer dans le header `Authorization` de chaque requête protégée :

```
Authorization: Bearer <token>
```

## Format des réponses

Toutes les réponses sont en JSON, avec un champ `success` (`true`/`false`). En cas d'erreur de validation, le code HTTP est `422` et le détail est dans `errors` :

```json
{
  "success": false,
  "message": "Erreur de validation",
  "errors": { "champ": ["message d'erreur"] }
}
```

## Routes publiques (client)

### Connexion

```
POST /api/login
Content-Type: application/json

{
  "login": "email_ou_telephone",
  "password": "..."
}
```
⚠️ Le champ s'appelle `login` (pas `email`) — accepte indifféremment l'email ou le téléphone.

Réponse `200` :
```json
{ "success": true, "message": "Connexion réussie.", "token": "...", "user": { ... } }
```
Réponse `401` si identifiants invalides.

### Inscription client (par OTP e-mail)

Flux en 2 étapes :

**1. Envoyer le code OTP**
```
POST /api/client/send-otp
Content-Type: application/json

{ "email": "client@example.com" }
```
(alias identique : `POST /api/client/send-email-otp`)

**2. Vérifier le code et créer le compte**
```
POST /api/client/verify-register
Content-Type: application/json

{
  "name": "...",
  "phone": "...",          // optionnel
  "email": "client@example.com",
  "city": "...",
  "birth_date": "YYYY-MM-DD",
  "gender": "...",
  "password": "...",       // min 8 caractères
  "otp_code": "123456",    // reçu par e-mail, 6 chiffres
  "fcm_token": "..."       // optionnel, pour les notifications push
}
```
Réponse `201` avec `token` + `user` — le compte est directement connecté après vérification.

## Routes chauffeur (protégées, header `Authorization` requis)

Toutes préfixées `/api/v1/driver/...` (des alias sans `/v1` existent aussi pour compatibilité avec l'app Flutter existante, mais préférez la version `/v1` pour tout nouveau développement).

### Inscription chauffeur (publique, avec upload de fichiers)

```
POST /api/v1/driver/register
Content-Type: multipart/form-data
```
Champs texte : `name`, `email`, `phone`, `password`, `fcm_token` (optionnel), `vehicle_type`, `vehicle_brand`, `vehicle_model`, `vehicle_year`, `vehicle_color`, `vehicle_plate`, `vehicle_seats`.
Fichiers : `profile_image` (image, max 5 Mo), `cni` (pdf/image, max 10 Mo), `license` (pdf/image, max 10 Mo), `selfie` (image, max 5 Mo), `criminal_record` (optionnel, pdf/image, max 10 Mo), `vehicle_image` (image, max 5 Mo).

Réponse `201` : `token` + `user` (statut `pending`, en attente de validation admin).

### Profil et statut

```
GET  /api/v1/driver/profile      → profil complet (véhicule, note, statut, en ligne/hors ligne)
GET  /api/v1/driver/status       → { status, rejection_reason }
POST /api/v1/driver/toggle-status → bascule en ligne/hors ligne
```
⚠️ `toggle-status` peut renvoyer `402` (`SUBSCRIPTION_REQUIRED`, pass journalier requis) ou `403` (`EXPIRED_DEBT`, dette impayée) en plus de `200`.

### Pass journalier

```
POST /api/v1/driver/buy-pass → active un pass de 2000 FCFA / 24h
```

### Courses

```
GET  /api/v1/driver/dashboard
GET  /api/v1/driver/active-ride
POST /api/v1/driver/rides/{id}/accept
POST /api/v1/driver/rides/{id}/arrive
POST /api/v1/driver/rides/{id}/start
POST /api/v1/driver/rides/{id}/complete
```

### Token FCM (notifications push)

```
POST /api/v1/update-fcm-token
```

## Routes admin (protégées)

```
GET  /api/v1/admin/drivers/pending      → liste des chauffeurs en attente de validation
POST /api/v1/admin/drivers/{id}/status  → valider/rejeter un chauffeur
```

## Exemple avec `fetch`

```js
const res = await fetch('https://api.buudi.net/api/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ login: email, password }),
});
const data = await res.json();
if (data.success) {
  localStorage.setItem('token', data.token);
}
```

Puis pour une route protégée :

```js
const res = await fetch('https://api.buudi.net/api/v1/driver/profile', {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
});
```

## À savoir / limitations actuelles

- Le fichier de credentials Firebase n'est pas encore configuré côté serveur : les notifications push (validation/rejet chauffeur) ne fonctionnent pas encore, mais le reste de l'API (auth, courses, wallet) est opérationnel.
- Pas encore d'endpoint documenté pour l'envoi d'OTP par SMS (uniquement e-mail actuellement).
- L'environnement est en `APP_DEBUG=false` : les erreurs serveur (500) ne renvoient pas de stack trace, seulement un message générique.
