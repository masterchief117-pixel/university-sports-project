document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("[data-chat-form]");
    const chatBox = document.getElementById("chat-box");

    if (!form || !chatBox) {
        return;
    }

    const input = document.getElementById("chat-input");
    const status = document.querySelector("[data-chat-status]");
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

    async function loadMessages() {
        const targetUserId = getTargetUserId();
        if (requiresContact && !targetUserId) {
            if (activeLoadController) {
                activeLoadController.abort();
                activeLoadController = null;
                activeLoadTarget = null;
            }
            chatBox.replaceChildren();
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

        try {
            const params = new URLSearchParams({ conversation_type: conversationType });
            if (targetUserId) {
                params.set("target_user_id", String(targetUserId));
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

            chatBox.replaceChildren();
            result.messages.forEach((message) => {
                const bubble = document.createElement("article");
                bubble.className = Number(message.sender_id) === currentUserId
                    ? "message-bubble message-own"
                    : "message-bubble";

                const sender = document.createElement("strong");
                sender.textContent = message.sender_name;
                const body = document.createElement("p");
                body.textContent = message.body;

                bubble.append(sender, body);
                chatBox.appendChild(bubble);
            });

            chatBox.scrollTop = chatBox.scrollHeight;
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
            await loadMessages();
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
            loadMessages();
        });
    });

    updateSendButton();
    loadMessages();
    setInterval(loadMessages, 3000);
});
