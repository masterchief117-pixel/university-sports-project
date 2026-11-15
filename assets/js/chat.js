document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("[data-chat-form]");
    const list = document.querySelector("[data-message-list]");

    if (!form || !list) {
        return;
    }

    const conversationId = form.dataset.conversationId;
    const csrfToken = form.dataset.csrfToken;
    const status = document.querySelector("[data-chat-status]");
    const basePath = document.body.dataset.basePath || "";
    const messagesUrl = `${basePath}/api/fetch_messages.php`;
    const sendUrl = `${basePath}/api/send_message.php`;

    const showStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const loadMessages = async () => {
        const response = await fetch(
            `${messagesUrl}?conversation_id=${encodeURIComponent(conversationId)}`,
            { credentials: "same-origin" }
        );
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || "Unable to load messages.");
        }

        list.replaceChildren();
        result.messages.forEach((message) => {
            const item = document.createElement("p");
            item.textContent = `${message.sender_name}: ${message.body}`;
            list.appendChild(item);
        });
    };

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const input = form.querySelector("[name='body']");
        const body = input.value.trim();

        if (!body) {
            return;
        }

        try {
            const response = await fetch(sendUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({ conversation_id: conversationId, body })
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
    });

    loadMessages().catch((error) => showStatus(error.message));
});
