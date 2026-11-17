document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("[data-chat-form]");
    const chatBox = document.getElementById("chat-box");

    if (!form || !chatBox) {
        return;
    }

    const input = document.getElementById("chat-input");
    const status = document.querySelector("[data-chat-status]");
    const loadOlderButton = document.querySelector("[data-chat-load-older]");
    const contactButtons = Array.from(document.querySelectorAll("[data-chat-contact]"));
    const sendButton = form.querySelector('button[type="submit"]');
    const basePath = document.body.dataset.basePath || "";
    let conversationType = form.dataset.conversationType;
    let activeTargetUserId = form.dataset.targetUserId
        ? Number(form.dataset.targetUserId)
        : null;
    const csrfToken = form.dataset.csrfToken;
    const currentUserId = Number(form.dataset.currentUserId);
    const requiresContact = form.dataset.requiresContact === "true";
    let activeLoadController = null;
    let activeLoadTarget = null;
    let sendingMessage = false;
    let lastMessageId = 0;
    let firstMessageId = 0;
    let hasOlderMessages = false;
    let loadingOlderMessages = false;
    const noAvailableContacts = requiresContact && contactButtons.length === 0;

    function showStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function getTargetUserId() {
        return activeTargetUserId;
    }

    function updateSendButton() {
        if (sendButton) {
            sendButton.disabled = sendingMessage
                || noAvailableContacts
                || Boolean(requiresContact && !activeTargetUserId);
        }
    }

    function updateLoadOlderButton() {
        if (loadOlderButton) {
            loadOlderButton.hidden = !hasOlderMessages;
            loadOlderButton.disabled = loadingOlderMessages;
        }
    }

    function createMessageBubble(message) {
        const bubble = document.createElement("article");
        bubble.className = Number(message.sender_id) === currentUserId
            ? "message-bubble message-own"
            : "message-bubble";

        const sender = document.createElement("strong");
        sender.textContent = message.sender_name;
        const body = document.createElement("p");
        body.textContent = message.body;

        bubble.dataset.messageId = String(message.id);
        bubble.append(sender, body);
        return bubble;
    }

    async function loadMessages(mode = "poll") {
        const targetUserId = getTargetUserId();
        if (requiresContact && !targetUserId) {
            if (activeLoadController) {
                activeLoadController.abort();
                activeLoadController = null;
                activeLoadTarget = null;
            }
            chatBox.replaceChildren();
            firstMessageId = 0;
            lastMessageId = 0;
            hasOlderMessages = false;
            updateLoadOlderButton();
            showStatus("Select a conversation partner to view messages.");
            return;
        }

        const requestTarget = `${conversationType}:${targetUserId || ""}`;
        if (activeLoadController) {
            if (activeLoadTarget === requestTarget) {
                return;
            }
            activeLoadController.abort();
        }

        const controller = new AbortController();
        activeLoadController = controller;
        activeLoadTarget = requestTarget;
        const previousScrollHeight = chatBox.scrollHeight;
        const previousScrollTop = chatBox.scrollTop;
        const followLatest = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 80;
        loadingOlderMessages = mode === "older";
        updateLoadOlderButton();

        try {
            const params = new URLSearchParams({ conversation_type: conversationType });
            if (targetUserId) {
                params.set("target_user_id", String(targetUserId));
            }
            if (mode === "older" && firstMessageId > 0) {
                params.set("before_id", String(firstMessageId));
            } else if (mode !== "initial" && lastMessageId > 0) {
                params.set("after_id", String(lastMessageId));
            }

            const response = await fetch(
                `${basePath}/api/fetch_messages.php?${params.toString()}`,
                { credentials: "same-origin", signal: controller.signal }
            );
            if (controller.signal.aborted) {
                return;
            }

            const result = await response.json();
            if (controller.signal.aborted) {
                return;
            }

            if (!response.ok) {
                throw new Error(result.error || "Unable to load messages.");
            }

            if (mode === "initial") {
                chatBox.replaceChildren();
                firstMessageId = 0;
                lastMessageId = 0;
                hasOlderMessages = Boolean(result.has_older);
                result.messages.forEach((message) => {
                    chatBox.appendChild(createMessageBubble(message));
                    const messageId = Number(message.id) || 0;
                    if (firstMessageId === 0) {
                        firstMessageId = messageId;
                    }
                    lastMessageId = Math.max(lastMessageId, messageId);
                });
                chatBox.scrollTop = chatBox.scrollHeight;
            } else if (mode === "older") {
                const bubbles = result.messages.map(createMessageBubble);
                if (bubbles.length > 0) {
                    chatBox.prepend(...bubbles);
                    firstMessageId = Number(result.messages[0].id) || firstMessageId;
                    chatBox.scrollTop = previousScrollTop + chatBox.scrollHeight - previousScrollHeight;
                }
                hasOlderMessages = Boolean(result.has_older);
            } else {
                result.messages.forEach((message) => {
                    chatBox.appendChild(createMessageBubble(message));
                    lastMessageId = Math.max(lastMessageId, Number(message.id) || 0);
                });
                if (result.messages.length > 0 && followLatest) {
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            }
            updateLoadOlderButton();
            showStatus("");
        } catch (error) {
            if (!controller.signal.aborted && error.name !== "AbortError") {
                showStatus(error.message);
            }
        } finally {
            if (activeLoadController === controller) {
                activeLoadController = null;
                activeLoadTarget = null;
            }
            loadingOlderMessages = false;
            updateLoadOlderButton();
        }
    }

    async function sendMessage() {
        if (sendingMessage) {
            return;
        }

        const body = input.value.trim();
        if (!body) {
            return;
        }

        const targetUserId = getTargetUserId();
        if (requiresContact && !targetUserId) {
            showStatus("Select a conversation partner before sending a message.");
            return;
        }

        sendingMessage = true;
        updateSendButton();
        const payload = {
            conversation_type: conversationType,
            body
        };
        if (targetUserId) {
            payload.target_user_id = targetUserId;
        }

        try {
            const response = await fetch(`${basePath}/api/send_message.php`, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || "Unable to send message.");
            }

            input.value = "";
            showStatus("");
            await loadMessages("initial");
        } catch (error) {
            showStatus(error.message);
        } finally {
            sendingMessage = false;
            updateSendButton();
        }
    }

    form.addEventListener("submit", (event) => {
        event.preventDefault();
        sendMessage();
    });

    input.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            sendMessage();
        }
    });

    contactButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const targetUserId = Number(button.dataset.targetUserId);
            const targetType = button.dataset.conversationType;
            if (!targetUserId || !targetType
                || (activeTargetUserId === targetUserId && conversationType === targetType)) {
                return;
            }

            activeTargetUserId = targetUserId;
            conversationType = targetType;
            firstMessageId = 0;
            lastMessageId = 0;
            hasOlderMessages = false;
            updateLoadOlderButton();
            form.dataset.targetUserId = String(targetUserId);
            form.dataset.conversationType = targetType;
            const typeInput = form.querySelector('input[name="conversation_type"]');
            if (typeInput) {
                typeInput.value = targetType;
            }
            contactButtons.forEach((contactButton) => {
                contactButton.setAttribute(
                    "aria-pressed",
                    String(contactButton === button)
                );
            });
            updateSendButton();
            chatBox.replaceChildren();
            showStatus("Loading messages...");
            loadMessages("initial");
        });
    });

    if (loadOlderButton) {
        loadOlderButton.addEventListener("click", () => {
            if (!loadingOlderMessages && hasOlderMessages) {
                loadMessages("older");
            }
        });
    }

    updateSendButton();
    loadMessages("initial");
    setInterval(loadMessages, 3000);
});
