document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("[data-chat-form]");
    const chatBox = document.getElementById("chat-box");

    if (!form || !chatBox) {
        return;
    }

    const input = document.getElementById("chat-input");
    const status = document.querySelector("[data-chat-status]");
    const targetSelect = document.querySelector("[data-chat-target]");
    const basePath = document.body.dataset.basePath || "";
    const conversationType = form.dataset.conversationType;
    const csrfToken = form.dataset.csrfToken;
    const currentUserId = Number(form.dataset.currentUserId);
    let loading = false;

    function showStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function getTargetUserId() {
        const selectedId = targetSelect
            ? targetSelect.value
            : form.dataset.targetUserId;
        return selectedId ? Number(selectedId) : null;
    }

    async function loadMessages() {
        if (loading) {
            return;
        }

        const targetUserId = getTargetUserId();
        if (targetSelect && !targetUserId) {
            chatBox.replaceChildren();
            showStatus("Select a conversation partner to view messages.");
            return;
        }

        loading = true;
        try {
            const params = new URLSearchParams({ conversation_type: conversationType });
            if (targetUserId) {
                params.set("target_user_id", String(targetUserId));
            }

            const response = await fetch(
                `${basePath}/api/fetch_messages.php?${params.toString()}`,
                { credentials: "same-origin" }
            );
            const result = await response.json();

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
            showStatus(error.message);
        } finally {
            loading = false;
        }
    }

    async function sendMessage() {
        const body = input.value.trim();
        if (!body) {
            return;
        }

        const targetUserId = getTargetUserId();
        if (targetSelect && !targetUserId) {
            showStatus("Select a conversation partner before sending a message.");
            return;
        }

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

    if (targetSelect) {
        targetSelect.addEventListener("change", loadMessages);
    }

    loadMessages();
    setInterval(loadMessages, 3000);
});
