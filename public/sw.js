self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    var data = event.notification.data || {};
    var url = data.url || null;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            // Find an open admin tab
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.indexOf('/admin') !== -1 && 'focus' in client) {
                    if (url && client.navigate) {
                        return client.navigate(url).then(function() { return client.focus(); });
                    }
                    return client.focus();
                }
            }
            // If no active tab is open, open a new window
            if (self.clients.openWindow) {
                return self.clients.openWindow(url || '/admin');
            }
        })
    );
});
