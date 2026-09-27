// Location & Map JavaScript

let userLocation = null;
let map = null;
let marker = null;

// Request user location
function requestLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject('Geolocation is not supported by your browser');
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                userLocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };
                resolve(userLocation);
            },
            (error) => {
                let errorMessage = 'Unable to retrieve your location';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = 'Location permission denied. Please enable location access.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = 'Location information unavailable.';
                        break;
                    case error.TIMEOUT:
                        errorMessage = 'Location request timeout.';
                        break;
                }
                reject(errorMessage);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    });
}

// Initialize Map (Google Maps or OpenStreetMap)
function initMap(containerId, centerLat, centerLng, zoom = 15) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // Using OpenStreetMap with Leaflet (no API key needed)
    if (typeof L !== 'undefined') {
        map = L.map(containerId).setView([centerLat, centerLng], zoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        marker = L.marker([centerLat, centerLng]).addTo(map);
    } else {
        // Fallback: Load Leaflet
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);
        
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => initMap(containerId, centerLat, centerLng, zoom);
        document.body.appendChild(script);
    }
}

// Add marker to map
function addMarker(lat, lng, title = '') {
    if (map && typeof L !== 'undefined') {
        if (marker) {
            map.removeLayer(marker);
        }
        marker = L.marker([lat, lng]).addTo(map);
        if (title) {
            marker.bindPopup(title).openPopup();
        }
        map.setView([lat, lng], 15);
    }
}

// Reverse Geocoding (Get address from coordinates)
function reverseGeocode(lat, lng) {
    return fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
            if (data && data.address) {
                const address = data.address;
                return {
                    city: address.city || address.town || address.village || '',
                    area: address.suburb || address.neighbourhood || '',
                    full_address: data.display_name || ''
                };
            }
            return { city: '', area: '', full_address: '' };
        })
        .catch(error => {
            console.error('Geocoding error:', error);
            return { city: '', area: '', full_address: '' };
        });
}

// Calculate ETA
function calculateETA(distanceKm, sameCity = false) {
    if (sameCity) {
        return Math.floor(Math.random() * 24) + 12; // 12-36 hours
    } else {
        return Math.floor(Math.random() * 72) + 48; // 48-120 hours (2-5 days)
    }
}

// Format ETA
function formatETA(hours) {
    if (hours < 24) {
        return `${hours} hours`;
    } else {
        const days = Math.floor(hours / 24);
        const remainingHours = hours % 24;
        if (remainingHours === 0) {
            return `${days} day${days > 1 ? 's' : ''}`;
        } else {
            return `${days} day${days > 1 ? 's' : ''} ${remainingHours} hour${remainingHours > 1 ? 's' : ''}`;
        }
    }
}

// Save location to server
function saveLocation(lat, lng, address, city) {
    return fetch('api/location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            latitude: lat,
            longitude: lng,
            address: address,
            city: city
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            return data;
        } else {
            throw new Error(data.message || 'Failed to save location');
        }
    });
}
