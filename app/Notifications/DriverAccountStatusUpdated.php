<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class DriverAccountStatusUpdated extends Notification
{
    use Queueable;

    protected $status;
    protected $rejectionReason;

    public function __construct($status, $rejectionReason = null)
    {
        $this->status = $status;
        $this->rejectionReason = $rejectionReason;
    }

    /**
     * Détermine les canaux d'envoi de la notification.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Génération de l'email
     */
    public function toMail($notifiable)
    {
        if ($this->status === 'approved') {
            return (new MailMessage)
                ->subject('Votre compte chauffeur a été validé ! 🎉')
                ->greeting("Bonjour {$notifiable->name},")
                ->line('Excellente nouvelle ! Votre dossier chauffeur a été vérifié et approuvé par notre équipe.')
                ->line('Vous pouvez désormais vous connecter à l\'application mobile pour commencer à accepter des courses.')
                ->action('Ouvrir l\'application', url('/'))
                ->line('Bienvenue dans notre équipe !');
        }

        return (new MailMessage)
            ->subject('Mise à jour concernant votre dossier chauffeur')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Votre dossier de candidature chauffeur a été examiné, mais n\'a pas pu être approuvé.')
            ->line('Motif : ' . ($this->rejectionReason ?? 'Documents non conformes.'))
            ->line('Veuillez vous reconnecter sur l\'application pour corriger vos informations et soumettre de nouveau votre dossier.')
            ->action('Mettre à jour mon dossier', url('/'));
    }

    /**
     * Méthode personnalisée pour envoyer la notification Push via Firebase FCM
     */
    public function sendFcmNotification($user, Messaging $messaging)
    {
        if (!$user->fcm_token) {
            \Log::warning("Envoi FCM annulé : fcm_token manquant pour l'utilisateur {$user->id}");
            return;
        }

        $title = $this->status === 'approved' 
            ? 'Compte Validé ! 🎉' 
            : 'Mise à jour de votre dossier';

        $body = $this->status === 'approved'
            ? 'Félicitations, votre dossier chauffeur a été validé.'
            : 'Votre dossier n\'a pas été validé. Motif : ' . ($this->rejectionReason ?? 'Non spécifié');

        $message = CloudMessage::fromArray([
            'token' => $user->fcm_token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
            'android' => [
                'notification' => [
                    'channel_id' => 'high_importance_channel',
                    'sound'      => 'default',
                    'priority'   => 'high',
                ],
            ],
            'data' => [
                'status' => $this->status,
                'type'   => 'driver_status_update',
            ],
        ]);

        $messaging->send($message);
    }
}