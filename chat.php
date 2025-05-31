<?php
$path_prefix = ''; // This file is at the root
$page_title = "Messagerie | OMNES IMMOBILIER";
require_once 'php/config/db.php'; // Provides $pdo
require_once 'php/includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error_message_main'] = "Vous devez être connecté pour accéder à la messagerie.";
    header("Location: votre-compte.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_type = $_SESSION['user_type'];
$error_message_chat = '';
$success_message_chat = '';

if(isset($_SESSION['success_message_chat'])) {
    $success_message_chat = $_SESSION['success_message_chat'];
    unset($_SESSION['success_message_chat']);
}
if(isset($_SESSION['error_message_chat'])) {
    $error_message_chat = $_SESSION['error_message_chat'];
    unset($_SESSION['error_message_chat']);
}

$contacts = [];

if (!isset($pdo)) {
    $error_message_chat .= " Erreur critique: La connexion à la base de données n'a pas pu être établie.";
} else {
    try {
        // SQL to get users with whom the current user has exchanged messages, ordered by unread count and then by most recent message
        $sql_contacts = "
        SELECT 
            U.id as contact_id,
            U.nom as contact_nom,
            U.prenom as contact_prenom,
            U.type_compte as contact_type,
            LM.contenu_message as last_message_content,
            LM.date_heure_envoi as last_message_time,
            LM.id_expediteur as last_message_sender_id,
            (SELECT COUNT(*) FROM Messages M_unread WHERE M_unread.id_destinataire = :current_user_id_unread AND M_unread.id_expediteur = U.id AND M_unread.lu = FALSE) as unread_count
        FROM (\n            -- Subquery to find the latest message for each conversation partner\n            SELECT 
                CASE
                    WHEN M_sub.id_expediteur = :current_user_id_sub1 THEN M_sub.id_destinataire
                    ELSE M_sub.id_expediteur
                END as partner_id,
                MAX(M_sub.date_heure_envoi) as max_date_heure_envoi
            FROM Messages M_sub
            WHERE M_sub.id_expediteur = :current_user_id_sub2 OR M_sub.id_destinataire = :current_user_id_sub3
            GROUP BY partner_id
        ) AS C
        JOIN Utilisateurs U ON C.partner_id = U.id
        LEFT JOIN Messages LM ON ((LM.id_expediteur = C.partner_id AND LM.id_destinataire = :current_user_id_lm1) OR (LM.id_expediteur = :current_user_id_lm2 AND LM.id_destinataire = C.partner_id))\n                            AND LM.date_heure_envoi = C.max_date_heure_envoi
        WHERE C.partner_id != :current_user_id_main -- Exclude self from contacts
        ORDER BY unread_count DESC, C.max_date_heure_envoi DESC";

        $stmt_contacts = $pdo->prepare($sql_contacts);
        $params = [
            ':current_user_id_unread' => $current_user_id,
            ':current_user_id_sub1' => $current_user_id,
            ':current_user_id_sub2' => $current_user_id,
            ':current_user_id_sub3' => $current_user_id,
            ':current_user_id_lm1' => $current_user_id,
            ':current_user_id_lm2' => $current_user_id,
            ':current_user_id_main' => $current_user_id
        ];
        $stmt_contacts->execute($params);
        $contacts = $stmt_contacts->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message_chat .= " Erreur de base de données lors de la récupération des contacts: " . htmlspecialchars($e->getMessage());
        error_log("PDO Error in chat.php (contacts): " . $e->getMessage());
    }
}

?>

<div class="container mt-5 mb-5">
    <div class="section-title">
        <h2>Messagerie</h2>
        <p>Communiquez avec les agents ou les clients.</p>
    </div>

    <?php if (!empty($success_message_chat)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message_chat); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message_chat)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message_chat); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Contact List Column -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Contacts</h5>
                </div>
                <div class="list-group list-group-flush chat-contact-list-scroll" id="contact-list">
                    <?php if (!empty($contacts)): ?>
                        <?php foreach ($contacts as $contact): ?>
                            <?php 
                                $contact_display_name = htmlspecialchars(ucfirst($contact['contact_prenom']) . " " . ucfirst($contact['contact_nom']));
                                if ($contact['contact_type'] === 'agent') {
                                    $contact_display_name .= " (Agent)";
                                } elseif ($contact['contact_type'] === 'admin') {
                                    $contact_display_name .= " (Admin)";
                                }
                                $last_message_preview = htmlspecialchars($contact['last_message_content'] ?? '');
                                if (strlen($last_message_preview) > 30) {
                                    $last_message_preview = substr($last_message_preview, 0, 27) . "...";
                                }
                                if ($contact['last_message_sender_id'] == $current_user_id) {
                                    $last_message_preview = "Vous: " . $last_message_preview;
                                }
                                // Simple avatar placeholder (e.g., first letter of prenom)
                                $avatar_letter = !empty($contact['contact_prenom']) ? strtoupper(substr($contact['contact_prenom'], 0, 1)) : '?';
                            ?>
                            <a href="#" class="list-group-item list-group-item-action contact-item d-flex align-items-center" data-contact-id="<?php echo $contact['contact_id']; ?>" data-contact-name="<?php echo htmlspecialchars(ucfirst($contact['contact_prenom']) . ' ' . ucfirst($contact['contact_nom'])); /* Store raw name for JS */ ?>">
                                <div class="contact-avatar me-3">
                                    <?php echo $avatar_letter; ?>
                                </div>
                                <div class="contact-info flex-grow-1">
                                    <h6 class="my-0 contact-name"><?php echo $contact_display_name; ?></h6>
                                    <small class="text-muted last-message-preview"><?php echo $last_message_preview; ?></small>
                                </div>
                                <?php if ($contact['unread_count'] > 0): ?>
                                    <span class="badge bg-primary rounded-pill unread-badge ms-2"><?php echo $contact['unread_count']; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="p-3 text-muted">Aucun contact récent. Commencez une nouvelle conversation en contactant un agent depuis sa page ou une page de propriété.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chat Area Column -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" id="chat-with-name">Sélectionnez un contact</h5>
                    <!-- Could add contact details link or actions here -->
                </div>
                <div class="card-body chat-messages chat-messages-area-dimensions" id="chat-messages-area">
                    <!-- Messages will be loaded here -->
                    <!-- Example Message Structure (will be generated by JS) -->
                    <!--
                    <div class="message-bubble-wrapper sent">
                        <div class="message-bubble">
                            <p class="message-text">Ceci est un exemple de message envoyé.</p>
                            <small class="message-time">10:00</small>
                        </div>
                    </div>
                    <div class="message-bubble-wrapper received">
                        <div class="message-bubble">
                            <p class="message-text">Ceci est un exemple de message reçu.</p>
                            <small class="message-time">10:01</small>
                        </div>
                    </div>
                    -->
                     <p class="text-center text-muted" id="no-chat-selected">Veuillez sélectionner une conversation pour afficher les messages.</p>
                </div>
                <div class="card-footer chat-input-area">
                    <form id="send-message-form" action="php/actions/send_message_action.php" method="POST">
                        <div class="input-group">
                            <input type="hidden" name="id_destinataire" id="chat-recipient-id" value="">
                            <textarea class="form-control message-input-field" name="contenu_message" id="message-input" placeholder="Écrivez votre message..." rows="1" required></textarea>
                            <button class="btn btn-primary send-message-button" type="submit" id="send-message-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for chat functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const contactList = document.getElementById('contact-list');
    const chatMessagesArea = document.getElementById('chat-messages-area');
    const chatWithName = document.getElementById('chat-with-name');
    const chatRecipientIdInput = document.getElementById('chat-recipient-id');
    const sendMessageForm = document.getElementById('send-message-form');
    const messageInput = document.getElementById('message-input');
    const noChatSelectedP = document.getElementById('no-chat-selected');

    let currentOpenChatId = null;

    // Function to load contacts
    function loadContacts() {
        // Placeholder: In a real app, this would be an AJAX call to a PHP script
        // For now, we'll rely on the PHP generated list or update it if needed.
        // The PHP part will need to list users with whom the current user has had conversations
        // or, for agents, list their clients, and for clients, list agents they've interacted with.
    }

    // Function to load messages for a contact
    async function loadMessages(contactId, contactName) {
        if (noChatSelectedP) noChatSelectedP.style.display = 'none';
        chatMessagesArea.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>'; // Loading spinner
        chatWithName.textContent = `Conversation avec ${contactName}`;
        chatRecipientIdInput.value = contactId;
        currentOpenChatId = contactId;

        try {
            const response = await fetch(`<?php echo $path_prefix; ?>php/actions/get_messages_action.php?contact_id=${contactId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const messages = await response.json();
            displayMessages(messages);
        } catch (error) {
            console.error('Error loading messages:', error);
            chatMessagesArea.innerHTML = `<p class="text-danger p-3">Impossible de charger les messages. ${error.message}</p>`;
        }
    }

    // Function to display messages
    function displayMessages(messages) {
        chatMessagesArea.innerHTML = ''; // Clear previous messages or spinner
        if (messages.length === 0) {
            chatMessagesArea.innerHTML = '<p class="text-center text-muted p-3">Aucun message dans cette conversation. Commencez à discuter !</p>';
            return;
        }
        messages.forEach(msg => {
            const messageWrapper = document.createElement('div');
            messageWrapper.classList.add('message-bubble-wrapper');

            const messageBubble = document.createElement('div');
            messageBubble.classList.add('message-bubble');

            const textP = document.createElement('p');
            textP.classList.add('message-text');
            textP.textContent = msg.contenu_message;

            const timeSmall = document.createElement('small');
            timeSmall.classList.add('message-time');
            timeSmall.textContent = new Date(msg.date_heure_envoi).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

            if (msg.id_expediteur == <?php echo $current_user_id; ?>) { // Message sent by current user
                messageWrapper.classList.add('sent');
            } else { // Message received
                messageWrapper.classList.add('received');
            }
            messageBubble.appendChild(textP);
            messageBubble.appendChild(timeSmall);
            messageWrapper.appendChild(messageBubble);
            chatMessagesArea.insertBefore(messageWrapper, chatMessagesArea.firstChild);
        });
        chatMessagesArea.scrollTop = 0;
    }

    // Event listener for contact list clicks
    contactList.addEventListener('click', function(e) {
        e.preventDefault();
        let target = e.target;
        while (target && target !== contactList && !target.dataset.contactId) {
            target = target.parentElement;
        }
        if (target && target.dataset.contactId) {
            // Remove active class from previously selected contact
            const currentActive = contactList.querySelector('.active');
            if (currentActive) {
                currentActive.classList.remove('active');
            }
            // Add active class to new contact
            target.classList.add('active');

            const contactId = target.dataset.contactId;
            const contactName = target.dataset.contactName;
            loadMessages(contactId, contactName);
        }
    });

    // Event listener for sending messages
    sendMessageForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!currentOpenChatId) {
            alert('Veuillez sélectionner un contact à qui envoyer un message.');
            return;
        }
        if (!messageInput.value.trim()) return; // Don't send empty messages

        const formData = new FormData(sendMessageForm);
        // We already have id_destinataire from chatRecipientIdInput.value
        // formData.append('id_expediteur', <?php echo $current_user_id; ?>); // PHP handles this

        try {
            const response = await fetch(sendMessageForm.action, {
                method: 'POST',
                body: formData
            });
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({message: 'Erreur inconnue.'}));
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            const result = await response.json();
            if (result.success) {
                messageInput.value = ''; // Clear input
                // Add message to UI immediately for better UX
                const newMessageData = {
                    id_expediteur: <?php echo $current_user_id; ?>,
                    contenu_message: formData.get('contenu_message'),
                    date_heure_envoi: new Date().toISOString() // Approximate time
                };
                
                const noMessagesP = chatMessagesArea.querySelector('p.text-center.text-muted');
                if (noMessagesP && (chatMessagesArea.childElementCount === 1 || (chatMessagesArea.childElementCount === 2 && chatMessagesArea.firstChild.id === 'no-chat-selected'))) {
                    noMessagesP.remove();
                }

                const messageWrapper = document.createElement('div');
                messageWrapper.classList.add('message-bubble-wrapper', 'sent');

                const messageBubble = document.createElement('div');
                messageBubble.classList.add('message-bubble');

                const textP = document.createElement('p');
                textP.classList.add('message-text');
                textP.textContent = newMessageData.contenu_message;

                const timeSmall = document.createElement('small');
                timeSmall.classList.add('message-time');
                timeSmall.textContent = new Date(newMessageData.date_heure_envoi).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                
                messageBubble.appendChild(textP);
                messageBubble.appendChild(timeSmall);
                messageWrapper.appendChild(messageBubble);
                chatMessagesArea.insertBefore(messageWrapper, chatMessagesArea.firstChild);
                chatMessagesArea.scrollTop = 0; // Scroll to latest message
                
                // Auto-resize textarea
                messageInput.style.height = 'auto'; 
            } else {
                alert(`Erreur: ${result.message}`);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert(`Erreur d'envoi du message: ${error.message}`);
        }
    });

    // Initial load (if any contact is pre-selected or to load general state)
    // loadContacts();
    // Check if a contact_id is passed in URL to pre-open a chat
    const urlParams = new URLSearchParams(window.location.search);
    const preselectContactId = urlParams.get('contact_id');
    const preselectContactName = urlParams.get('contact_name'); 

    if (preselectContactId) { // Only preselectContactId is strictly needed from URL to find in list
        const contactLink = contactList.querySelector(`[data-contact-id="${preselectContactId}"]`);
        if (contactLink) {
            contactLink.click(); // Simulate click to make it active and load messages
        } else {
            // If contact is not in the pre-loaded list, we might need a way to initiate a new chat
            // For now, if a name is also provided, we can set the chat header, but messages won't load until contact exists in list
            if (preselectContactName) {
                 chatWithName.textContent = `Conversation avec ${decodeURIComponent(preselectContactName)}`;
                 chatRecipientIdInput.value = preselectContactId;
                 // We could try to load messages, but get_messages might return empty if no history
                 loadMessages(preselectContactId, decodeURIComponent(preselectContactName));
            } else {
                 chatWithName.textContent = `Nouvelle conversation avec ID ${preselectContactId}`;
                 chatRecipientIdInput.value = preselectContactId;
                 if (noChatSelectedP) noChatSelectedP.style.display = 'none';
                 chatMessagesArea.innerHTML = '<p class="text-center text-muted p-3">Envoyez un message pour démarrer cette nouvelle conversation.</p>';
            }
        }
    } else {
        if (noChatSelectedP) noChatSelectedP.style.display = 'block';
        chatMessagesArea.innerHTML = ''; // Clear any example messages
        const firstContact = contactList.querySelector('a.list-group-item');
        if (firstContact) {
           // firstContact.click(); // Uncomment to automatically open the first contact's chat
        } else {
            // contactList.innerHTML = '<p class="p-3 text-muted">Aucun contact récent. Commencez une nouvelle conversation via la page profil d\'un agent ou d\'un client.</p>';
             if (noChatSelectedP) noChatSelectedP.style.display = 'block';
             chatMessagesArea.innerHTML = '<p class="text-center text-muted p-3">Veuillez sélectionner une conversation pour afficher les messages.</p>';
        }
    }

    // Auto-refresh messages every 30 seconds for the current chat if one is open
    setInterval(function() {
        if (currentOpenChatId) {
            // Only refresh if the user is not actively typing in the message input
            // and if the chat window is visible (e.g. browser tab is active - harder to check reliably without complex JS)
            if (document.activeElement !== messageInput || messageInput.value.trim() === '') {
                // console.log('Refreshing messages for contact: ' + currentOpenChatId);
                const currentContactName = chatWithName.textContent.replace('Conversation avec ', '').replace('Nouvelle conversation avec ID ', ''); // Get name regardless of state
                loadMessages(currentOpenChatId, currentContactName);
                // After refreshing messages, we might also want to refresh the contact list to update unread counts if any other chats received messages
                // This would be a separate call, e.g., refreshContactList(); (function to be created if desired)
            }
        }
    }, 15000); // Refresh every 15 seconds

    // Auto-resize textarea input
    messageInput.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

});
</script>

<?php require_once 'php/includes/footer.php'; ?> 