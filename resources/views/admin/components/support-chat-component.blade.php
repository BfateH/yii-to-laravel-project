<div class="support-chat-wrapper dark" id="support-chat-wrapper-{{ $ticketId }}" data-ticket-id="{{ $ticketId }}">
    <div class="chat-header">
        <h3 class="chat-title">Чат</h3>
    </div>

    <div class="chat-container">
        <div class="chat-messages dark" id="chat-messages-{{ $ticketId }}">
            <div class="loading-placeholder">
                <span class="loading-text">Загрузка сообщений...</span>
            </div>
        </div>

        <form {!! $ticket->isClosed() ? 'style="display: none;"' : '' !!} class="chat-form dark" id="chat-form-{{ $ticketId }}" data-send-url="{{ route('moonshine.tickets.messages.store', $ticketId) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="message-input-{{ $ticketId }}" class="form-label">Сообщение *</label>
                <textarea
                    id="message-input-{{ $ticketId }}"
                    name="message"
                    class="form-control dark"
                    rows="3"
                    placeholder="Введите ваше сообщение..."
                    required
                ></textarea>
            </div>

            <input type="file" id="attachments-{{ $ticketId }}" name="attachments[]" class="form-control dark" multiple accept="image/*,.pdf,.doc,.docx,.txt" style="display: none;">

            <div class="selected-files-container" id="selected-files-{{ $ticketId }}" style="display: none;">
                <div class="selected-files-header">
                    <div class="files-title">
                        <i class="icon-attachment-header"></i>
                        <span>Выбранные файлы</span>
                        <span class="files-count" id="files-count-{{ $ticketId }}">0</span>
                        <span class="file-limit-text">/ 5</span>
                    </div>
                    <button type="button" class="btn-clear-all" id="clear-files-{{ $ticketId }}">
                        <i class="icon-clear"></i> Очистить все
                    </button>
                </div>
                <div class="files-grid" id="files-grid-{{ $ticketId }}"></div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-attachment" id="attach-btn-{{ $ticketId }}">
                    <i class="icon-attachment"></i> Прикрепить файлы
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="icon-send"></i> Отправить
                </button>
            </div>
        </form>
    </div>

    <div style="display: none;"
         data-is-admin="{{ $isAdmin }}"
         data-current-user-id="{{ $currentUser->id }}"
         data-load-messages-url="{{ route('moonshine.tickets.messages.index', $ticketId) }}"
         data-send-message-url="{{ route('moonshine.tickets.messages.store', $ticketId) }}"
    ></div>
</div>

