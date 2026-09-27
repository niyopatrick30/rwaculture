// Notifications JavaScript

let notificationPollInterval = null;
let notificationRequestInProgress = false;
const notificationsApiUrl = `${window.RWACULTURE_API_BASE || '/Rwaculture/api'}/notifications.php`;

document.addEventListener('DOMContentLoaded', function() {
    startNotificationPolling();
});

function startNotificationPolling() {
    stopNotificationPolling();
    if (document.hidden) return;
    updateNotificationCount();
    notificationPollInterval = setInterval(updateNotificationCount, 10000);
}

function stopNotificationPolling() {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
        notificationPollInterval = null;
    }
}

function updateNotificationCount() {
    if (notificationRequestInProgress || document.hidden) return;
    notificationRequestInProgress = true;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 5000);

    fetch(`${notificationsApiUrl}?action=get_count`, { signal: controller.signal })
        .then(response => response.json())
        .then(data => {
            if (data.count !== undefined) {
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        })
        .catch(error => {
            if (error.name !== 'AbortError') console.error('Notification request error:', error);
        })
        .finally(() => {
            clearTimeout(timeout);
            notificationRequestInProgress = false;
        });
}

document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopNotificationPolling();
    } else {
        startNotificationPolling();
    }
});

// Mark notification as read
function markAsRead(notificationId) {
    fetch(notificationsApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_read',
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationCount();
        }
    })
    .catch(error => console.error('Error:', error));
}
