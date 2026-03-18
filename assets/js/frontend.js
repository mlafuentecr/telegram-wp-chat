(function () {
  const storageKey = "twc-session-token";
  const pollInterval = 5000;
  const config = window.twcConfig || {};
  const root = document.getElementById("twc-widget-root");

  if (!root) {
    return;
  }

  const state = {
    open: false,
    token: window.localStorage.getItem(storageKey) || "",
    status: "idle",
    messages: [],
    pollingId: null,
  };

  root.innerHTML = `
    <button class="twc-bubble" type="button" aria-expanded="false" aria-controls="twc-panel">Chat</button>
    <section class="twc-panel" id="twc-panel" hidden>
      <header class="twc-header">
        <div>
          <strong>${escapeHtml(config.settings?.title || "Atencion en linea")}</strong>
          <span>${escapeHtml(config.settings?.subtitle || "")}</span>
        </div>
        <button class="twc-close" type="button" aria-label="Cerrar">×</button>
      </header>
      <div class="twc-body">
        <div class="twc-intro">
          <p>${escapeHtml(config.settings?.welcomeText || "")}</p>
          ${config.settings?.phone ? `<p class="twc-phone">${escapeHtml(config.settings.phone)}</p>` : ""}
        </div>
        <form class="twc-lead-form">
          <label>Nombre<input name="name" type="text" required></label>
          <label>Email<input name="email" type="email" required></label>
          <label>Asunto<input name="subject" type="text" required></label>
          <button type="submit">Solicitar chat</button>
        </form>
        <div class="twc-status" hidden></div>
        <div class="twc-messages" hidden></div>
        <form class="twc-message-form" hidden>
          <textarea name="message" rows="3" placeholder="Escribe tu mensaje"></textarea>
          <button type="submit">Enviar</button>
        </form>
      </div>
    </section>
  `;

  const bubble = root.querySelector(".twc-bubble");
  const panel = root.querySelector(".twc-panel");
  const closeButton = root.querySelector(".twc-close");
  const leadForm = root.querySelector(".twc-lead-form");
  const statusBox = root.querySelector(".twc-status");
  const messagesBox = root.querySelector(".twc-messages");
  const messageForm = root.querySelector(".twc-message-form");

  bubble.addEventListener("click", () => {
    state.open = !state.open;
    syncVisibility();
  });

  closeButton.addEventListener("click", () => {
    state.open = false;
    syncVisibility();
  });

  leadForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(leadForm);

    setStatus("Enviando solicitud...");

    const response = await fetchJson("/session", {
      method: "POST",
      body: {
        name: formData.get("name"),
        email: formData.get("email"),
        subject: formData.get("subject"),
      },
    });

    if (!response.ok) {
      setStatus(response.data?.message || "No se pudo crear la solicitud.");
      return;
    }

    state.token = response.data.token;
    state.status = response.data.status;
    window.localStorage.setItem(storageKey, state.token);
    leadForm.hidden = true;
    setStatus("Solicitud enviada. Esperando aprobacion en Telegram.");
    startPolling();
  });

  messageForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(messageForm);
    const message = (formData.get("message") || "").toString().trim();

    if (!message) {
      return;
    }

    const response = await fetchJson(`/session/${state.token}/message`, {
      method: "POST",
      body: { message },
    });

    if (!response.ok) {
      setStatus(response.data?.message || "No se pudo enviar el mensaje.");
      return;
    }

    messageForm.reset();
    await loadState();
  });

  function syncVisibility() {
    panel.hidden = !state.open;
    bubble.setAttribute("aria-expanded", state.open ? "true" : "false");
  }

  function setStatus(text) {
    statusBox.hidden = false;
    statusBox.textContent = text;
  }

  function renderMessages() {
    messagesBox.hidden = false;
    messagesBox.innerHTML = state.messages
      .map(
        (message) => `
          <article class="twc-message twc-message-${escapeHtml(message.sender)}">
            <span>${escapeHtml(message.text)}</span>
          </article>
        `
      )
      .join("");
    messagesBox.scrollTop = messagesBox.scrollHeight;
  }

  async function loadState() {
    if (!state.token) {
      return;
    }

    const response = await fetchJson(`/session/${state.token}/state`, {
      method: "GET",
    });

    if (!response.ok) {
      return;
    }

    state.status = response.data.status;
    state.messages = response.data.messages || [];

    leadForm.hidden = true;
    renderMessages();

    if (state.status === "pending") {
      messageForm.hidden = true;
      setStatus("Tu solicitud sigue pendiente de aprobacion.");
      return;
    }

    if (state.status === "accepted") {
      messageForm.hidden = false;
      setStatus("Chat activo.");
      return;
    }

    messageForm.hidden = true;
    setStatus(state.status === "rejected" ? "La solicitud fue rechazada." : "El chat ya no esta disponible.");
  }

  function startPolling() {
    if (state.pollingId) {
      return;
    }

    loadState();
    state.pollingId = window.setInterval(loadState, pollInterval);
  }

  async function fetchJson(path, options) {
    const fetchOptions = {
      method: options.method,
      headers: {
        "Content-Type": "application/json",
      },
    };

    if (options.body) {
      fetchOptions.body = JSON.stringify(options.body);
    }

    const response = await window.fetch(`${config.restUrl}${path}`, fetchOptions);
    let data = null;

    try {
      data = await response.json();
    } catch (error) {
      data = null;
    }

    return { ok: response.ok, data };
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  syncVisibility();

  if (state.token) {
    startPolling();
  }
})();