<script>
    'use strict';
    window.SupportChatModule = window.SupportChatModule || {};

    window.SupportChatModule.initializeChatComponent = function(ticketId) {
        const chatWrapper = document.getElementById('support-chat-wrapper-' + ticketId);
        if (!chatWrapper) {
            console.error('SupportChat: Chat wrapper not found for ticket ' + ticketId);
            return;
        }

        const isAdmin = +chatWrapper.querySelector('[data-is-admin]').dataset.isAdmin;
        const currentUserId = chatWrapper.querySelector('[data-current-user-id]').dataset.currentUserId;
        const loadMessagesUrl = chatWrapper.querySelector('[data-load-messages-url]').dataset.loadMessagesUrl;
        const sendMessageUrl = chatWrapper.querySelector('[data-send-message-url]').dataset.sendMessageUrl;

        const chatMessages = document.getElementById('chat-messages-' + ticketId);
        const chatForm = document.getElementById('chat-form-' + ticketId);
        const messageInput = document.getElementById('message-input-' + ticketId);
        const attachmentsInput = document.getElementById('attachments-' + ticketId);
        const attachBtn = document.getElementById('attach-btn-' + ticketId);
        const selectedFilesDiv = document.getElementById('selected-files-' + ticketId);
        const filesGridDiv = document.getElementById('files-grid-' + ticketId);
        const filesCountSpan = document.getElementById('files-count-' + ticketId);
        const clearFilesBtn = document.getElementById('clear-files-' + ticketId);
        let isNewMessagesAdded = false;

        if (!chatMessages || !chatForm || !messageInput) {
            console.error('SupportChat: Required chat elements not found for ticket ' + ticketId);
            return;
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function getFileIconClass(fileName) {
            const ext = fileName.toLowerCase().split('.').pop();
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                return 'image';
            } else if (ext === 'pdf') {
                return 'pdf';
            } else if (['doc', 'docx'].includes(ext)) {
                return 'word';
            } else if (['txt'].includes(ext)) {
                return 'text';
            }
            return 'default';
        }

        function updateSelectedFilesDisplay() {
            const files = Array.from(attachmentsInput.files);

            filesCountSpan.textContent = files.length;

            if (files.length === 0) {
                selectedFilesDiv.style.display = 'none';
                return;
            }

            selectedFilesDiv.style.display = 'block';
            filesGridDiv.innerHTML = '';

            files.forEach((file, index) => {
                const fileCard = document.createElement('div');
                fileCard.className = 'file-card';

                const fileIconClass = getFileIconClass(file.name);

                fileCard.innerHTML = `
                    <div class="file-icon ${fileIconClass}"></div>
                    <div class="file-name-container">
                        <div class="file-name" title="${file.name}">${file.name}</div>
                    </div>
                    <div class="file-info">
                        <span class="file-size">${formatFileSize(file.size)}</span>
                    </div>
                    <button type="button" class="remove-file" data-index="${index}">×</button>
                `;

                filesGridDiv.appendChild(fileCard);
            });

            filesGridDiv.querySelectorAll('.remove-file').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    removeFileAtIndex(index);
                });
            });
        }

        function removeFileAtIndex(index) {
            const dt = new DataTransfer();
            const files = Array.from(attachmentsInput.files);

            files.forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });

            attachmentsInput.files = dt.files;
            updateSelectedFilesDisplay();
        }

        function addMessageToChat(messageData) {
            if (messageData.id) {
                const existingMessage = chatMessages.querySelector(`.chat-message[data-message-id="${messageData.id}"]`);
                if (existingMessage) return;
            }

            const messageElement = document.createElement('div');
            messageElement.classList.add('chat-message');
            if (messageData.id) messageElement.dataset.messageId = messageData.id;
            if (parseInt(messageData.user_id) === parseInt(currentUserId)) messageElement.classList.add('own');

            let formattedDate = 'N/A';
            try {
                const dateObj = new Date(messageData.created_at);
                formattedDate = dateObj.toLocaleString('ru-RU');
            } catch (e) {
                console.warn('SupportChat: Could not format date', messageData.created_at, e);
            }

            let messageHtml = `
                <div class="message-header">
                    <span class="message-user">${(messageData.user?.name || 'Unknown User').replace(/</g, '<')}</span>
                    <span class="message-time">${formattedDate}</span>
                </div>
                <div class="message-content">${messageData.message.replace(/</g, '<').replace(/>/g, '>').replace(/\n/g, '<br>')}</div>
            `;

            if (Array.isArray(messageData.attachments) && messageData.attachments.length > 0) {
                const attachmentsDiv = document.createElement('div');
                attachmentsDiv.classList.add('message-attachments');

                const title = document.createElement('h4');
                title.textContent = 'Вложения:';
                attachmentsDiv.appendChild(title);

                const attachmentsList = document.createElement('ul');
                attachmentsList.classList.add('attachment-list');
                attachmentsList.style.margin = '0';
                attachmentsList.style.padding = '0';

                messageData.attachments.forEach(attachment => {
                    const li = document.createElement('li');
                    li.classList.add('attachment-item');
                    li.style.marginBottom = '0.25rem';
                    if (attachment.url && attachment.original_name) {
                        const link = document.createElement('a');
                        link.href = attachment.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.classList.add('attachment-link');
                        link.innerHTML = '<span class="attachment-icon">📎</span>' + attachment.original_name;
                        link.setAttribute('download', "1");
                        li.appendChild(link);
                        attachmentsList.appendChild(li);
                    }
                });

                attachmentsDiv.appendChild(attachmentsList);
                messageHtml += attachmentsDiv.outerHTML;
            }

            if(!isNewMessagesAdded) {
                const newMessages = document.createElement('div');
                newMessages.classList.add('new-messages');
                newMessages.innerHTML = `Новые сообщения`;

                if(+messageData.id > +messageData.last_admin_message_read && isAdmin) {
                    chatMessages.appendChild(newMessages);
                    isNewMessagesAdded = true;
                }

                if(+messageData.id > +messageData.last_user_message_read && !isAdmin) {
                    chatMessages.appendChild(newMessages);
                    isNewMessagesAdded = true;
                }
            }

            messageElement.innerHTML = messageHtml;
            chatMessages.appendChild(messageElement);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function loadMessages() {
            if (!loadMessagesUrl) {
                chatMessages.innerHTML = '<p class="loading-text">Ошибка: Невозможно загрузить сообщения.</p>';
                return;
            }

            if (chatMessages.innerHTML.includes('Загрузка') || chatMessages.children.length === 1) {
                chatMessages.innerHTML = '<div class="loading-placeholder"><span class="loading-text">Загрузка сообщений...</span></div>';
            }

            fetch(loadMessagesUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    chatMessages.innerHTML = '';
                    if (Array.isArray(data.data)) {
                        data.data.forEach(message => addMessageToChat(message));
                        isNewMessagesAdded = true;
                        if (data.data.length === 0) {
                            chatMessages.innerHTML = '<p class="loading-text">Нет сообщений.</p>';
                        }
                    } else {
                        chatMessages.innerHTML = '<p class="loading-text">Ошибка формата данных.</p>';
                    }
                })
                .catch(error => {
                    console.error('SupportChat: Error loading messages for ticket ' + ticketId + ':', error);
                    chatMessages.innerHTML = '<p class="loading-text">Ошибка загрузки сообщений.</p>';
                });
        }

        function initializeEcho() {
            if (typeof window.Echo === 'undefined') {
                console.warn(`SupportChat: Echo is not available for ticket ${ticketId}. Falling back to AJAX.`);
                return;
            }

            try {
                const echoChannel = window.Echo.private(`ticket.${ticketId}`);
                echoChannel.listen('.message.sent', (res) => {
                    if (res.message) {
                        addMessageToChat(res.message);
                    }
                });

                echoChannel.listen('.ticket.status.changed', (res) => {
                    if (res.ticket) {
                        if(res.ticket.status === 'closed') {
                            alert('Тикет был закрыт')
                            window.location.reload()
                        }
                    }
                });
            } catch (error) {
                console.error(`SupportChat: Ошибка при подключении к WebSocket каналу для ticket ${ticketId}:`, error);
            }
        }

        clearFilesBtn.addEventListener('click', function() {
            attachmentsInput.value = '';
            selectedFilesDiv.style.display = 'none';
        });

        attachBtn.addEventListener('click', function() {
            attachmentsInput.click();
        });

        attachmentsInput.addEventListener('change', function() {
            if (this.files.length > 5) {
                const dt = new DataTransfer();
                for (let i = 0; i < 5; i++) {
                    dt.items.add(this.files[i]);
                }
                this.files = dt.files;
                alert('Максимальное количество файлов: 5. Лишние файлы были удалены.');
            }
            updateSelectedFilesDisplay();
        });

        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const messageText = messageInput.value.trim();
            const hasAttachments = attachmentsInput.files.length > 0;

            if (!messageText && !hasAttachments) {
                messageInput.style.borderColor = '#ef4444';
                messageInput.focus();
                return;
            }

            const submitButton = chatForm.querySelector('button[type="submit"]');
            const originalText = submitButton ? submitButton.innerHTML : null;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="icon-send"></i> Отправка...';
            }

            const formData = new FormData(chatForm);

            if (!messageText && hasAttachments) {
                formData.set('message', ' ');
            }

            fetch(sendMessageUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errData => {
                            const errorMsg = errData.message || errData.error || `Ошибка ${response.status}`;
                            throw new Error(errorMsg);
                        }).catch(() => {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.data) addMessageToChat(data.data);
                })
                .catch(error => {
                    console.error('SupportChat: Ошибка при отправке:', error);
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.textContent = 'Ошибка при отправке: ' + error.message;
                    errorDiv.style.cssText = 'margin-top: 0.5rem; padding: 0.75rem; background-color: #fee; border: 1px solid #fcc; border-radius: 0.375rem; color: #c33; font-size: 0.9rem;';
                    chatForm.parentNode.insertBefore(errorDiv, chatForm.nextSibling);

                    setTimeout(() => {
                        if (errorDiv.parentNode) errorDiv.parentNode.removeChild(errorDiv);
                    }, 5000);
                })
                .finally(() => {
                    if (submitButton && originalText) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalText;
                    }
                    messageInput.value = '';
                    messageInput.style.borderColor = '';
                    attachmentsInput.value = '';
                    selectedFilesDiv.style.display = 'none';
                });
        });

        initializeEcho();
        setTimeout(() => loadMessages(), 100);
    };

    window.SupportChatModule.initChat = function(ticketId) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () =>
                window.SupportChatModule.initializeChatComponent(ticketId)
            );
        } else {
            window.SupportChatModule.initializeChatComponent(ticketId);
        }
    };

    window.SupportChatModule.initChat({{ $ticketId }});
</script>
