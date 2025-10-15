<?php
declare(strict_types=1);

/**
 * Système de notifications et d'alertes EVENT2
 */

class NotificationSystem {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Récupérer les notifications d'un utilisateur
     */
    public function getUserNotifications(int $userId, int $limit = 10): array {
        try {
            $stmt = $this->db->prepare("
                SELECT n.*, u.nom_complet as from_user_name
                FROM notifications n
                LEFT JOIN utilisateurs u ON n.from_user_id = u.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Créer une notification
     */
    public function createNotification(int $userId, string $type, string $title, string $message, int $fromUserId = null): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, from_user_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([$userId, $type, $title, $message, $fromUserId]);
        } catch (PDOException $e) {
            error_log("Erreur création notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(int $notificationId, int $userId): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications
                SET is_read = TRUE, read_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notificationId, $userId]);
        } catch (PDOException $e) {
            error_log("Erreur marquage notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Compter les notifications non lues
     */
    public function countUnread(int $userId): int {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erreur comptage notifications: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Créer une notification pour inscription à un événement
     */
    public function notifyEventRegistration(int $eventId, int $participantId): bool {
        try {
            // Récupérer les infos de l'événement
            $stmt = $this->db->prepare("
                SELECT titre, organisateur_id FROM evenements WHERE id = ?
            ");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) return false;

            // Récupérer les infos du participant
            $stmt = $this->db->prepare("
                SELECT nom_complet FROM utilisateurs WHERE id = ?
            ");
            $stmt->execute([$participantId]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$participant) return false;

            // Notification pour l'organisateur
            return $this->createNotification(
                $event['organisateur_id'],
                'inscription',
                'Nouvelle inscription',
                "Nouveau participant: {$participant['nom_complet']}",
                $participantId
            );
        } catch (PDOException $e) {
            error_log("Erreur notification inscription: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Créer une notification pour validation d'événement
     */
    public function notifyEventValidation(int $eventId, int $organisateurId): bool {
        try {
            return $this->createNotification(
                $organisateurId,
                'validation',
                'Événement validé',
                'Votre événement a été approuvé par l\'administration',
                null
            );
        } catch (PDOException $e) {
            error_log("Erreur notification validation: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Fonction helper pour afficher les notifications
 */
function displayNotifications(PDO $db, int $userId): void {
    $notificationSystem = new NotificationSystem($db);
    $notifications = $notificationSystem->getUserNotifications($userId, 5);
    $unreadCount = $notificationSystem->countUnread($userId);

    if (empty($notifications) && $unreadCount === 0) {
        return;
    }
    ?>
    <div class="notifications-dropdown">
        <button class="notification-toggle btn btn-secondary" onclick="toggleNotifications()">
            <i data-lucide="bell"></i>
            <?php if ($unreadCount > 0): ?>
                <span class="notification-badge"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </button>

        <div class="notifications-panel" id="notificationsPanel">
            <div class="notifications-header">
                <h3>
                    <i data-lucide="bell"></i>
                    Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="unread-count"><?php echo $unreadCount; ?> nouvelles</span>
                    <?php endif; ?>
                </h3>
                <?php if (!empty($notifications)): ?>
                    <button onclick="markAllAsRead()" class="btn btn-sm btn-primary">
                        Tout marquer comme lu
                    </button>
                <?php endif; ?>
            </div>

            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications">
                        <i data-lucide="bell-off"></i>
                        <p>Aucune notification</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>"
                             onclick="markAsRead(<?php echo $notification['id']; ?>)">
                            <div class="notification-icon">
                                <i data-lucide="<?php echo getNotificationIcon($notification['type']); ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                                <div class="notification-time">
                                    <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                </div>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <div class="notification-indicator"></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($notifications)): ?>
                <div class="notifications-footer">
                    <a href="notifications.php" class="btn btn-secondary btn-sm">
                        Voir toutes les notifications
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .notifications-dropdown {
        position: relative;
    }

    .notification-toggle {
        position: relative;
        background: none;
        border: none;
        padding: 0.75rem;
        cursor: pointer;
    }

    .notifications-panel {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        width: 400px;
        max-height: 500px;
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        z-index: 1000;
        overflow: hidden;
    }

    .notifications-panel.active {
        display: block;
        animation: slideIn 0.3s ease;
    }

    .notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--grey-light);
    }

    .notifications-header h3 {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }

    .unread-count {
        background: var(--cerise-primary);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .notifications-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .notification-item {
        display: flex;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--grey-light);
        cursor: pointer;
        transition: background-color 0.3s ease;
        position: relative;
    }

    .notification-item:hover {
        background: var(--grey-light);
    }

    .notification-item.unread {
        background: rgba(210, 10, 46, 0.02);
        border-left: 3px solid var(--cerise-primary);
    }

    .notification-icon {
        margin-right: 1rem;
        color: var(--cerise-primary);
    }

    .notification-content {
        flex: 1;
    }

    .notification-title {
        font-weight: 600;
        color: var(--grey-dark);
        margin-bottom: 0.25rem;
    }

    .notification-message {
        font-size: 0.875rem;
        color: var(--grey-medium);
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }

    .notification-time {
        font-size: 0.75rem;
        color: var(--grey-medium);
    }

    .notification-indicator {
        width: 8px;
        height: 8px;
        background: var(--cerise-primary);
        border-radius: 50%;
        position: absolute;
        top: 1rem;
        right: 1.5rem;
    }

    .no-notifications {
        text-align: center;
        padding: 3rem;
        color: var(--grey-medium);
    }

    .no-notifications i {
        width: 48px;
        height: 48px;
        margin: 0 auto 1rem;
        opacity: 0.5;
    }

    .notifications-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--grey-light);
        text-align: center;
    }

    /* Mobile */
    @media (max-width: 768px) {
        .notifications-panel {
            width: 350px;
            right: -50px;
        }
    }

    @media (max-width: 480px) {
        .notifications-panel {
            width: 300px;
            right: -100px;
        }
    }
    </style>

    <script>
    function toggleNotifications() {
        const panel = document.getElementById('notificationsPanel');
        panel.classList.toggle('active');
    }

    function markAsRead(notificationId) {
        // Simulation AJAX - à implémenter
        console.log('Marquer comme lu:', notificationId);
    }

    function markAllAsRead() {
        // Simulation AJAX - à implémenter
        console.log('Marquer toutes comme lues');
    }

    // Fermer le panneau en cliquant dehors
    document.addEventListener('click', function(event) {
        const panel = document.getElementById('notificationsPanel');
        const toggle = document.querySelector('.notification-toggle');

        if (!panel.contains(event.target) && !toggle.contains(event.target)) {
            panel.classList.remove('active');
        }
    });
    </script>
    <?php
}

/**
 * Récupérer l'icône appropriée selon le type de notification
 */
function getNotificationIcon(string $type): string {
    return match ($type) {
        'inscription' => 'user-plus',
        'validation' => 'check-circle',
        'message' => 'message-circle',
        'warning' => 'alert-triangle',
        'info' => 'info',
        default => 'bell'
    };
}
?>