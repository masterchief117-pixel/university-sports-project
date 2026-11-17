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
    let intervalCallback;
    const fetchUrls = [];
    let sendPayload;
    const sendButton = { disabled: false };
    const loadOlderButton = {
        hidden: true,
        disabled: false,
        addEventListener(name, callback) {
            listeners[`older:${name}`] = callback;
        }
    };
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
        children: [],
        replaceChildren() {
            this.children = [];
        },
        appendChild(child) {
            this.children.push(child);
        },
        prepend(...children) {
            this.children.unshift(...children);
        },
        clientHeight: 200,
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
            if (selector === "[data-chat-form]") {
                return form;
            }
            return selector === "[data-chat-load-older]" ? loadOlderButton : null;
        },
        querySelectorAll(selector) {
            return selector === "[data-chat-contact]" ? [contactButton] : [];
        },
        getElementById(id) {
            return id === "chat-box" ? chatBox : id === "chat-input" ? input : null;
        },
        createElement() {
            return {
                dataset: {},
                append() {},
                textContent: "",
                className: ""
            };
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
        const requestUrl = new URL(String(url), "http://localhost");
        if (requestUrl.searchParams.has("before_id")) {
            return Promise.resolve({
                ok: true,
                json: async () => ({
                    messages: [{
                        id: "9",
                        sender_id: 8,
                        sender_name: "Player",
                        body: "Earlier message"
                    }],
                    has_older: false
                })
            });
        }
        if (requestUrl.searchParams.has("after_id")) {
            const afterId = requestUrl.searchParams.get("after_id");
            return Promise.resolve({
                ok: true,
                json: async () => ({
                    messages: afterId === "10" ? [{
                        id: "11",
                        sender_id: 8,
                        sender_name: "Player",
                        body: "New message"
                    }] : [],
                    has_older: false
                })
            });
        }
        return Promise.resolve({
            ok: true,
            json: async () => ({
                messages: [{
                    id: "10",
                    sender_id: 8,
                    sender_name: "Player",
                    body: "hello"
                }],
                has_older: true
            })
        });
    };

    vm.runInNewContext(source, {
        AbortController,
        document,
        Error,
        fetch,
        JSON,
        Number,
        setInterval(callback) {
            intervalCallback = callback;
        },
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
    assert.ok(
        !fetchUrls.filter((url) => url.includes("conversation_type=player_player"))
            .some((url) => url.includes("after_id=")),
        "switching conversations should reset the message cursor"
    );
    assert.equal(contactButton.attributes["aria-pressed"], "true");
    assert.equal(typeInput.value, "player_player");
    assert.equal(loadOlderButton.hidden, false);

    listeners["older:click"]();
    await new Promise((resolve) => setImmediate(resolve));
    assert.ok(
        fetchUrls.some((url) => url.includes("before_id=10")),
        "loading earlier messages should request records before the oldest displayed ID"
    );
    assert.deepEqual(
        chatBox.children.map((bubble) => bubble.dataset.messageId),
        ["9", "10"],
        "earlier messages should prepend without replacing the recent page"
    );
    assert.equal(loadOlderButton.hidden, true, "the older-history button hides after the final page");

    intervalCallback();
    await new Promise((resolve) => setImmediate(resolve));
    assert.ok(
        fetchUrls.some((url) => url.includes("after_id=10")),
        "polling should use the most recent displayed ID as its cursor"
    );
    assert.deepEqual(
        chatBox.children.map((bubble) => bubble.dataset.messageId),
        ["9", "10", "11"],
        "new messages should append while loaded earlier history remains visible"
    );

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
    assert.ok(messageLoadCount >= 4, "initial, older, incremental, and post-send history loads should run");
    assert.ok(
        fetchUrls.some((url) => url.includes("conversation_type=player_player")
            && url.includes("after_id=10")),
        "subsequent loads should request messages after the last displayed ID"
    );
}

testConcurrentSubmissionsSendOnce()
    .then(() => console.log("Chat duplicate-send guard passed."))
    .catch((error) => {
        console.error(error);
        process.exitCode = 1;
    });
