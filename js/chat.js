// Live Chat JavaScript

let chatWindow = null;
let chatMessages = null;
let chatInput = null;
let chatSend = null;
let chatToggle = null;
let chatClose = null;
let chatId = Number(window.RWACULTURE_CHAT_ID || 0) || null;
let pollInterval = null;
const chatApiUrl = `${window.RWACULTURE_API_BASE || '/Rwaculture/api'}/chat.php`;
let chatSellerId = Number(window.RWACULTURE_CHAT_SELLER_ID || 0);

document.addEventListener('DOMContentLoaded', function() {
    chatWindow = document.getElementById('chatWindow');
    chatMessages = document.getElementById('chatMessages');
    chatInput = document.getElementById('chatMessageInput');
    chatSend = document.getElementById('chatSend');
    chatToggle = document.getElementById('chatToggle');
    chatClose = document.getElementById('chatClose');
    const chatTarget = document.getElementById('chatTarget');

    if (chatTarget) {
        const sellers = window.RWACULTURE_CHAT_SELLERS || [];
        sellers.forEach(seller => {
            const option = document.createElement('option');
            option.value = String(seller.id);
            option.textContent = seller.name;
            if (Number(seller.id) === chatSellerId) option.selected = true;
            chatTarget.appendChild(option);
        });
        chatTarget.addEventListener('change', function() {
            chatSellerId = this.value === 'admin' ? 0 : Number(this.value);
            chatId = null;
            loadChat();
        });
    }
    
    if (chatToggle) {
        chatToggle.addEventListener('click', toggleChat);
    }
    
    if (chatClose) {
        chatClose.addEventListener('click', closeChat);
    }
    
    if (chatSend) {
        chatSend.addEventListener('click', sendMessage);
    }
    
    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }

    if (new URLSearchParams(window.location.search).get('open_chat') === '1' && chatSellerId > 0) {
        if (chatTarget) chatTarget.value = String(chatSellerId);
        openChat();
    }
    
    // Load existing chat if window is open
    if (chatWindow && chatWindow.style.display !== 'none') {
        loadChat();
        startPolling();
    }
});

function toggleChat() {
    if (chatWindow.style.display === 'none' || !chatWindow.style.display) {
        openChat();
    } else {
        closeChat();
    }
}

function openChat() {
    chatWindow.style.display = 'flex';
    loadChat();
    startPolling();
}

function closeChat() {
    chatWindow.style.display = 'none';
    stopPolling();
}

function loadChat() {
    if (chatId) {
        fetch(`${chatApiUrl}?action=get_messages&chat_id=${chatId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) displayMessages(data.messages || []);
            })
            .catch(error => console.error('Error:', error));
        return;
    }

    const sellerQuery = chatSellerId > 0 ? `&seller_id=${chatSellerId}` : '';
    fetch(`${chatApiUrl}?action=get_chat${sellerQuery}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                chatId = data.chat_id;
                displayMessages(data.messages || []);
            }
        })
        .catch(error => console.error('Error:', error));
}

function sendMessage() {
    const message = chatInput.value.trim();
    if (!message) return;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('message', message);
    if (chatId) {
        formData.append('chat_id', chatId);
    }
    if (chatSellerId > 0) {
        formData.append('seller_id', chatSellerId);
    }
    
    fetch(chatApiUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            chatInput.value = '';
            if (data.chat_id) {
                chatId = data.chat_id;
            }
            loadChat();
        } else {
            alert(data.message || 'Failed to send message');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function displayMessages(messages) {
    chatMessages.innerHTML = '';
    messages.forEach(msg => {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${msg.is_admin ? 'admin' : ''}`;
        messageDiv.innerHTML = `
            <strong>${msg.is_admin ? 'Admin' : msg.user_name || 'Guest'}:</strong>
            <p>${msg.message}</p>
            <small>${new Date(msg.created_at).toLocaleString()}</small>
        `;
        chatMessages.appendChild(messageDiv);
    });
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function startPolling() {
    stopPolling();
    pollInterval = setInterval(() => {
        if (chatId) {
            fetch(`${chatApiUrl}?action=get_messages&chat_id=${chatId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMessages(data.messages || []);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }, 3000); // Poll every 3 seconds
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}
