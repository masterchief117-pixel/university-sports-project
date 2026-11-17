const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

async function testConcurrentSubmissionsSendOnce() {
    const source = fs.readFileSync(
        path.join(__dirname, "..", "assets", "js", "chat.js"),
        "utf8"
    );
    const listeners = {};
    let domReady;
    let resolveSend;
    let sendCount = 0;
    let messageLoadCount = 0;
    const fetchUrls = [];
    let sendPayload;
    const sendButton = { disabled: false };
    const typeInput = { value: "player_player" };
    const contactButton = {
        dataset: {
            targetUserId: "8",
            conversationType: "player_player"
        },
        attributes: {},
        addEventListener(name, callback) {
            listeners[`contact:${name}`] = callback;
        },
        setAttribute(name, value) {
            this.attributes[name] = value;
        }
    };
    const input = {
        value: "hello",
        addEventListener(name, callback) {
            listeners[`input:${name}`] = callback;
        }
    };
    const chatBox = {
        replaceChildren() {},
        appendChild() {},
        scrollHeight: 0,
        scrollTop: 0
    };
    const form = {
        dataset: {
            basePath: "",
            conversationType: "team",
            csrfToken: "test-token",
            currentUserId: "4",
            targetUserId: "",
            requiresContact: "true"
        },
        querySelector(selector) {
            return selector === 'button[type="submit"]' ? sendButton : typeInput;
        },
        addEventListener(name, callback) {
            listeners[`form:${name}`] = callback;
        }
    };
    const document = {
        body: { dataset: { basePath: "" } },
        addEventListener(name, callback) {
            if (name === "DOMContentLoaded") {
                domReady = callback;
            }
        },
        querySelector(selector) {
            return selector === "[data-chat-form]" ? form : null;
        },
        querySelectorAll(selector) {
            return selector === "[data-chat-contact]" ? [contactButton] : [];
        },
        getElementById(id) {
            return id === "chat-box" ? chatBox : id === "chat-input" ? input : null;
        }
    };
    const fetch = (url, options) => {
        fetchUrls.push(String(url));
        if (String(url).includes("send_message.php")) {
            sendCount++;
            sendPayload = JSON.parse(options.body);
            return new Promise((resolve) => {
                resolveSend = resolve;
            });
        }
        messageLoadCount++;
        return Promise.resolve({
            ok: true,
            json: async () => ({ messages: [] })
        });
    };

    vm.runInNewContext(source, {
        AbortController,
        document,
        Error,
        fetch,
        JSON,
        Number,
        setInterval() {},
        URLSearchParams
    });
    domReady();
    await new Promise((resolve) => setImmediate(resolve));

    listeners["contact:click"]();
    await new Promise((resolve) => setImmediate(resolve));
    assert.ok(
        fetchUrls.some((url) => url.includes("conversation_type=player_player")
            && url.includes("target_user_id=8")),
        "selecting a sidebar contact should load that direct conversation"
    );
    assert.equal(contactButton.attributes["aria-pressed"], "true");
    assert.equal(typeInput.value, "player_player");

    const submit = listeners["form:submit"];
    const event = { preventDefault() {} };
    submit(event);
    submit(event);
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(sendCount, 1, "overlapping submits should send one message");
    assert.equal(sendButton.disabled, true, "the send button should be disabled while sending");
    assert.deepEqual(sendPayload, {
        conversation_type: "player_player",
        body: "hello",
        target_user_id: 8
    });

    resolveSend({
        ok: true,
        json: async () => ({ success: true })
    });
    await new Promise((resolve) => setImmediate(resolve));
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(sendButton.disabled, false, "the send button should be restored after sending");
    assert.equal(input.value, "", "a successful send should clear the message input");
    assert.ok(messageLoadCount >= 2, "messages should load before and after sending");
}

testConcurrentSubmissionsSendOnce()
    .then(() => console.log("Chat duplicate-send guard passed."))
    .catch((error) => {
        console.error(error);
        process.exitCode = 1;
    });
